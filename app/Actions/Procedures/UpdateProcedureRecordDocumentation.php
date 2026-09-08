<?php

namespace App\Actions\Procedures;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateProcedureRecordDocumentation
{
    /** @var list<string> */
    private const DOCUMENTATION_FIELDS = [
        'findings',
        'diagnosis_impression',
        'specimens_taken',
        'specimen_notes',
        'complications',
        'outcome',
        'procedure_notes',
    ];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        User $actor,
        ProcedureRecord $procedureRecord,
        array $attributes,
    ): ProcedureRecord {
        Gate::forUser($actor)->authorize('update', $procedureRecord);

        $validated = Validator::make($this->normalize($attributes), [
            'id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'procedure_decision_id' => ['prohibited'],
            'pre_procedure_readiness_id' => ['prohibited'],
            'service_catalog_item_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'procedure_number' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'lock_version' => ['prohibited'],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'findings' => ['present', 'nullable', 'string', 'max:5000'],
            'diagnosis_impression' => ['present', 'nullable', 'string', 'max:5000'],
            'specimens_taken' => ['required', 'boolean'],
            'specimen_notes' => ['present', 'nullable', 'string', 'max:5000'],
            'complications' => ['present', 'nullable', 'string', 'max:5000'],
            'outcome' => ['present', 'nullable', 'string', 'max:5000'],
            'procedure_notes' => ['present', 'nullable', 'string', 'max:5000'],
        ])->validate();

        $expectedLockVersion = (int) $validated['expected_lock_version'];
        unset($validated['expected_lock_version']);

        return DB::transaction(function () use (
            $actor,
            $procedureRecord,
            $validated,
            $expectedLockVersion,
        ): ProcedureRecord {
            $lockedProcedureRecord = ProcedureRecord::query()
                ->lockForUpdate()
                ->find($procedureRecord->getKey());
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Doctor->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedProcedureRecord instanceof ProcedureRecord
                || ! $lockedActor instanceof User
                || $lockedProcedureRecord->doctor_user_id !== $lockedActor->getKey()
                || $lockedProcedureRecord->getRawOriginal('status') !== ProcedureRecordStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'procedure' => 'Only the responsible active Doctor may update an in-progress procedure.',
                ]);
            }

            if ($lockedProcedureRecord->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'procedure' => 'This procedure record changed after you opened it. Reload before saving.',
                ]);
            }

            $changedFields = collect(self::DOCUMENTATION_FIELDS)
                ->filter(fn (string $field): bool => $lockedProcedureRecord->getAttribute($field) !== $validated[$field])
                ->values()
                ->all();

            if ($changedFields === []) {
                return $lockedProcedureRecord;
            }

            $lockedProcedureRecord->updateDocumentationFromDoctorWorkflow($lockedActor, $validated);

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ProcedureDocumentationUpdated,
                subject: $lockedProcedureRecord,
                afterValues: [
                    'procedure_number' => $lockedProcedureRecord->procedure_number,
                    'visit_id' => $lockedProcedureRecord->visit_id,
                    'doctor_user_id' => $lockedActor->getKey(),
                    'status' => $lockedProcedureRecord->status->value,
                    'changed_fields' => $changedFields,
                ],
            );

            return $lockedProcedureRecord->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        foreach ([
            'findings',
            'diagnosis_impression',
            'specimen_notes',
            'complications',
            'outcome',
            'procedure_notes',
        ] as $field) {
            if (array_key_exists($field, $attributes) && is_string($attributes[$field])) {
                $value = trim($attributes[$field]);
                $attributes[$field] = $value === '' ? null : $value;
            }
        }

        if (in_array($attributes['specimens_taken'] ?? null, [false, 0, '0'], true)) {
            $attributes['specimen_notes'] = null;
        }

        return $attributes;
    }
}
