<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\BillType;
use App\Http\Requests\StorePreProcedureReadinessRequest;
use App\Http\Requests\UpdatePreProcedureReadinessRequest;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PreProcedureReadinessController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $visits = Visit::query()
            ->select([
                'visits.id',
                'visits.patient_id',
                'visits.visit_number',
                'visits.occurred_at',
                'visits.status',
                'financial_clearances.granted_at as procedure_cleared_at',
            ])
            ->join('procedure_decisions', 'procedure_decisions.visit_id', '=', 'visits.id')
            ->join('procedure_billing_handoffs', function ($join): void {
                $join->on('procedure_billing_handoffs.visit_id', '=', 'visits.id')
                    ->on('procedure_billing_handoffs.procedure_decision_id', '=', 'procedure_decisions.id');
            })
            ->join('bills', function ($join): void {
                $join->on('bills.visit_id', '=', 'visits.id')
                    ->on('bills.procedure_billing_handoff_id', '=', 'procedure_billing_handoffs.id')
                    ->where('bills.type', BillType::Procedure->value);
            })
            ->join('financial_clearances', 'financial_clearances.bill_id', '=', 'bills.id')
            ->leftJoin('pre_procedure_readinesses', 'pre_procedure_readinesses.visit_id', '=', 'visits.id')
            ->where('procedure_decisions.outcome', ProcedureDecisionOutcome::ProcedureRequired->value)
            ->where(function ($query): void {
                $query->whereNull('pre_procedure_readinesses.id')
                    ->orWhere('pre_procedure_readinesses.status', PreProcedureReadinessStatus::InPreparation->value);
            })
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'consultation:id,visit_id,doctor_user_id,status',
                'consultation.doctor:id,name',
                'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome',
                'procedureDecision.serviceCatalogItem:id,name',
                'preProcedureReadiness:id,visit_id,procedure_decision_id,nurse_user_id,readiness_number,status,started_at,completed_at',
                'preProcedureReadiness.nurse:id,name',
            ])
            ->orderBy('financial_clearances.granted_at')
            ->orderBy('visits.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('nursing/pre-procedure-readiness/index', [
            'preparations' => [
                'data' => $visits->getCollection()
                    ->map(fn (Visit $visit): array => $this->queueVisitData($visit, $actor))
                    ->values(),
                'pagination' => $this->paginationData($visits),
            ],
        ]);
    }

    public function store(
        StorePreProcedureReadinessRequest $request,
        Visit $visit,
        StartPreProcedureReadiness $startPreProcedureReadiness,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $readiness = $startPreProcedureReadiness->handle($actor, $visit);

        return redirect()->route('nursing.pre-procedure-readiness.show', $readiness)->with(
            'status',
            "Nursing preparation {$readiness->readiness_number} was started.",
        );
    }

    public function show(Request $request, PreProcedureReadiness $preProcedureReadiness): Response
    {
        $preProcedureReadiness->load($this->workspaceRelations());

        return Inertia::render('nursing/pre-procedure-readiness/show', [
            'readiness' => $this->workspaceData($preProcedureReadiness, $request),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(
        UpdatePreProcedureReadinessRequest $request,
        PreProcedureReadiness $preProcedureReadiness,
        UpdatePreProcedureReadiness $updatePreProcedureReadiness,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updatePreProcedureReadiness->handle(
            $actor,
            $preProcedureReadiness,
            $request->readinessAttributes(),
        );

        return redirect()->route('nursing.pre-procedure-readiness.show', $preProcedureReadiness)->with(
            'status',
            'Pre-procedure readiness checks were saved.',
        );
    }

    /** @return list<string> */
    private function workspaceRelations(): array
    {
        return [
            'nurse:id,name',
            'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,decided_at',
            'procedureDecision.doctor:id,name',
            'procedureDecision.serviceCatalogItem:id,name',
            'procedureDecision.consultation:id,visit_id,doctor_user_id,current_medications,allergies,asa_classification',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name,date_of_birth,sex',
        ];
    }

    /** @return array<string, mixed> */
    private function queueVisitData(Visit $visit, User $actor): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;
        /** @var ProcedureDecision $decision */
        $decision = $visit->procedureDecision;
        /** @var ServiceCatalogItem $procedure */
        $procedure = $decision->serviceCatalogItem;
        /** @var Consultation $consultation */
        $consultation = $visit->consultation;
        /** @var User $doctor */
        $doctor = $consultation->doctor;
        $readiness = $visit->preProcedureReadiness;
        $clearedAt = CarbonImmutable::parse((string) $visit->getAttribute('procedure_cleared_at'));

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
                'name' => $procedure->name,
                'decisionNumber' => $decision->decision_number,
            ],
            'doctor' => ['name' => $doctor->name],
            'procedureClearedAt' => $clearedAt->toIso8601String(),
            'readiness' => $readiness instanceof PreProcedureReadiness ? [
                'id' => $readiness->id,
                'readinessNumber' => $readiness->readiness_number,
                'status' => [
                    'value' => $readiness->status->value,
                    'label' => $readiness->status->displayName(),
                ],
                'nurse' => ['name' => $readiness->nurse->name],
                'canManage' => $actor->can('update', $readiness),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function workspaceData(PreProcedureReadiness $readiness, Request $request): array
    {
        $decision = $readiness->procedureDecision;
        $visit = $readiness->visit;
        $patient = $visit->patient;
        $consultation = $decision->consultation;
        $procedure = $decision->serviceCatalogItem;

        return [
            'id' => $readiness->id,
            'readinessNumber' => $readiness->readiness_number,
            'status' => [
                'value' => $readiness->status->value,
                'label' => $readiness->status->displayName(),
            ],
            'startedAt' => $readiness->started_at->toIso8601String(),
            'completedAt' => $readiness->completed_at?->toIso8601String(),
            'canManage' => $request->user()?->can('update', $readiness) ?? false,
            'canComplete' => $request->user()?->can('complete', $readiness) ?? false,
            'nurse' => ['name' => $readiness->nurse->name],
            'checks' => [
                'consentVerified' => $readiness->consent_verified,
                'patientIdentityVerified' => $readiness->patient_identity_verified,
                'procedureVerified' => $readiness->procedure_verified,
                'allergiesReviewed' => $readiness->allergies_reviewed,
                'medicationsReviewed' => $readiness->medications_reviewed,
            ],
            'observations' => $readiness->observations,
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'nextStep' => $visit->workflowMessage(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
                'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                'sex' => $patient->sex?->displayName(),
            ],
            'doctor' => ['name' => $decision->doctor->name],
            'procedure' => [
                'name' => $procedure?->name,
                'decisionNumber' => $decision->decision_number,
                'decidedAt' => $decision->decided_at->toIso8601String(),
            ],
            'clinicalContext' => [
                'allergies' => $consultation->allergies,
                'currentMedications' => $consultation->current_medications,
                'asaClassification' => $consultation->asa_classification?->displayName(),
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Visit>  $paginator
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
