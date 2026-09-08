<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\StartRecoveryEpisode;
use App\Http\Requests\StoreRecoveryEpisodeRequest;
use App\Models\Patient;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RecoveryEpisodeController extends Controller
{
    public function index(): Response
    {
        $procedures = ProcedureRecord::query()
            ->readyForNursingRecovery()
            ->with($this->procedureContextRelations())
            ->orderBy('completed_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('nursing/recovery/index', [
            'procedures' => [
                'data' => $procedures->getCollection()
                    ->map(fn (ProcedureRecord $procedureRecord): array => $this->procedureContextData($procedureRecord))
                    ->values(),
                'pagination' => $this->paginationData($procedures),
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

    /**
     * @param  LengthAwarePaginator<int, ProcedureRecord>  $paginator
     * @return array{currentPage: int, from: int|null, lastPage: int, perPage: int, to: int|null, total: int}
     */
    private function paginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'lastPage' => $paginator->lastPage(),
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
