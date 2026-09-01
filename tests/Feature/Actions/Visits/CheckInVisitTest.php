<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Visits\CheckInVisit;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('checks in one cleared Visit and records exactly one business audit', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    [$visit, $financialClearance] = checkInActionEligibleVisit();
    $patientId = $visit->patient_id;

    expect($visit->workflowMessage())->toBe('Awaiting Reception check-in');

    $visitCheckIn = app(CheckInVisit::class)->handle($receptionist, $visit);

    expect($visitCheckIn->check_in_number)->toMatch('/^CHK-\d{6,}$/')
        ->and($visitCheckIn->visit->is($visit))->toBeTrue()
        ->and($visitCheckIn->checkedInBy->is($receptionist))->toBeTrue()
        ->and($visit->fresh()->patient_id)->toBe($patientId)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor consultation');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->action)->toBe(AuditAction::VisitCheckedIn)
        ->and($auditLog->actor->is($receptionist))->toBeTrue()
        ->and($auditLog->subject->is($visitCheckIn))->toBeTrue()
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(5)
        ->and($auditLog->after_values)->toHaveKey('check_in_number', $visitCheckIn->check_in_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey('visit_number', $visit->visit_number)
        ->and($auditLog->after_values)->toHaveKey('clearance_id', $financialClearance->id)
        ->and($auditLog->after_values)->toHaveKey('clearance_number', $financialClearance->clearance_number);
});

it('rejects duplicate check-in without another record or audit', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    [$visit] = checkInActionEligibleVisit();

    app(CheckInVisit::class)->handle($receptionist, $visit);

    expect(fn () => app(CheckInVisit::class)->handle(
        $receptionist,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(VisitCheckIn::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(1)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn);
});

it('rejects unbilled open and paid but uncleared Visits without residue', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $unbilledVisit = Visit::factory()->create();
    $openBill = checkInActionBillWithItem();
    $openVisit = $openBill->visit;
    $unclearedBill = checkInActionBillWithItem();
    $payment = Payment::factory()->for($unclearedBill)->create();
    Receipt::factory()->for($payment)->create();
    $unclearedBill->status = BillStatus::Paid;
    $unclearedBill->save();

    foreach ([$unbilledVisit, $openVisit, $unclearedBill->visit] as $visit) {
        expect(fn () => app(CheckInVisit::class)->handle(
            $receptionist,
            $visit,
        ))->toThrow(ValidationException::class);
    }

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects procedure-related forged financial state without residue', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    [$visit, $financialClearance] = checkInActionEligibleVisit();

    DB::table('bills')->where('id', $financialClearance->bill_id)->update([
        'type' => BillType::Procedure->value,
    ]);

    expect(fn () => app(CheckInVisit::class)->handle(
        $receptionist,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces Receptionist authorization at the action boundary', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    [$visit] = checkInActionEligibleVisit();

    expect(fn () => app(CheckInVisit::class)->handle(
        $accountant,
        $visit,
    ))->toThrow(AuthorizationException::class);

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back the check-in and Visit status when audit recording fails', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    [$visit] = checkInActionEligibleVisit();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Check-in audit failed.'));

    expect(fn () => (new CheckInVisit($recordAuditLog))->handle(
        $receptionist,
        $visit,
    ))->toThrow(RuntimeException::class, 'Check-in audit failed.');

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting Reception check-in');
});

/**
 * @return array{Visit, FinancialClearance}
 */
function checkInActionEligibleVisit(): array
{
    $financialClearance = FinancialClearance::factory()->create();

    return [$financialClearance->bill->visit, $financialClearance];
}

function checkInActionBillWithItem(): Bill
{
    $bill = Bill::factory()->create();
    $service = ServiceCatalogItem::factory()->create();

    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill->fresh('visit');
}
