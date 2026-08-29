<?php

namespace Database\Factories;

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'action' => AuditAction::StaffUpdated,
            'subject_type' => User::class,
            'subject_id' => User::factory(),
            'before_values' => ['name' => 'Previous name'],
            'after_values' => ['name' => 'Updated name'],
            'metadata' => null,
        ];
    }
}
