<?php

namespace App\Actions\Visits;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CheckInVisit
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Visit $visit): VisitCheckIn
    {
        Gate::forUser($actor)->authorize('create', VisitCheckIn::class);

        return DB::transaction(function () use ($actor, $visit): VisitCheckIn {
            $lockedBill = Bill::query()
                ->where('visit_id', $visit->getKey())
                ->where('type', BillType::Consultation->value)
                ->lockForUpdate()
                ->first();

            if (! $lockedBill instanceof Bill || $lockedBill->status !== BillStatus::Paid) {
                throw ValidationException::withMessages([
                    'visit' => 'Check-in requires a fully paid consultation Bill.',
                ]);
            }

            $billItems = BillItem::query()
                ->where('bill_id', $lockedBill->getKey())
                ->lockForUpdate()
                ->get();
            $lockedBill->setRelation('items', $billItems);
            $payment = Payment::query()
                ->where('bill_id', $lockedBill->getKey())
                ->lockForUpdate()
                ->first();
            $receipt = $payment instanceof Payment
                ? Receipt::query()->where('payment_id', $payment->getKey())->lockForUpdate()->first()
                : null;
            $financialClearance = FinancialClearance::query()
                ->where('bill_id', $lockedBill->getKey())
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof Payment
                || ! $receipt instanceof Receipt
                || ! $financialClearance instanceof FinancialClearance
                || $payment->amount_minor <= 0
                || $payment->amount_minor !== $lockedBill->totalAmountMinor()) {
                throw ValidationException::withMessages([
                    'visit' => 'Check-in requires the exact successful Payment, Receipt, and consultation financial clearance.',
                ]);
            }

            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($visit->getKey());

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::Created->value) {
                throw ValidationException::withMessages([
                    'visit' => 'Only a created Visit awaiting Reception check-in can be checked in.',
                ]);
            }

            if ($lockedVisit->checkIn()->exists()) {
                throw ValidationException::withMessages([
                    'visit' => 'This Visit has already been checked in.',
                ]);
            }

            $visitCheckIn = new VisitCheckIn;
            $visitCheckIn->visit()->associate($lockedVisit);
            $visitCheckIn->checkedInBy()->associate($actor);
            $visitCheckIn->save();

            $lockedVisit->status = VisitStatus::CheckedIn;
            $lockedVisit->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::VisitCheckedIn,
                subject: $visitCheckIn,
                afterValues: [
                    'check_in_number' => $visitCheckIn->check_in_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'visit_number' => $lockedVisit->visit_number,
                    'clearance_id' => $financialClearance->getKey(),
                    'clearance_number' => $financialClearance->clearance_number,
                ],
            );

            return $visitCheckIn->load([
                'visit:id,patient_id,appointment_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
                'visit.consultationBill:id,visit_id,bill_number,type,status',
                'visit.consultationBill.financialClearance:id,bill_id,clearance_number,granted_at',
                'checkedInBy:id,name',
            ]);
        }, attempts: 3);
    }
}
