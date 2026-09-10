<?php

namespace App\Actions\PatientActivity;

use App\AuditAction;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryObservation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffPermission;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use stdClass;

class ListPatientActivity
{
    private const PER_PAGE = 20;

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{currentPage: int, from: int|null, lastPage: int, pageName: string, perPage: int, to: int|null, total: int},
     *     activityOptions: list<array{value: string, label: string}>
     * }
     */
    public function handle(User $actor, string $search = '', string $activity = ''): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::PatientActivityView);

        $role = StaffRole::tryFrom((string) $actor->role?->slug);
        $activityOptions = $this->activityOptions($role);
        $allowedActivities = array_column($activityOptions, 'value');

        if ($activity !== '' && ! in_array($activity, $allowedActivities, true)) {
            throw ValidationException::withMessages([
                'activity' => 'Select an activity available to your staff role.',
            ]);
        }

        $activitySummary = DB::query()
            ->fromSub($this->filteredEvents($actor, $role, $activity), 'actor_activity')
            ->select('visit_id')
            ->selectRaw('MAX(activity_at) as latest_activity_at')
            ->groupBy('visit_id');

        $activities = DB::query()
            ->fromSub($activitySummary, 'activity_summary')
            ->join('visits', 'visits.id', '=', 'activity_summary.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $searchPrefix = addcslashes($search, '\\%_').'%';

                $query->where(function (Builder $searchQuery) use ($searchPrefix): void {
                    $searchQuery
                        ->where('visits.visit_number', 'like', $searchPrefix)
                        ->orWhere('patients.patient_number', 'like', $searchPrefix)
                        ->orWhere('patients.first_name', 'like', $searchPrefix)
                        ->orWhere('patients.middle_name', 'like', $searchPrefix)
                        ->orWhere('patients.last_name', 'like', $searchPrefix);
                });
            })
            ->orderByDesc('activity_summary.latest_activity_at')
            ->orderByDesc('visits.id')
            ->paginate(
                self::PER_PAGE,
                [
                    'visits.id as visit_id',
                    'visits.visit_number',
                    'patients.patient_number',
                    'patients.first_name',
                    'patients.middle_name',
                    'patients.last_name',
                    'activity_summary.latest_activity_at',
                ],
                'activity_page',
            )
            ->withQueryString();

        $visitIds = array_values(
            collect($activities->items())
                ->map(fn (stdClass $row): int => (int) $row->visit_id)
                ->all(),
        );
        $latestEvents = $this->latestEventsForVisits($actor, $role, $activity, $visitIds);
        $visits = $this->visitsForWorkflowProjection($visitIds);

        $activities->setCollection(
            $activities->getCollection()
                ->map(function (stdClass $row) use ($latestEvents, $visits): array {
                    $visitId = (int) $row->visit_id;
                    $event = $latestEvents->get($visitId);
                    $visit = $visits->get($visitId);

                    if (! $event instanceof stdClass || ! $visit instanceof Visit) {
                        throw new LogicException('Patient activity must resolve to its authoritative Visit and event.');
                    }

                    return [
                        'patient' => [
                            'patientNumber' => (string) $row->patient_number,
                            'name' => collect([
                                $row->first_name,
                                $row->middle_name,
                                $row->last_name,
                            ])->filter()->implode(' '),
                        ],
                        'visit' => [
                            'visitNumber' => (string) $row->visit_number,
                            'currentStage' => $visit->workflowMessage(),
                        ],
                        'activity' => [
                            'type' => (string) $event->activity_type,
                            'label' => (string) $event->activity_label,
                            'reference' => (string) $event->activity_reference,
                            'occurredAt' => CarbonImmutable::parse((string) $event->activity_at)->toIso8601String(),
                        ],
                        'destination' => [
                            'type' => (string) $event->destination_type,
                            'id' => (int) $event->destination_id,
                        ],
                    ];
                })
                ->values(),
        );

        return [
            'data' => array_values($activities->items()),
            'pagination' => $this->paginationData($activities),
            'activityOptions' => $activityOptions,
        ];
    }

    /**
     * @param  list<int>  $visitIds
     * @return Collection<int, stdClass>
     */
    private function latestEventsForVisits(
        User $actor,
        ?StaffRole $role,
        string $activity,
        array $visitIds,
    ): Collection {
        if ($visitIds === []) {
            return collect();
        }

        return DB::query()
            ->fromSub($this->filteredEvents($actor, $role, $activity), 'actor_activity')
            ->whereIn('visit_id', $visitIds)
            ->orderByDesc('activity_at')
            ->orderByDesc('event_id')
            ->get()
            ->groupBy(fn (stdClass $event): int => (int) $event->visit_id)
            ->map(fn (Collection $events): stdClass => $events->first());
    }

    /**
     * @param  list<int>  $visitIds
     * @return Collection<int, Visit>
     */
    private function visitsForWorkflowProjection(array $visitIds): Collection
    {
        return Visit::query()
            ->whereKey($visitIds)
            ->with([
                'consultation:id,visit_id,status',
                'procedureDecision:id,visit_id,outcome',
                'preProcedureReadiness:id,visit_id,status',
                'procedureRecord:id,visit_id,status',
                'recoveryEpisode:id,visit_id,status',
                'recoveryEpisode.openEscalation:id,recovery_episode_id,open_marker',
                'consultationBill:id,visit_id,type',
                'consultationBill.payment:id,bill_id',
                'consultationBill.financialClearance:id,bill_id',
                'procedureBill:id,visit_id,type',
                'procedureBill.payment:id,bill_id',
                'procedureBill.payment.receipt:id,payment_id',
                'procedureBill.financialClearance:id,bill_id',
            ])
            ->get(['id', 'patient_id', 'visit_number', 'status'])
            ->keyBy('id');
    }

    private function filteredEvents(User $actor, ?StaffRole $role, string $activity): Builder
    {
        $events = $this->eventsForRole($actor, $role);

        if ($activity === '') {
            return $events;
        }

        return DB::query()
            ->fromSub($events, 'role_activity')
            ->where('activity_type', $activity);
    }

    private function eventsForRole(User $actor, ?StaffRole $role): Builder
    {
        $queries = match ($role) {
            StaffRole::Receptionist => $this->receptionEvents($actor),
            StaffRole::Accountant => $this->accountantEvents($actor),
            StaffRole::Doctor => $this->doctorEvents($actor),
            StaffRole::Nurse => $this->nurseEvents($actor),
            default => [],
        };

        if ($queries === []) {
            throw ValidationException::withMessages([
                'actor' => 'Patient activity is available only to operational staff roles.',
            ]);
        }

        $events = array_shift($queries);

        foreach ($queries as $query) {
            $events->unionAll($query);
        }

        return $events;
    }

    /** @return list<Builder> */
    private function receptionEvents(User $actor): array
    {
        return [
            $this->visitEvent($actor, AuditAction::VisitCreated, 'visit', 'visit'),
            $this->appointmentEvent($actor, AuditAction::AppointmentCreated),
            $this->appointmentEvent($actor, AuditAction::AppointmentRescheduled),
            $this->appointmentEvent($actor, AuditAction::AppointmentCancelled),
            $this->appointmentEvent($actor, AuditAction::AppointmentNoShow),
            $this->appointmentEvent($actor, AuditAction::AppointmentVisitLinked),
            $this->checkInEvent($actor),
        ];
    }

    /** @return list<Builder> */
    private function accountantEvents(User $actor): array
    {
        return [
            $this->billEvent($actor),
            $this->paymentEvent($actor),
            $this->receiptEvent($actor),
            $this->clearanceEvent($actor, AuditAction::ConsultationFinancialCleared),
            $this->clearanceEvent($actor, AuditAction::ProcedureFinancialCleared),
        ];
    }

    /** @return list<Builder> */
    private function doctorEvents(User $actor): array
    {
        return [
            $this->consultationEvent($actor, AuditAction::ConsultationStarted),
            $this->consultationEvent($actor, AuditAction::ConsultationAssessmentUpdated),
            $this->procedureDecisionEvent($actor),
            $this->procedureEvent($actor, AuditAction::ProcedureStarted),
            $this->procedureEvent($actor, AuditAction::ProcedureDocumentationUpdated),
            $this->procedureEvent($actor, AuditAction::ProcedureCompleted),
            $this->recoveryEscalationEvent($actor, AuditAction::RecoveryEscalationResolved),
        ];
    }

    /** @return list<Builder> */
    private function nurseEvents(User $actor): array
    {
        return [
            $this->readinessEvent($actor, AuditAction::NursingPreparationStarted),
            $this->readinessEvent($actor, AuditAction::NursingReadinessCompleted),
            $this->recoveryEvent($actor),
            $this->recoveryObservationEvent($actor),
            $this->recoveryReadinessEvent($actor),
            $this->recoveryEscalationEvent($actor, AuditAction::RecoveryEscalated),
        ];
    }

    private function visitEvent(User $actor, AuditAction $action, string $activityType, string $destinationType): Builder
    {
        return $this->eventBase($actor, $action, Visit::class)
            ->join('visits', 'visits.id', '=', 'audit_logs.subject_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'visits.visit_number as activity_reference',
                'visits.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                $activityType,
                $action->displayName(),
                $destinationType,
            ]);
    }

    private function appointmentEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, Appointment::class)
            ->join('appointments', 'appointments.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.appointment_id', '=', 'appointments.id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'appointments.appointment_number as activity_reference',
                'appointments.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'appointment',
                $action->displayName(),
                'appointment',
            ]);
    }

    private function checkInEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::VisitCheckedIn, VisitCheckIn::class)
            ->join('visit_check_ins', 'visit_check_ins.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'visit_check_ins.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'visit_check_ins.check_in_number as activity_reference',
                'visit_check_ins.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'check_in',
                AuditAction::VisitCheckedIn->displayName(),
                'check_in',
            ]);
    }

    private function billEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::BillCreated, Bill::class)
            ->join('bills', 'bills.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'bills.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'bills.bill_number as activity_reference',
                'bills.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'bill',
                AuditAction::BillCreated->displayName(),
                'bill',
            ]);
    }

    private function paymentEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::PaymentRecorded, Payment::class)
            ->join('payments', 'payments.id', '=', 'audit_logs.subject_id')
            ->join('bills', 'bills.id', '=', 'payments.bill_id')
            ->join('visits', 'visits.id', '=', 'bills.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'payments.payment_number as activity_reference',
                'bills.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'payment',
                AuditAction::PaymentRecorded->displayName(),
                'bill',
            ]);
    }

    private function receiptEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::ReceiptIssued, Receipt::class)
            ->join('receipts', 'receipts.id', '=', 'audit_logs.subject_id')
            ->join('payments', 'payments.id', '=', 'receipts.payment_id')
            ->join('bills', 'bills.id', '=', 'payments.bill_id')
            ->join('visits', 'visits.id', '=', 'bills.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'receipts.receipt_number as activity_reference',
                'receipts.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'receipt',
                AuditAction::ReceiptIssued->displayName(),
                'receipt',
            ]);
    }

    private function clearanceEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, FinancialClearance::class)
            ->join('financial_clearances', 'financial_clearances.id', '=', 'audit_logs.subject_id')
            ->join('bills', 'bills.id', '=', 'financial_clearances.bill_id')
            ->join('visits', 'visits.id', '=', 'bills.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'financial_clearances.clearance_number as activity_reference',
                'financial_clearances.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'clearance',
                $action->displayName(),
                'clearance',
            ]);
    }

    private function consultationEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, Consultation::class)
            ->join('consultations', 'consultations.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'consultations.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'consultations.consultation_number as activity_reference',
                'consultations.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'consultation',
                $action->displayName(),
                'consultation',
            ]);
    }

    private function procedureDecisionEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::ConsultationProcedureDecided, ProcedureDecision::class)
            ->join('procedure_decisions', 'procedure_decisions.id', '=', 'audit_logs.subject_id')
            ->join('consultations', 'consultations.id', '=', 'procedure_decisions.consultation_id')
            ->join('visits', 'visits.id', '=', 'procedure_decisions.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'procedure_decisions.decision_number as activity_reference',
                'consultations.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'procedure_decision',
                AuditAction::ConsultationProcedureDecided->displayName(),
                'consultation',
            ]);
    }

    private function procedureEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, ProcedureRecord::class)
            ->join('procedure_records', 'procedure_records.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'procedure_records.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'procedure_records.procedure_number as activity_reference',
                'procedure_records.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'procedure',
                $action->displayName(),
                'procedure',
            ]);
    }

    private function readinessEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, PreProcedureReadiness::class)
            ->join('pre_procedure_readinesses', 'pre_procedure_readinesses.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'pre_procedure_readinesses.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'pre_procedure_readinesses.readiness_number as activity_reference',
                'pre_procedure_readinesses.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'preparation',
                $action->displayName(),
                'preparation',
            ]);
    }

    private function recoveryEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::RecoveryStarted, RecoveryEpisode::class)
            ->join('recovery_episodes', 'recovery_episodes.id', '=', 'audit_logs.subject_id')
            ->join('visits', 'visits.id', '=', 'recovery_episodes.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'recovery_episodes.recovery_number as activity_reference',
                'recovery_episodes.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'recovery',
                AuditAction::RecoveryStarted->displayName(),
                'recovery',
            ]);
    }

    private function recoveryObservationEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::RecoveryObservationRecorded, RecoveryObservation::class)
            ->join('recovery_observations', 'recovery_observations.id', '=', 'audit_logs.subject_id')
            ->join('recovery_episodes', 'recovery_episodes.id', '=', 'recovery_observations.recovery_episode_id')
            ->join('visits', 'visits.id', '=', 'recovery_episodes.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'recovery_episodes.recovery_number as activity_reference',
                'recovery_episodes.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'recovery',
                AuditAction::RecoveryObservationRecorded->displayName(),
                'recovery',
            ]);
    }

    private function recoveryReadinessEvent(User $actor): Builder
    {
        return $this->eventBase($actor, AuditAction::RecoveryReadinessAssessed, RecoveryReadinessAssessment::class)
            ->join('recovery_readiness_assessments', 'recovery_readiness_assessments.id', '=', 'audit_logs.subject_id')
            ->join('recovery_episodes', 'recovery_episodes.id', '=', 'recovery_readiness_assessments.recovery_episode_id')
            ->join('visits', 'visits.id', '=', 'recovery_episodes.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'recovery_episodes.recovery_number as activity_reference',
                'recovery_episodes.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'recovery',
                AuditAction::RecoveryReadinessAssessed->displayName(),
                'recovery',
            ]);
    }

    private function recoveryEscalationEvent(User $actor, AuditAction $action): Builder
    {
        return $this->eventBase($actor, $action, RecoveryEscalation::class)
            ->join('recovery_escalations', 'recovery_escalations.id', '=', 'audit_logs.subject_id')
            ->join('recovery_episodes', 'recovery_episodes.id', '=', 'recovery_escalations.recovery_episode_id')
            ->join('visits', 'visits.id', '=', 'recovery_episodes.visit_id')
            ->select([
                'audit_logs.id as event_id',
                'visits.id as visit_id',
                'audit_logs.created_at as activity_at',
                'recovery_episodes.recovery_number as activity_reference',
                'recovery_episodes.id as destination_id',
            ])
            ->selectRaw('? as activity_type, ? as activity_label, ? as destination_type', [
                'recovery',
                $action->displayName(),
                'recovery',
            ]);
    }

    private function eventBase(User $actor, AuditAction $action, string $subjectType): Builder
    {
        return DB::table('audit_logs')
            ->where('audit_logs.actor_id', $actor->getKey())
            ->where('audit_logs.action', $action->value)
            ->where('audit_logs.subject_type', $subjectType);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function activityOptions(?StaffRole $role): array
    {
        return match ($role) {
            StaffRole::Receptionist => [
                ['value' => 'visit', 'label' => 'Visit creation'],
                ['value' => 'appointment', 'label' => 'Appointment handling'],
                ['value' => 'check_in', 'label' => 'Reception check-in'],
            ],
            StaffRole::Accountant => [
                ['value' => 'bill', 'label' => 'Bill creation'],
                ['value' => 'payment', 'label' => 'Payment recording'],
                ['value' => 'receipt', 'label' => 'Receipt issue'],
                ['value' => 'clearance', 'label' => 'Financial clearance'],
            ],
            StaffRole::Doctor => [
                ['value' => 'consultation', 'label' => 'Consultation'],
                ['value' => 'procedure_decision', 'label' => 'Procedure decision'],
                ['value' => 'procedure', 'label' => 'Procedure'],
                ['value' => 'recovery', 'label' => 'Recovery review'],
            ],
            StaffRole::Nurse => [
                ['value' => 'preparation', 'label' => 'Procedure preparation'],
                ['value' => 'recovery', 'label' => 'Recovery'],
            ],
            default => [],
        };
    }

    /**
     * @param  LengthAwarePaginator<int, stdClass>  $paginator
     * @return array{currentPage: int, from: int|null, lastPage: int, pageName: string, perPage: int, to: int|null, total: int}
     */
    private function paginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'lastPage' => $paginator->lastPage(),
            'pageName' => $paginator->getPageName(),
            'perPage' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
