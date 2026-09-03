<?php

namespace App\Actions\Consultations;

use App\Actions\Audit\RecordAuditLog;
use App\AsaClassification;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateConsultationAssessment
{
    /** @var list<string> */
    private const ASSESSMENT_FIELDS = [
        'presenting_complaint',
        'relevant_history',
        'current_medications',
        'allergies',
        'examination_findings',
        'asa_classification',
        'assessment_impression',
        'plan_notes',
    ];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Consultation $consultation, array $attributes): Consultation
    {
        Gate::forUser($actor)->authorize('update', $consultation);

        $normalizedAttributes = $this->normalize($attributes);
        $validated = Validator::make($normalizedAttributes, [
            'id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'consultation_number' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
            'finalized_at' => ['prohibited'],
            'check_in_id' => ['prohibited'],
            'bill_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'receipt_id' => ['prohibited'],
            'financial_clearance_id' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'relevant_history' => ['nullable', 'string', 'max:5000'],
            'current_medications' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'asa_classification' => ['nullable', Rule::enum(AsaClassification::class)],
            'assessment_impression' => ['nullable', 'string', 'max:5000'],
            'plan_notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $consultation, $validated): Consultation {
            $lockedConsultation = Consultation::query()
                ->whereKey($consultation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

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
                    'consultation' => 'Only the responsible active Doctor may update an in-progress Consultation.',
                ]);
            }

            foreach (self::ASSESSMENT_FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    $lockedConsultation->setAttribute($field, $validated[$field]);
                }
            }

            $changedFields = array_values(array_intersect(
                self::ASSESSMENT_FIELDS,
                array_keys($lockedConsultation->getDirty()),
            ));

            if ($changedFields === []) {
                return $lockedConsultation;
            }

            $lockedConsultation->save();

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ConsultationAssessmentUpdated,
                subject: $lockedConsultation,
                afterValues: [
                    'consultation_number' => $lockedConsultation->consultation_number,
                    'visit_id' => $lockedConsultation->visit_id,
                    'doctor_user_id' => $lockedActor->getKey(),
                ],
                metadata: ['changed_fields' => $changedFields],
            );

            return $lockedConsultation->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        foreach (self::ASSESSMENT_FIELDS as $field) {
            if (! array_key_exists($field, $attributes) || ! is_string($attributes[$field])) {
                continue;
            }

            $value = trim($attributes[$field]);
            $attributes[$field] = $value === '' ? null : $value;
        }

        return $attributes;
    }
}
