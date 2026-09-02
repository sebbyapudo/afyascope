<?php

namespace Database\Factories;

use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Models\VisitCheckIn;
use App\StaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => fn (): int => VisitCheckIn::factory()->create()->visit_id,
            'doctor_user_id' => User::factory()->forRole(StaffRole::Doctor),
        ];
    }

    /**
     * Persist a test fixture representing a future authoritative finalization.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFinalizedFixture(array $attributes = []): Consultation
    {
        unset(
            $attributes['consultation_number'],
            $attributes['status'],
            $attributes['started_at'],
            $attributes['finalized_at'],
        );

        $consultation = $this->createOne($attributes);
        $consultation->status = ConsultationStatus::Finalized;
        $consultation->finalized_at = now();
        $consultation->saveQuietly();

        return $consultation;
    }
}
