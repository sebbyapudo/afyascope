<?php

namespace App\Actions\Consultations;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordProcedureDecision
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Consultation $consultation, array $attributes): ProcedureDecision
    {
        Gate::forUser($actor)->authorize('update', $consultation);

        $attributes = $this->normalize($attributes);
        $validated = Validator::make($attributes, $this->rules($attributes))->validate();
        $outcome = ProcedureDecisionOutcome::from($validated['outcome']);

        return DB::transaction(function () use ($actor, $consultation, $validated, $outcome): ProcedureDecision {
            $lockedConsultation = Consultation::query()
                ->whereKey($consultation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVisit = Visit::query()
                ->whereKey($lockedConsultation->visit_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value) {
                throw ValidationException::withMessages([
                    'consultation' => 'A procedure decision requires a checked-in Visit.',
                ]);
            }

            $visitCheckIn = VisitCheckIn::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if (! $visitCheckIn instanceof VisitCheckIn) {
                throw ValidationException::withMessages([
                    'consultation' => 'A procedure decision requires a valid Reception check-in.',
                ]);
            }

            $existingDecision = ProcedureDecision::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();
            $existingHandoff = ProcedureBillingHandoff::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingDecision instanceof ProcedureDecision || $existingHandoff instanceof ProcedureBillingHandoff) {
                throw ValidationException::withMessages([
                    'consultation' => 'The authoritative procedure decision has already been recorded.',
                ]);
            }

            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Doctor->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User
                || $lockedConsultation->doctor_user_id !== $lockedActor->getKey()
                || $lockedConsultation->getRawOriginal('status') !== ConsultationStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'consultation' => 'Only the responsible active Doctor may decide an in-progress Consultation.',
                ]);
            }

            $serviceCatalogItem = null;

            if ($outcome === ProcedureDecisionOutcome::ProcedureRequired) {
                $serviceCatalogItem = ServiceCatalogItem::query()
                    ->whereKey($validated['service_catalog_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $serviceCatalogItem instanceof ServiceCatalogItem
                    || ! $serviceCatalogItem->is_active
                    || $serviceCatalogItem->category !== BillType::Procedure) {
                    throw ValidationException::withMessages([
                        'service_catalog_item_id' => 'Select an active procedure service.',
                    ]);
                }
            }

            $decision = ProcedureDecision::recordFromAuthoritativeDoctorWorkflow(
                consultation: $lockedConsultation,
                doctor: $lockedActor,
                outcome: $outcome,
                serviceCatalogItem: $serviceCatalogItem,
                clinicalRationale: $validated['clinical_rationale'] ?? null,
            );

            if ($outcome === ProcedureDecisionOutcome::ProcedureRequired) {
                ProcedureBillingHandoff::createFromProcedureDecision($decision);
            }

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ConsultationProcedureDecided,
                subject: $decision,
                afterValues: [
                    'decision_number' => $decision->decision_number,
                    'consultation_number' => $lockedConsultation->consultation_number,
                    'visit_number' => $lockedVisit->visit_number,
                    'doctor_user_id' => $lockedActor->getKey(),
                    'outcome' => $outcome->value,
                    'service_catalog_item_id' => $serviceCatalogItem?->getKey(),
                ],
            );

            return $decision->load([
                'consultation:id,visit_id,doctor_user_id,consultation_number,status',
                'doctor:id,name',
                'procedureBillingHandoff:id,procedure_decision_id,visit_id,service_catalog_item_id,decided_by_user_id,handoff_number,decided_at',
                'serviceCatalogItem:id,name,category,is_active',
                'visit:id,patient_id,visit_number,status',
            ]);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (array_key_exists('clinical_rationale', $attributes) && is_string($attributes['clinical_rationale'])) {
            $clinicalRationale = trim($attributes['clinical_rationale']);
            $attributes['clinical_rationale'] = $clinicalRationale === '' ? null : $clinicalRationale;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, list<mixed|string>>
     */
    private function rules(array $attributes): array
    {
        $isProcedureRequired = ($attributes['outcome'] ?? null) === ProcedureDecisionOutcome::ProcedureRequired->value;
        $isNoProcedure = ($attributes['outcome'] ?? null) === ProcedureDecisionOutcome::NoProcedure->value;

        return [
            'id' => ['prohibited'],
            'consultation_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'decision_number' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'handoff_number' => ['prohibited'],
            'bill_id' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'unit_price_minor' => ['prohibited'],
            'price' => ['prohibited'],
            'outcome' => ['required', Rule::enum(ProcedureDecisionOutcome::class)],
            'service_catalog_item_id' => [
                Rule::requiredIf($isProcedureRequired),
                Rule::prohibitedIf($isNoProcedure),
                'nullable',
                'integer',
                'exists:service_catalog_items,id',
            ],
            'clinical_rationale' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
