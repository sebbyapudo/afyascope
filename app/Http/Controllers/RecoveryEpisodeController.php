<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\StartRecoveryEpisode;
use App\Http\Requests\StoreRecoveryEpisodeRequest;
use App\Models\Patient;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryObservation;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RecoveryEpisodeController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $procedures = ProcedureRecord::query()
            ->readyForNursingRecovery()
            ->with($this->procedureContextRelations())
            ->orderBy('completed_at')
            ->orderBy('id')
            ->paginate(15, ['*'], 'awaiting_page')
            ->withQueryString();

        $activeRecoveries = RecoveryEpisode::query()
            ->where('nurse_user_id', $actor->getKey())
            ->where('status', RecoveryEpisodeStatus::InProgress)
            ->with([
                'procedureRecord:id,visit_id,service_catalog_item_id,doctor_user_id,procedure_number,status,completed_at',
                'procedureRecord.doctor:id,name',
                'procedureRecord.serviceCatalogItem:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
                'visit.consultation:id,visit_id,status',
                'visit.procedureDecision:id,visit_id,outcome',
                'visit.procedureRecord:id,visit_id,status',
                'visit.recoveryEpisode:id,visit_id,status',
                'visit.recoveryEpisode.openEscalation:id,recovery_episode_id,open_marker',
            ])
            ->orderBy('started_at')
            ->orderBy('id')
            ->paginate(15, ['*'], 'active_page')
            ->withQueryString();

        return Inertia::render('nursing/recovery/index', [
            'awaitingRecoveries' => [
                'data' => $procedures->getCollection()
                    ->map(fn (ProcedureRecord $procedureRecord): array => $this->procedureContextData($procedureRecord))
                    ->values(),
                'pagination' => $this->paginationData($procedures),
            ],
            'activeRecoveries' => [
                'data' => $activeRecoveries->getCollection()
                    ->map(fn (RecoveryEpisode $episode): array => $this->activeRecoveryData($episode))
                    ->values(),
                'pagination' => $this->paginationData($activeRecoveries),
            ],
        ]);
    }

    public function create(ProcedureRecord $procedureRecord): Response
    {
        $eligibleProcedure = ProcedureRecord::query()
            ->readyForNursingRecovery()
            ->with($this->procedureContextRelations())
            ->whereKey($procedureRecord->getKey())
            ->firstOrFail();

        return Inertia::render('nursing/recovery/create', [
            'procedure' => $this->procedureContextData($eligibleProcedure),
        ]);
    }

    public function store(
        StoreRecoveryEpisodeRequest $request,
        ProcedureRecord $procedureRecord,
        StartRecoveryEpisode $startRecoveryEpisode,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $recoveryEpisode = $startRecoveryEpisode->handle($actor, $procedureRecord);

        return redirect()->route('nursing.recovery.show', $recoveryEpisode)->with(
            'status',
            "Recovery {$recoveryEpisode->recovery_number} was started.",
        );
    }

    public function show(Request $request, RecoveryEpisode $recoveryEpisode): Response
    {
        $recoveryEpisode->load([
            'nurse:id,name',
            'procedureRecord:id,visit_id,service_catalog_item_id,doctor_user_id,procedure_number,status,started_at,completed_at',
            'procedureRecord.doctor:id,name',
            'procedureRecord.serviceCatalogItem:id,name',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
            'visit.consultation:id,visit_id,status',
            'visit.procedureDecision:id,visit_id,outcome',
            'visit.procedureRecord:id,visit_id,status',
            'visit.recoveryEpisode:id,visit_id,status',
            'visit.recoveryEpisode.openEscalation:id,recovery_episode_id,open_marker',
            'observations' => fn ($query) => $query->orderByDesc('recorded_at')->orderByDesc('id'),
            'observations.recordedBy:id,name',
            'readinessAssessment:id,recovery_episode_id,assessed_by_user_id,criteria_met,clinical_concern_requires_escalation,assessment_note,assessed_at',
            'readinessAssessment.assessedBy:id,name',
            'escalations' => fn ($query) => $query->orderByDesc('escalated_at')->orderByDesc('id'),
            'escalations.escalatedBy:id,name',
            'escalations.resolvedBy:id,name',
            'openEscalation:id,recovery_episode_id,status,open_marker',
        ]);

        $visit = $recoveryEpisode->visit;
        $patient = $visit->patient;
        $procedureRecord = $recoveryEpisode->procedureRecord;

        return Inertia::render('nursing/recovery/show', [
            'recovery' => [
                'id' => $recoveryEpisode->id,
                'recoveryNumber' => $recoveryEpisode->recovery_number,
                'status' => [
                    'value' => $recoveryEpisode->status->value,
                    'label' => $recoveryEpisode->status->displayName(),
                ],
                'startedAt' => $recoveryEpisode->started_at->toIso8601String(),
                'completedAt' => $recoveryEpisode->completed_at?->toIso8601String(),
                'canManage' => $request->user()?->can('update', $recoveryEpisode) ?? false,
                'isResponsibleNurse' => $request->user()?->getKey() === $recoveryEpisode->nurse_user_id,
                'canAssessReadiness' => $request->user()?->can('assessReadiness', $recoveryEpisode) ?? false,
                'canResolveEscalation' => $recoveryEpisode->openEscalation instanceof RecoveryEscalation
                    && ($request->user()?->can('resolve', $recoveryEpisode->openEscalation) ?? false),
                'nurse' => ['name' => $recoveryEpisode->nurse->name],
                'patient' => [
                    'patientNumber' => $patient->patient_number,
                    'name' => $this->patientName($patient),
                ],
                'visit' => [
                    'visitNumber' => $visit->visit_number,
                    'occurredAt' => $visit->occurred_at->toIso8601String(),
                    'nextStep' => $visit->workflowMessage(),
                ],
                'procedure' => [
                    'id' => $procedureRecord->id,
                    'procedureNumber' => $procedureRecord->procedure_number,
                    'name' => $procedureRecord->serviceCatalogItem->name,
                    'completedAt' => $procedureRecord->completed_at?->toIso8601String(),
                ],
                'doctor' => ['name' => $procedureRecord->doctor->name],
                'readinessAssessment' => $recoveryEpisode->readinessAssessment === null ? null : [
                    'criteriaMet' => $recoveryEpisode->readinessAssessment->criteria_met,
                    'clinicalConcernRequiresEscalation' => $recoveryEpisode->readinessAssessment->clinical_concern_requires_escalation,
                    'assessmentNote' => $recoveryEpisode->readinessAssessment->assessment_note,
                    'assessedAt' => $recoveryEpisode->readinessAssessment->assessed_at->toIso8601String(),
                    'assessedBy' => ['name' => $recoveryEpisode->readinessAssessment->assessedBy->name],
                ],
                'escalations' => $recoveryEpisode->escalations
                    ->map(fn (RecoveryEscalation $escalation): array => [
                        'id' => $escalation->id,
                        'reason' => $escalation->reason,
                        'status' => [
                            'value' => $escalation->status->value,
                            'label' => $escalation->status->displayName(),
                        ],
                        'escalatedAt' => $escalation->escalated_at->toIso8601String(),
                        'escalatedBy' => ['name' => $escalation->escalatedBy->name],
                        'resolution' => $escalation->resolution === null ? null : [
                            'value' => $escalation->resolution->value,
                            'label' => $escalation->resolution->displayName(),
                        ],
                        'resolutionNote' => $escalation->resolution_note,
                        'resolvedAt' => $escalation->resolved_at?->toIso8601String(),
                        'resolvedBy' => $escalation->resolvedBy === null
                            ? null
                            : ['name' => $escalation->resolvedBy->name],
                    ])->values(),
                'observations' => $recoveryEpisode->observations
                    ->map(fn (RecoveryObservation $observation): array => [
                        'id' => $observation->id,
                        'generalRecoveryStatus' => $observation->general_recovery_status,
                        'painScore' => $observation->pain_score,
                        'nausea' => $observation->nausea,
                        'vomiting' => $observation->vomiting,
                        'systolicBloodPressure' => $observation->systolic_blood_pressure,
                        'diastolicBloodPressure' => $observation->diastolic_blood_pressure,
                        'pulseRate' => $observation->pulse_rate,
                        'respiratoryRate' => $observation->respiratory_rate,
                        'oxygenSaturation' => $observation->oxygen_saturation,
                        'supplementalOxygen' => $observation->supplemental_oxygen,
                        'nursingNote' => $observation->nursing_note,
                        'recordedAt' => $observation->recorded_at->toIso8601String(),
                        'recordedBy' => ['name' => $observation->recordedBy->name],
                    ])->values(),
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    /** @return list<string> */
    private function procedureContextRelations(): array
    {
        return [
            'doctor:id,name',
            'serviceCatalogItem:id,name',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
            'visit.consultation:id,visit_id,status',
            'visit.procedureDecision:id,visit_id,outcome',
            'visit.procedureRecord:id,visit_id,status',
            'visit.recoveryEpisode:id,visit_id,status',
            'visit.recoveryEpisode.openEscalation:id,recovery_episode_id,open_marker',
        ];
    }

    /** @return array<string, mixed> */
    private function procedureContextData(ProcedureRecord $procedureRecord): array
    {
        $visit = $procedureRecord->visit;
        $patient = $visit->patient;

        return [
            'id' => $procedureRecord->id,
            'procedureNumber' => $procedureRecord->procedure_number,
            'completedAt' => $procedureRecord->completed_at?->toIso8601String(),
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'nextStep' => $visit->workflowMessage(),
            ],
            'procedure' => ['name' => $procedureRecord->serviceCatalogItem->name],
            'doctor' => ['name' => $procedureRecord->doctor->name],
        ];
    }

    /** @return array<string, mixed> */
    private function activeRecoveryData(RecoveryEpisode $recoveryEpisode): array
    {
        $procedureRecord = $recoveryEpisode->procedureRecord;
        $visit = $recoveryEpisode->visit;
        $patient = $visit->patient;

        return [
            'id' => $recoveryEpisode->id,
            'recoveryNumber' => $recoveryEpisode->recovery_number,
            'startedAt' => $recoveryEpisode->started_at->toIso8601String(),
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'nextStep' => $visit->workflowMessage(),
            ],
            'procedure' => [
                'procedureNumber' => $procedureRecord->procedure_number,
                'name' => $procedureRecord->serviceCatalogItem->name,
            ],
            'doctor' => ['name' => $procedureRecord->doctor->name],
        ];
    }

    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
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

    private function patientName(Patient $patient): string
    {
        return collect([
            $patient->first_name,
            $patient->middle_name,
            $patient->last_name,
        ])->filter()->implode(' ');
    }
}
