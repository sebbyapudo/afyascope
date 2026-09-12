<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\RecoveryEpisode;
use App\Models\Role;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateStaffUser
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{name: string, email: string, role: string, is_active: bool}  $attributes
     */
    public function handle(User $actor, User $staffUser, array $attributes): User
    {
        Gate::forUser($actor)->authorize('update', $staffUser);

        return DB::transaction(function () use ($actor, $staffUser, $attributes): User {
            $administratorRole = Role::query()
                ->where('slug', StaffRole::Administrator->value)
                ->lockForUpdate()
                ->sole();
            $targetRole = Role::query()
                ->where('slug', StaffRole::from($attributes['role'])->value)
                ->sole();
            $lockedStaffUser = User::query()
                ->whereKey($staffUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $currentRole = Role::query()->whereKey($lockedStaffUser->role_id)->sole();

            $removesActiveAdministrator = $lockedStaffUser->role_id === $administratorRole->id
                && $lockedStaffUser->is_active
                && (! $attributes['is_active'] || $targetRole->id !== $administratorRole->id);

            if ($removesActiveAdministrator && ! $this->anotherActiveAdministratorExists(
                $administratorRole,
                $lockedStaffUser,
            )) {
                throw ValidationException::withMessages([
                    $attributes['is_active'] ? 'role' : 'is_active' => 'The last active Administrator cannot be deactivated or assigned another role.',
                ]);
            }

            $this->ensureActiveWorkflowOwnershipRemainsOperable(
                $lockedStaffUser,
                $targetRole,
                $attributes['is_active'],
            );

            $beforeValues = [];
            $afterValues = [];

            if ($lockedStaffUser->name !== $attributes['name']) {
                $beforeValues['name'] = $lockedStaffUser->name;
                $afterValues['name'] = $attributes['name'];
            }

            if ($lockedStaffUser->email !== $attributes['email']) {
                $beforeValues['email'] = $lockedStaffUser->email;
                $afterValues['email'] = $attributes['email'];
            }

            if ($currentRole->isNot($targetRole)) {
                $beforeValues['role'] = $this->roleValues($currentRole);
                $afterValues['role'] = $this->roleValues($targetRole);
            }

            if ($lockedStaffUser->is_active !== $attributes['is_active']) {
                $beforeValues['is_active'] = $lockedStaffUser->is_active;
                $afterValues['is_active'] = $attributes['is_active'];
            }

            $lockedStaffUser->role_id = $targetRole->id;
            $lockedStaffUser->is_active = $attributes['is_active'];
            $lockedStaffUser->name = $attributes['name'];
            $lockedStaffUser->email = $attributes['email'];
            $lockedStaffUser->save();

            if ($beforeValues !== []) {
                $this->recordAuditLog->handle(
                    actor: $actor,
                    action: AuditAction::StaffUpdated,
                    subject: $lockedStaffUser,
                    beforeValues: $beforeValues,
                    afterValues: $afterValues,
                );
            }

            return $lockedStaffUser->refresh();
        }, attempts: 3);
    }

    private function ensureActiveWorkflowOwnershipRemainsOperable(
        User $staffUser,
        Role $targetRole,
        bool $willRemainActive,
    ): void {
        $validationKey = $willRemainActive ? 'role' : 'is_active';
        $willRemainDoctor = $willRemainActive && $targetRole->slug === StaffRole::Doctor->value;

        if (! $willRemainDoctor && Consultation::query()
            ->whereBelongsTo($staffUser, 'doctor')
            ->where('status', ConsultationStatus::InProgress)
            ->lockForUpdate()
            ->first() instanceof Consultation) {
            throw ValidationException::withMessages([
                $validationKey => 'This staff member is responsible for an in-progress Consultation and must remain an active Doctor.',
            ]);
        }

        $willRemainNurse = $willRemainActive && $targetRole->slug === StaffRole::Nurse->value;

        if (! $willRemainNurse && PreProcedureReadiness::query()
            ->whereBelongsTo($staffUser, 'nurse')
            ->where('status', PreProcedureReadinessStatus::InPreparation)
            ->lockForUpdate()
            ->first() instanceof PreProcedureReadiness) {
            throw ValidationException::withMessages([
                $validationKey => 'This staff member owns an in-progress pre-procedure preparation and must remain an active Nurse.',
            ]);
        }

        if (! $willRemainNurse && RecoveryEpisode::query()
            ->whereBelongsTo($staffUser, 'nurse')
            ->whereIn('status', [
                RecoveryEpisodeStatus::InProgress,
                RecoveryEpisodeStatus::ReadyForDischarge,
            ])
            ->lockForUpdate()
            ->first() instanceof RecoveryEpisode) {
            throw ValidationException::withMessages([
                $validationKey => 'This staff member owns an active recovery episode and must remain an active Nurse.',
            ]);
        }
    }

    private function anotherActiveAdministratorExists(Role $administratorRole, User $staffUser): bool
    {
        return User::query()
            ->whereBelongsTo($administratorRole)
            ->whereKeyNot($staffUser->getKey())
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array{slug: string, name: string}
     */
    private function roleValues(Role $role): array
    {
        return [
            'slug' => $role->slug,
            'name' => $role->name,
        ];
    }
}
