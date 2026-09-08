<?php

namespace App\Http\Controllers;

use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\ConsultationStatus;
use App\Http\Requests\StoreProcedureRecordRequest;
use App\Http\Requests\UpdateProcedureRecordRequest;
use App\Models\Patient;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ProcedureRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $readyVisits = Visit::query()
            ->select([
                'visits.id',
                'visits.patient_id',
                'visits.visit_number',
                'visits.occurred_at',
                'visits.status',
                'pre_procedure_readinesses.completed_at as readiness_completed_at',
            ])
            ->join('procedure_decisions', 'procedure_decisions.visit_id', '=', 'visits.id')
            ->join('consultations', function ($join) use ($actor): void {
                $join->on('consultations.id', '=', 'procedure_decisions.consultation_id')
                    ->where('consultations.doctor_user_id', $actor->getKey())
                    ->where('consultations.status', ConsultationStatus::InProgress->value);
            })
            ->join('pre_procedure_readinesses', function ($join): void {
                $join->on('pre_procedure_readinesses.visit_id', '=', 'visits.id')
                    ->on('pre_procedure_readinesses.procedure_decision_id', '=', 'procedure_decisions.id')
                    ->where('pre_procedure_readinesses.status', PreProcedureReadinessStatus::Ready->value);
            })
            ->where('procedure_decisions.outcome', ProcedureDecisionOutcome::ProcedureRequired->value)
            ->where('procedure_decisions.doctor_user_id', $actor->getKey())
            ->whereDoesntHave('procedureRecord')
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,decided_at',
                'procedureDecision.serviceCatalogItem:id,name',
                'procedureDecision.doctor:id,name',
                'preProcedureReadiness:id,visit_id,procedure_decision_id,nurse_user_id,readiness_number,status,completed_at',
                'preProcedureReadiness.nurse:id,name',
            ])
            ->orderBy('pre_procedure_readinesses.completed_at')
            ->orderBy('visits.id')
            ->paginate(15, ['*'], 'ready_page')
            ->withQueryString();

        $inProgressProcedures = ProcedureRecord::query()
            ->select([
                'id',
                'visit_id',
                'pre_procedure_readiness_id',
                'service_catalog_item_id',
                'doctor_user_id',
                'procedure_number',
                'status',
                'started_at',
            ])
            ->where('doctor_user_id', $actor->getKey())
            ->where('status', ProcedureRecordStatus::InProgress->value)
            ->with([
                'doctor:id,name',
                'serviceCatalogItem:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
                'preProcedureReadiness:id,nurse_user_id,readiness_number,status,completed_at',
                'preProcedureReadiness.nurse:id,name',
            ])
            ->orderBy('started_at')
            ->orderBy('id')
            ->paginate(15, ['*'], 'in_progress_page')
            ->withQueryString();

        return Inertia::render('clinical/procedures/index', [
            'readyProcedures' => [
                'data' => $readyVisits->getCollection()
                    ->map(fn (Visit $visit): array => $this->readyVisitData($visit))
                    ->values(),
                'pagination' => $this->paginationData($readyVisits),
            ],
            'inProgressProcedures' => [
                'data' => $inProgressProcedures->getCollection()
                    ->map(fn (ProcedureRecord $procedureRecord): array => $this->queueProcedureData($procedureRecord))
                    ->values(),
                'pagination' => $this->paginationData($inProgressProcedures),
            ],
        ]);
    }

    public function store(
        StoreProcedureRecordRequest $request,
        Visit $visit,
        StartProcedureRecord $startProcedureRecord,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $procedureRecord = $startProcedureRecord->handle($actor, $visit);

        return redirect()->route('clinical.procedures.show', $procedureRecord)->with(
            'status',
            "Procedure {$procedureRecord->procedure_number} was started.",
        );
    }

    public function show(Request $request, ProcedureRecord $procedureRecord): Response
    {
        $procedureRecord->load($this->workspaceRelations());

        return Inertia::render('clinical/procedures/show', [
            'procedure' => $this->workspaceData($procedureRecord, $request),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(
        UpdateProcedureRecordRequest $request,
        ProcedureRecord $procedureRecord,
        UpdateProcedureRecordDocumentation $updateProcedureRecordDocumentation,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updateProcedureRecordDocumentation->handle(
            $actor,
            $procedureRecord,
            $request->procedureAttributes(),
        );

        return redirect()->route('clinical.procedures.show', $procedureRecord)->with(
            'status',
            'Procedure documentation was saved.',
        );
    }

    /** @return list<string> */
    private function workspaceRelations(): array
    {
        return [
            'doctor:id,name',
            'serviceCatalogItem:id,name',
            'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,clinical_rationale,decided_at',
            'procedureDecision.consultation:id,visit_id,doctor_user_id,consultation_number,status,started_at,presenting_complaint,relevant_history,current_medications,allergies,examination_findings,asa_classification,assessment_impression,plan_notes',
            'preProcedureReadiness:id,visit_id,procedure_decision_id,nurse_user_id,readiness_number,status,started_at,completed_at',
            'preProcedureReadiness.nurse:id,name',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name,date_of_birth,sex',
            'visit.procedureRecord:id,visit_id,status',
        ];
    }

    /** @return array<string, mixed> */
    private function readyVisitData(Visit $visit): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;
        /** @var ProcedureDecision $decision */
        $decision = $visit->procedureDecision;
        /** @var ServiceCatalogItem $service */
        $service = $decision->serviceCatalogItem;
        /** @var User $doctor */
        $doctor = $decision->doctor;
        /** @var PreProcedureReadiness $readiness */
        $readiness = $visit->preProcedureReadiness;

        return [
            'visit' => [
                'id' => $visit->id,
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'nextStep' => $visit->workflowMessage(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
            'procedure' => [
                'name' => $service->name,
                'decisionNumber' => $decision->decision_number,
            ],
            'doctor' => ['name' => $doctor->name],
            'readiness' => [
                'readinessNumber' => $readiness->readiness_number,
                'nurse' => ['name' => $readiness->nurse->name],
                'completedAt' => $readiness->completed_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function queueProcedureData(ProcedureRecord $procedureRecord): array
    {
        $visit = $procedureRecord->visit;
        $patient = $visit->patient;

        return [
            'id' => $procedureRecord->id,
            'procedureNumber' => $procedureRecord->procedure_number,
            'startedAt' => $procedureRecord->started_at->toIso8601String(),
            'status' => [
                'value' => $procedureRecord->status->value,
                'label' => $procedureRecord->status->displayName(),
            ],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'nextStep' => $visit->workflowMessage(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
            'procedure' => ['name' => $procedureRecord->serviceCatalogItem->name],
            'doctor' => ['name' => $procedureRecord->doctor->name],
            'readiness' => [
                'readinessNumber' => $procedureRecord->preProcedureReadiness->readiness_number,
                'nurse' => ['name' => $procedureRecord->preProcedureReadiness->nurse->name],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workspaceData(ProcedureRecord $procedureRecord, Request $request): array
    {
        $visit = $procedureRecord->visit;
        $patient = $visit->patient;
        $decision = $procedureRecord->procedureDecision;
        $consultation = $decision->consultation;
        $readiness = $procedureRecord->preProcedureReadiness;

        return [
            'id' => $procedureRecord->id,
            'procedureNumber' => $procedureRecord->procedure_number,
            'status' => [
                'value' => $procedureRecord->status->value,
                'label' => $procedureRecord->status->displayName(),
            ],
            'startedAt' => $procedureRecord->started_at->toIso8601String(),
            'completedAt' => $procedureRecord->completed_at?->toIso8601String(),
            'lockVersion' => $procedureRecord->lock_version,
            'canManage' => $request->user()?->can('update', $procedureRecord) ?? false,
            'canComplete' => $request->user()?->can('complete', $procedureRecord) ?? false,
            'doctor' => ['name' => $procedureRecord->doctor->name],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
                'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                'sex' => $patient->sex?->displayName(),
            ],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'nextStep' => $visit->workflowMessage(),
            ],
            'selectedProcedure' => [
                'id' => $procedureRecord->serviceCatalogItem->id,
                'name' => $procedureRecord->serviceCatalogItem->name,
                'decisionNumber' => $decision->decision_number,
                'clinicalRationale' => $decision->clinical_rationale,
                'decidedAt' => $decision->decided_at->toIso8601String(),
            ],
            'readiness' => [
                'readinessNumber' => $readiness->readiness_number,
                'status' => [
                    'value' => $readiness->status->value,
                    'label' => $readiness->status->displayName(),
                ],
                'nurse' => ['name' => $readiness->nurse->name],
                'completedAt' => $readiness->completed_at?->toIso8601String(),
            ],
            'clinicalContext' => [
                'consultationNumber' => $consultation->consultation_number,
                'presentingComplaint' => $consultation->presenting_complaint,
                'relevantHistory' => $consultation->relevant_history,
                'currentMedications' => $consultation->current_medications,
                'allergies' => $consultation->allergies,
                'examinationFindings' => $consultation->examination_findings,
                'asaClassification' => $consultation->asa_classification?->displayName(),
                'assessmentImpression' => $consultation->assessment_impression,
                'planNotes' => $consultation->plan_notes,
            ],
            'documentation' => [
                'findings' => $procedureRecord->findings,
                'diagnosisImpression' => $procedureRecord->diagnosis_impression,
                'specimensTaken' => $procedureRecord->specimens_taken,
                'specimenNotes' => $procedureRecord->specimen_notes,
                'complications' => $procedureRecord->complications,
                'outcome' => $procedureRecord->outcome,
                'procedureNotes' => $procedureRecord->procedure_notes,
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Visit>|LengthAwarePaginator<int, ProcedureRecord>  $paginator
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
