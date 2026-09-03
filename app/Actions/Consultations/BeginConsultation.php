<?php

namespace App\Actions\Consultations;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\Consultation;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BeginConsultation
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Visit $visit): Consultation
    {
        Gate::forUser($actor)->authorize('create', Consultation::class);

        return DB::transaction(function () use ($actor, $visit): Consultation {
            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($visit->getKey());

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value) {
                throw ValidationException::withMessages([
                    'visit' => 'Only a checked-in Visit can begin consultation.',
                ]);
            }

            $visitCheckIn = VisitCheckIn::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if (! $visitCheckIn instanceof VisitCheckIn) {
                throw ValidationException::withMessages([
                    'visit' => 'Consultation requires a valid Reception check-in.',
                ]);
            }

            $existingConsultation = Consultation::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingConsultation instanceof Consultation) {
                throw ValidationException::withMessages([
                    'visit' => 'Consultation has already started for this Visit.',
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

            if (! $lockedActor instanceof User) {
                throw ValidationException::withMessages([
                    'actor' => 'Consultation requires an active Doctor.',
                ]);
            }

            $consultation = new Consultation;
            $consultation->visit()->associate($lockedVisit);
            $consultation->doctor()->associate($lockedActor);
            $consultation->save();

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ConsultationStarted,
                subject: $consultation,
                afterValues: [
                    'consultation_number' => $consultation->consultation_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'visit_number' => $lockedVisit->visit_number,
                    'doctor_user_id' => $lockedActor->getKey(),
                ],
            );

            return $consultation->load([
                'doctor:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
                'visit.checkIn:id,visit_id,check_in_number,checked_in_at',
            ]);
        }, attempts: 3);
    }
}
