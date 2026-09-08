<?php

namespace Database\Factories;

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcedureRecord>
 */
class ProcedureRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'findings' => null,
            'diagnosis_impression' => null,
            'specimens_taken' => false,
            'specimen_notes' => null,
            'complications' => null,
            'outcome' => null,
            'procedure_notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProcedureRecordStatus::Completed,
            'findings' => 'Documented procedure findings.',
            'outcome' => 'Procedure completed as planned.',
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function createAuthoritativeProcedureFixture(
        ProcedureDecision $procedureDecision,
        PreProcedureReadiness $preProcedureReadiness,
        ?User $doctor = null,
        array $attributes = [],
    ): ProcedureRecord {
        unset(
            $attributes['visit_id'],
            $attributes['procedure_decision_id'],
            $attributes['pre_procedure_readiness_id'],
            $attributes['service_catalog_item_id'],
            $attributes['doctor_user_id'],
            $attributes['procedure_number'],
            $attributes['started_at'],
        );

        $procedureRecord = $this->makeOne($attributes);
        $procedureRecord->visit_id = $procedureDecision->visit_id;
        $procedureRecord->procedure_decision_id = $procedureDecision->id;
        $procedureRecord->pre_procedure_readiness_id = $preProcedureReadiness->id;
        $procedureRecord->service_catalog_item_id = $procedureDecision->service_catalog_item_id;
        $procedureRecord->doctor_user_id = ($doctor ?? $procedureDecision->doctor)->id;
        $procedureRecord->procedure_number = 'TMP-'.Str::ulid();
        $procedureRecord->status ??= ProcedureRecordStatus::InProgress;
        $procedureRecord->lock_version ??= 1;
        $procedureRecord->started_at = now();
        $procedureRecord->saveQuietly();
        $procedureRecord->procedure_number = sprintf('PRC-%06d', $procedureRecord->id);
        $procedureRecord->saveQuietly();

        return $procedureRecord->refresh();
    }
}
