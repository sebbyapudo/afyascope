<?php

namespace App\Http\Controllers;

use App\Actions\Consultations\BeginConsultation;
use App\Actions\Consultations\UpdateConsultationAssessment;
use App\AsaClassification;
use App\BillType;
use App\ConsultationStatus;
use App\Http\Requests\StoreConsultationRequest;
use App\Http\Requests\UpdateConsultationAssessmentRequest;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
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
            ])
            ->readyForDoctorConsultation()
            ->join('visit_check_ins', 'visit_check_ins.visit_id', '=', 'visits.id')
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'checkIn:id,visit_id,check_in_number,checked_in_at',
            ])
            ->orderBy('visit_check_ins.checked_in_at')
            ->orderBy('visits.id')
            ->paginate(15, ['*'], 'ready_page')
            ->withQueryString();

        $inProgressConsultations = Consultation::query()
            ->select([
                'id',
                'visit_id',
                'doctor_user_id',
                'consultation_number',
                'status',
                'started_at',
            ])
            ->where('doctor_user_id', $actor->getKey())
            ->where('status', ConsultationStatus::InProgress->value)
            ->with([
                'doctor:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
                'visit.checkIn:id,visit_id,check_in_number,checked_in_at',
                'visit.procedureDecision:id,visit_id,outcome',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'in_progress_page')
            ->withQueryString();

        return Inertia::render('clinical/consultations/index', [
            'readyVisits' => [
                'data' => $readyVisits->getCollection()
                    ->map(fn (Visit $visit): array => $this->readyVisitData($visit))
                    ->values(),
                'pagination' => $this->paginationData($readyVisits),
            ],
            'inProgressConsultations' => [
                'data' => $inProgressConsultations->getCollection()
                    ->map(fn (Consultation $consultation): array => $this->consultationData(
                        $consultation,
                        true,
                    ))
                    ->values(),
                'pagination' => $this->paginationData($inProgressConsultations),
            ],
        ]);
    }

    public function create(Visit $visit): Response|RedirectResponse
    {
        $existingConsultation = $visit->consultation()->first();

        if ($existingConsultation instanceof Consultation) {
            return redirect()->route('clinical.consultations.show', $existingConsultation);
        }

        $visit->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'checkIn:id,visit_id,check_in_number,checked_in_at',
            'consultation:id,visit_id',
        ]);

        abort_unless($visit->isReadyForDoctorConsultation(), 404);

        return Inertia::render('clinical/consultations/create', [
            'visit' => $this->readyVisitData($visit),
        ]);
    }

    public function store(
        StoreConsultationRequest $request,
        Visit $visit,
        BeginConsultation $beginConsultation,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $consultation = $beginConsultation->handle($actor, $visit);

        return redirect()->route('clinical.consultations.show', $consultation)->with(
            'status',
            "Consultation {$consultation->consultation_number} was started.",
        );
    }

    public function show(Request $request, Consultation $consultation): Response
    {
        $consultation->load([
            'doctor:id,name',
            'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,clinical_rationale,decided_at',
            'procedureDecision.procedureBillingHandoff:id,procedure_decision_id,handoff_number',
            'procedureDecision.serviceCatalogItem:id,name',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
            'visit.checkIn:id,visit_id,check_in_number,checked_in_at',
            'visit.procedureDecision:id,visit_id,outcome',
        ]);
        $status = $request->session()->get('status');
        $canManage = $request->user()?->can('update', $consultation) ?? false;
        $canRecordProcedureDecision = $canManage
            && ! $consultation->procedureDecision instanceof ProcedureDecision;

        return Inertia::render('clinical/consultations/show', [
            'consultation' => $this->consultationWorkspaceData(
                $consultation,
                $canManage,
            ),
            'procedureServices' => $canRecordProcedureDecision
                ? ServiceCatalogItem::query()
                    ->select(['id', 'name'])
                    ->where('category', BillType::Procedure->value)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ServiceCatalogItem $service): array => [
                        'id' => $service->id,
                        'name' => $service->name,
                    ])
                    ->values()
                : [],
            'asaClassifications' => array_map(
                fn (AsaClassification $classification): array => [
                    'value' => $classification->value,
                    'label' => $classification->displayName(),
                ],
                AsaClassification::cases(),
            ),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    public function update(
        UpdateConsultationAssessmentRequest $request,
        Consultation $consultation,
        UpdateConsultationAssessment $updateConsultationAssessment,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updateConsultationAssessment->handle(
            $actor,
            $consultation,
            $request->assessmentAttributes(),
        );

        return redirect()->route('clinical.consultations.show', $consultation)->with(
            'status',
            'Clinical assessment saved.',
        );
    }

    /**
     * @return array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string, checkIn: array{checkInNumber: string, checkedInAt: string}, patient: array{patientNumber: string, name: string}}
     */
    private function readyVisitData(Visit $visit): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;
        /** @var VisitCheckIn $visitCheckIn */
        $visitCheckIn = $visit->checkIn;

        return [
            'id' => $visit->id,
            'visitNumber' => $visit->visit_number,
            'occurredAt' => $visit->occurred_at->toIso8601String(),
            'status' => [
                'value' => $visit->status->value,
                'label' => $visit->status->displayName(),
            ],
            'nextStep' => $visit->workflowMessage(),
            'checkIn' => [
                'checkInNumber' => $visitCheckIn->check_in_number,
                'checkedInAt' => $visitCheckIn->checked_in_at->toIso8601String(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ];
    }

    /**
     * @return array{id: int, consultationNumber: string, status: array{value: string, label: string}, startedAt: string, canManage: bool, doctor: array{id: int, name: string}, visit: array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string, checkIn: array{checkInNumber: string, checkedInAt: string}, patient: array{patientNumber: string, name: string}}}
     */
    private function consultationData(Consultation $consultation, bool $canManage): array
    {
        /** @var User $doctor */
        $doctor = $consultation->doctor;

        return [
            'id' => $consultation->id,
            'consultationNumber' => $consultation->consultation_number,
            'status' => [
                'value' => $consultation->status->value,
                'label' => $consultation->status->displayName(),
            ],
            'startedAt' => $consultation->started_at->toIso8601String(),
            'canManage' => $canManage,
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
            ],
            'visit' => $this->readyVisitData($consultation->visit),
        ];
    }

    /**
     * @return array{id: int, consultationNumber: string, status: array{value: string, label: string}, startedAt: string, canManage: bool, canRecordProcedureDecision: bool, doctor: array{id: int, name: string}, visit: array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string, checkIn: array{checkInNumber: string, checkedInAt: string}, patient: array{patientNumber: string, name: string}}, assessment: array{presentingComplaint: string|null, relevantHistory: string|null, currentMedications: string|null, allergies: string|null, examinationFindings: string|null, asaClassification: string|null, assessmentImpression: string|null, planNotes: string|null}, procedureDecision: array{decisionNumber: string, outcome: array{value: string, label: string}, clinicalRationale: string|null, decidedAt: string, service: array{id: int, name: string}|null, handoff: array{handoffNumber: string}|null}|null}
     */
    private function consultationWorkspaceData(Consultation $consultation, bool $canManage): array
    {
        $procedureDecision = $consultation->procedureDecision;

        return [
            ...$this->consultationData($consultation, $canManage),
            'canRecordProcedureDecision' => $canManage
                && ! $procedureDecision instanceof ProcedureDecision,
            'assessment' => [
                'presentingComplaint' => $consultation->presenting_complaint,
                'relevantHistory' => $consultation->relevant_history,
                'currentMedications' => $consultation->current_medications,
                'allergies' => $consultation->allergies,
                'examinationFindings' => $consultation->examination_findings,
                'asaClassification' => $consultation->asa_classification?->value,
                'assessmentImpression' => $consultation->assessment_impression,
                'planNotes' => $consultation->plan_notes,
            ],
            'procedureDecision' => $procedureDecision instanceof ProcedureDecision ? [
                'decisionNumber' => $procedureDecision->decision_number,
                'outcome' => [
                    'value' => $procedureDecision->outcome->value,
                    'label' => $procedureDecision->outcome->displayName(),
                ],
                'clinicalRationale' => $procedureDecision->clinical_rationale,
                'decidedAt' => $procedureDecision->decided_at->toIso8601String(),
                'service' => $procedureDecision->serviceCatalogItem instanceof ServiceCatalogItem ? [
                    'id' => $procedureDecision->serviceCatalogItem->id,
                    'name' => $procedureDecision->serviceCatalogItem->name,
                ] : null,
                'handoff' => $procedureDecision->procedureBillingHandoff instanceof ProcedureBillingHandoff ? [
                    'handoffNumber' => $procedureDecision->procedureBillingHandoff->handoff_number,
                ] : null,
            ] : null,
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Visit>|LengthAwarePaginator<int, Consultation>  $paginator
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
