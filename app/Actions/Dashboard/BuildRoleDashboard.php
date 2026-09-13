<?php

namespace App\Actions\Dashboard;

use App\Actions\Reporting\BuildManagementSummary;
use App\Actions\Reporting\ReportingPeriod;
use App\AppointmentStatus;
use App\BillStatus;
use App\BillType;
use App\ConsultationStatus;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\RecoveryEpisodeStatus;
use App\StaffPermission;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-type DashboardMetric array{label: string, value: int, description: string}
 * @phpstan-type DashboardProjection array{kind: string, eyebrow: string, title: string, description: string, emptyMessage: string, period: array{fromDate: string, throughDate: string}|null, metrics: list<DashboardMetric>}
 */
final class BuildRoleDashboard
{
    public function __construct(private BuildManagementSummary $buildManagementSummary) {}

    /** @return DashboardProjection */
    public function handle(User $actor): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::DashboardView);

        return match (StaffRole::from($actor->role->slug)) {
            StaffRole::Receptionist => $this->receptionDashboard(),
            StaffRole::Accountant => $this->accountantDashboard(),
            StaffRole::Doctor => $this->doctorDashboard($actor),
            StaffRole::Nurse => $this->nurseDashboard($actor),
            StaffRole::Administrator => $this->administratorDashboard(),
            StaffRole::Management => $this->managementDashboard($actor),
        };
    }

    /** @return DashboardProjection */
    private function receptionDashboard(): array
    {
        $today = CarbonImmutable::now();
        $awaitingAttendance = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->whereDoesntHave('visit');

        return $this->dashboard(
            kind: StaffRole::Receptionist->value,
            eyebrow: 'Reception workspace',
            title: 'Front-desk priorities',
            description: 'Move scheduled attendance and active Visits through the Reception handoff.',
            emptyMessage: 'There are no immediate Reception handoffs in the current queues.',
            metrics: [
                $this->metric(
                    'Scheduled appointments awaiting attendance',
                    (clone $awaitingAttendance)->count(),
                    'Scheduled records that have not yet produced a Visit.',
                ),
                $this->metric(
                    'Scheduled for today',
                    (clone $awaitingAttendance)->whereBetween('scheduled_at', [
                        $today->startOfDay(),
                        $today->endOfDay(),
                    ])->count(),
                    'Today\'s scheduled appointments still awaiting attendance.',
                ),
                $this->metric(
                    'Visits awaiting consultation billing',
                    Visit::query()
                        ->where('status', VisitStatus::Created->value)
                        ->whereDoesntHave('consultationBill')
                        ->count(),
                    'Created Visits waiting for the Accountant billing handoff.',
                ),
                $this->metric(
                    'Ready for Reception check-in',
                    $this->visitsReadyForCheckIn()->count(),
                    'Consultation-cleared Visits that may now be checked in.',
                ),
                $this->metric(
                    'Checked-in active Visits',
                    Visit::query()->where('status', VisitStatus::CheckedIn->value)->count(),
                    'Active Visits already handed into the clinical workflow.',
                ),
            ],
        );
    }

    /** @return DashboardProjection */
    private function accountantDashboard(): array
    {
        $today = CarbonImmutable::now();

        return $this->dashboard(
            kind: StaffRole::Accountant->value,
            eyebrow: 'Finance workspace',
            title: 'Financial work queues',
            description: 'Complete the two approved billing, payment, and financial-clearance gates.',
            emptyMessage: 'There are no immediate financial handoffs in the current queues.',
            metrics: [
                $this->metric(
                    'Visits awaiting consultation Bill',
                    Visit::query()
                        ->where('status', VisitStatus::Created->value)
                        ->whereDoesntHave('consultationBill')
                        ->count(),
                    'Created Visits that need the consultation charge.',
                ),
                $this->metric(
                    'Procedure decisions awaiting Bill',
                    ProcedureBillingHandoff::query()->awaitingBill()->count(),
                    'Doctor-authorized procedure handoffs without a procedure Bill.',
                ),
                $this->metric(
                    'Consultation Bills awaiting payment',
                    $this->billsAwaitingPayment(BillType::Consultation)->count(),
                    'Open consultation Bills with no recorded Payment.',
                ),
                $this->metric(
                    'Procedure Bills awaiting payment',
                    $this->billsAwaitingPayment(BillType::Procedure)->count(),
                    'Open procedure Bills with no recorded Payment.',
                ),
                $this->metric(
                    'Consultation Bills awaiting clearance',
                    $this->billsAwaitingClearance(BillType::Consultation)->count(),
                    'Paid consultation Bills awaiting the separate clearance action.',
                ),
                $this->metric(
                    'Procedure Bills awaiting clearance',
                    $this->billsAwaitingClearance(BillType::Procedure)->count(),
                    'Paid procedure Bills awaiting the separate clearance action.',
                ),
                $this->metric(
                    'Payments recorded today',
                    Payment::query()->whereBetween('recorded_at', [
                        $today->startOfDay(),
                        $today->endOfDay(),
                    ])->count(),
                    'Successful consultation and procedure Payments recorded today.',
                ),
                $this->metric(
                    'Clearances granted today',
                    FinancialClearance::query()->whereBetween('granted_at', [
                        $today->startOfDay(),
                        $today->endOfDay(),
                    ])->count(),
                    'Successful consultation and procedure clearances granted today.',
                ),
            ],
        );
    }

    /** @return DashboardProjection */
    private function doctorDashboard(User $actor): array
    {
        return $this->dashboard(
            kind: StaffRole::Doctor->value,
            eyebrow: 'Doctor workspace',
            title: 'Clinical priorities',
            description: 'Continue your consultation and procedure work from its authoritative handoffs.',
            emptyMessage: 'There are no immediate Doctor actions in the current queues.',
            metrics: [
                $this->metric(
                    'Visits ready for consultation',
                    Visit::query()->readyForDoctorConsultation()->count(),
                    'Checked-in Visits that have not started consultation.',
                ),
                $this->metric(
                    'My consultations in progress',
                    Consultation::query()
                        ->where('doctor_user_id', $actor->getKey())
                        ->where('status', ConsultationStatus::InProgress->value)
                        ->count(),
                    'Active consultations assigned to you.',
                ),
                $this->metric(
                    'My consultations awaiting decision',
                    Consultation::query()
                        ->where('doctor_user_id', $actor->getKey())
                        ->where('status', ConsultationStatus::InProgress->value)
                        ->whereDoesntHave('procedureDecision')
                        ->count(),
                    'Your active consultations without the immutable procedure decision.',
                ),
                $this->metric(
                    'My procedures ready to start',
                    $this->proceduresReadyForDoctor($actor)->count(),
                    'Nurse-prepared procedures released to your procedure queue.',
                ),
                $this->metric(
                    'My procedures in progress',
                    ProcedureRecord::query()
                        ->where('doctor_user_id', $actor->getKey())
                        ->where('status', ProcedureRecordStatus::InProgress->value)
                        ->count(),
                    'Procedure Records assigned to you and not yet completed.',
                ),
                $this->metric(
                    'Recovery cases awaiting review',
                    RecoveryEscalation::query()->where('open_marker', true)->count(),
                    'Open Nursing escalations requiring a Doctor decision.',
                ),
            ],
        );
    }

    /** @return DashboardProjection */
    private function nurseDashboard(User $actor): array
    {
        return $this->dashboard(
            kind: StaffRole::Nurse->value,
            eyebrow: 'Nursing workspace',
            title: 'Preparation and recovery priorities',
            description: 'Continue Nurse-owned preparation, recovery, escalation, and discharge work.',
            emptyMessage: 'There are no immediate Nursing actions in the current queues.',
            metrics: [
                $this->metric(
                    'Awaiting Nursing preparation',
                    $this->visitsAwaitingNursingPreparation()->count(),
                    'Procedure-cleared Visits with no preparation record.',
                ),
                $this->metric(
                    'My active preparations',
                    PreProcedureReadiness::query()
                        ->where('nurse_user_id', $actor->getKey())
                        ->where('status', PreProcedureReadinessStatus::InPreparation->value)
                        ->count(),
                    'In-progress preparation records assigned to you.',
                ),
                $this->metric(
                    'Prepared and ready for procedure',
                    PreProcedureReadiness::query()
                        ->where('status', PreProcedureReadinessStatus::Ready->value)
                        ->whereDoesntHave('procedureRecord')
                        ->count(),
                    'Completed readiness handoffs awaiting the responsible Doctor.',
                ),
                $this->metric(
                    'Awaiting Nursing recovery',
                    ProcedureRecord::query()->readyForNursingRecovery()->count(),
                    'Completed procedures without a Recovery Episode.',
                ),
                $this->metric(
                    'My recoveries in progress',
                    RecoveryEpisode::query()
                        ->where('nurse_user_id', $actor->getKey())
                        ->where('status', RecoveryEpisodeStatus::InProgress->value)
                        ->count(),
                    'Active Recovery Episodes assigned to you.',
                ),
                $this->metric(
                    'My Doctor-review cases',
                    RecoveryEpisode::query()
                        ->where('nurse_user_id', $actor->getKey())
                        ->whereHas('openEscalation')
                        ->count(),
                    'Your recoveries paused at the Doctor-review boundary.',
                ),
                $this->metric(
                    'My ready-for-discharge cases',
                    RecoveryEpisode::query()
                        ->where('nurse_user_id', $actor->getKey())
                        ->where('status', RecoveryEpisodeStatus::ReadyForDischarge->value)
                        ->count(),
                    'Your recoveries with completed readiness assessment.',
                ),
            ],
        );
    }

    /** @return DashboardProjection */
    private function administratorDashboard(): array
    {
        return $this->dashboard(
            kind: StaffRole::Administrator->value,
            eyebrow: 'Administration workspace',
            title: 'System administration',
            description: 'Maintain staff access and the approved Service Catalog without entering operational workflows.',
            emptyMessage: 'No administrative records are available yet.',
            metrics: [
                $this->metric(
                    'Active staff accounts',
                    User::query()->where('is_active', true)->count(),
                    'Staff accounts currently permitted to authenticate.',
                ),
                $this->metric(
                    'Disabled staff accounts',
                    User::query()->where('is_active', false)->count(),
                    'Historical staff accounts currently blocked from access.',
                ),
                $this->metric(
                    'Active catalog services',
                    ServiceCatalogItem::query()->where('is_active', true)->count(),
                    'Services available to eligible operational selections.',
                ),
                $this->metric(
                    'Inactive catalog services',
                    ServiceCatalogItem::query()->where('is_active', false)->count(),
                    'Historical services excluded from new selections.',
                ),
            ],
        );
    }

    /** @return DashboardProjection */
    private function managementDashboard(User $actor): array
    {
        $today = CarbonImmutable::now();
        $summary = $this->buildManagementSummary->handle(
            $actor,
            new ReportingPeriod($today->startOfMonth(), $today),
        );

        return $this->dashboard(
            kind: StaffRole::Management->value,
            eyebrow: 'Management workspace',
            title: 'Clinic overview',
            description: 'Review selected current-month milestones, then continue to the authoritative reports for analysis.',
            emptyMessage: 'No reportable clinic activity has been recorded this month.',
            period: [
                'fromDate' => $summary['period']['fromDate'],
                'throughDate' => $summary['period']['throughDate'],
            ],
            metrics: [
                $this->metric('Visits this month', $summary['visits']['occurred'], 'Visits occurring in the current reporting period.'),
                $this->metric('Completed Visits this month', $summary['visits']['completed'], 'Visits completing in the current reporting period.'),
                $this->metric('Bills created this month', $summary['financial']['billCount'], 'Consultation and procedure Bills created this month.'),
                $this->metric('Payments recorded this month', $summary['financial']['paymentCount'], 'Successful Payments recorded this month.'),
                $this->metric('Procedures completed this month', $summary['clinical']['proceduresCompleted'], 'Procedure Records completed this month.'),
                $this->metric('Discharges completed this month', $summary['clinical']['dischargesCompleted'], 'Recovery discharges finalized this month.'),
            ],
        );
    }

    /** @return Builder<Visit> */
    private function visitsReadyForCheckIn(): Builder
    {
        return Visit::query()
            ->where('status', VisitStatus::Created->value)
            ->whereDoesntHave('checkIn')
            ->whereHas('consultationBill', function (Builder $query): void {
                $query
                    ->where('type', BillType::Consultation->value)
                    ->where('status', BillStatus::Paid->value)
                    ->whereHas('payment.receipt')
                    ->whereHas('financialClearance');
            });
    }

    /** @return Builder<Bill> */
    private function billsAwaitingPayment(BillType $type): Builder
    {
        return Bill::query()
            ->where('type', $type->value)
            ->where('status', BillStatus::Open->value)
            ->whereDoesntHave('payment')
            ->when($type === BillType::Procedure, function (Builder $query): void {
                $query->whereHas('procedureBillingHandoff.procedureDecision', function (Builder $decisionQuery): void {
                    $decisionQuery->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value);
                });
            });
    }

    /** @return Builder<Bill> */
    private function billsAwaitingClearance(BillType $type): Builder
    {
        return Bill::query()
            ->where('type', $type->value)
            ->where('status', BillStatus::Paid->value)
            ->whereHas('payment.receipt')
            ->whereDoesntHave('financialClearance')
            ->when($type === BillType::Procedure, function (Builder $query): void {
                $query->whereHas('procedureBillingHandoff.procedureDecision', function (Builder $decisionQuery): void {
                    $decisionQuery->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value);
                });
            });
    }

    /** @return Builder<Visit> */
    private function proceduresReadyForDoctor(User $actor): Builder
    {
        return Visit::query()
            ->where('status', VisitStatus::CheckedIn->value)
            ->whereHas('procedureDecision', function (Builder $query) use ($actor): void {
                $query
                    ->where('doctor_user_id', $actor->getKey())
                    ->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value)
                    ->whereHas('consultation', function (Builder $consultationQuery) use ($actor): void {
                        $consultationQuery
                            ->where('doctor_user_id', $actor->getKey())
                            ->where('status', ConsultationStatus::InProgress->value);
                    });
            })
            ->whereHas('preProcedureReadiness', function (Builder $query): void {
                $query->where('status', PreProcedureReadinessStatus::Ready->value);
            })
            ->whereDoesntHave('procedureRecord');
    }

    /** @return Builder<Visit> */
    private function visitsAwaitingNursingPreparation(): Builder
    {
        return Visit::query()
            ->where('status', VisitStatus::CheckedIn->value)
            ->whereHas('procedureDecision', function (Builder $query): void {
                $query->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value);
            })
            ->whereHas('procedureBill', function (Builder $query): void {
                $query
                    ->where('status', BillStatus::Paid->value)
                    ->whereHas('payment.receipt')
                    ->whereHas('financialClearance');
            })
            ->whereDoesntHave('preProcedureReadiness');
    }

    /**
     * @param  list<DashboardMetric>  $metrics
     * @param  array{fromDate: string, throughDate: string}|null  $period
     * @return DashboardProjection
     */
    private function dashboard(
        string $kind,
        string $eyebrow,
        string $title,
        string $description,
        string $emptyMessage,
        array $metrics,
        ?array $period = null,
    ): array {
        return [
            'kind' => $kind,
            'eyebrow' => $eyebrow,
            'title' => $title,
            'description' => $description,
            'emptyMessage' => $emptyMessage,
            'period' => $period,
            'metrics' => $metrics,
        ];
    }

    /** @return DashboardMetric */
    private function metric(string $label, int $value, string $description): array
    {
        return compact('label', 'value', 'description');
    }
}
