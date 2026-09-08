<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartRecoveryEpisode
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, ProcedureRecord $procedureRecord): RecoveryEpisode
    {
        Gate::forUser($actor)->authorize('create', RecoveryEpisode::class);

        return DB::transaction(function () use ($actor, $procedureRecord): RecoveryEpisode {
            $lockedProcedureRecord = ProcedureRecord::query()
                ->lockForUpdate()
                ->find($procedureRecord->getKey());

            if (! $lockedProcedureRecord instanceof ProcedureRecord
                || $lockedProcedureRecord->getRawOriginal('status') !== ProcedureRecordStatus::Completed->value) {
                throw ValidationException::withMessages([
                    'procedure' => 'Recovery requires a completed Procedure Record.',
                ]);
            }

            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($lockedProcedureRecord->visit_id);

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value) {
                throw ValidationException::withMessages([
                    'procedure' => 'Recovery requires the completed procedure\'s checked-in Visit.',
                ]);
            }

            $existingRecovery = RecoveryEpisode::query()
                ->where(function (Builder $query) use ($lockedProcedureRecord, $lockedVisit): void {
                    $query->where('procedure_record_id', $lockedProcedureRecord->getKey())
                        ->orWhere('visit_id', $lockedVisit->getKey());
                })
                ->lockForUpdate()
                ->first();

            if ($existingRecovery instanceof RecoveryEpisode) {
                throw ValidationException::withMessages([
                    'procedure' => 'Recovery has already started for this Procedure Record.',
                ]);
            }

            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Nurse->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User) {
                throw ValidationException::withMessages([
                    'actor' => 'Starting recovery requires an active Nurse.',
                ]);
            }

            $recoveryEpisode = RecoveryEpisode::startFromNursingWorkflow(
                $lockedProcedureRecord,
                $lockedVisit,
                $lockedActor,
            );

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::RecoveryStarted,
                subject: $recoveryEpisode,
                afterValues: [
                    'recovery_number' => $recoveryEpisode->recovery_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'visit_number' => $lockedVisit->visit_number,
                    'procedure_record_id' => $lockedProcedureRecord->getKey(),
                    'procedure_number' => $lockedProcedureRecord->procedure_number,
                    'nurse_user_id' => $lockedActor->getKey(),
                    'status' => $recoveryEpisode->status->value,
                ],
            );

            return $recoveryEpisode->refresh();
        }, attempts: 3);
    }
}
