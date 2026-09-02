<?php

use App\Actions\Visits\CheckInVisit;
use App\AppointmentStatus;
use App\AuditAction;
use App\BillStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

it('queues only cleared unchecked Visits in deterministic order', function () {
    $receptionist = checkInControllerReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    [$oldest, $oldestClearance] = checkInControllerEligibleVisit($patient, [
        'occurred_at' => '2026-09-01 08:00:00',
    ]);
    [$newest] = checkInControllerEligibleVisit($patient, [
        'occurred_at' => '2026-09-01 10:00:00',
    ]);
    [$checkedIn] = checkInControllerEligibleVisit($patient, [
        'occurred_at' => '2026-09-01 07:00:00',
    ]);
    app(CheckInVisit::class)->handle($receptionist, $checkedIn);
    Visit::factory()->for($patient)->create(['occurred_at' => '2026-09-01 06:00:00']);
    checkInControllerBillWithItem(
        Visit::factory()->for($patient)->create(['occurred_at' => '2026-09-01 09:00:00']),
    );

    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('check-ins/index')
            ->where('visits.data', fn ($visits): bool => collect($visits)->pluck('id')->all() === [
                $oldest->id,
                $newest->id,
            ])
            ->where('visits.pagination.total', 2)
            ->where('visits.data.0.patient', [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => 'Amina Kamau',
            ])
            ->where('visits.data.0.clearance.clearanceNumber', $oldestClearance->clearance_number)
            ->where('visits.data.0.nextStep', 'Awaiting Reception check-in')
            ->where('auth.capabilities.viewCheckIns', true)
            ->where('auth.capabilities.createCheckIns', true)
            ->missing('visits.data.0.bill')
            ->missing('visits.data.0.payment')
            ->missing('visits.data.0.receipt')
            ->missing('visits.data.0.auditLogs')
            ->missing('visits.data.0.clinical')
        );
});

it('shows a sanitized confirmation context for an eligible Visit', function () {
    $receptionist = checkInControllerReceptionist();
    [$visit, $financialClearance] = checkInControllerEligibleVisit();

    $this->actingAs($receptionist)
        ->get(route('check-ins.create', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('check-ins/create')
            ->where('visit.id', $visit->id)
            ->where('visit.visitNumber', $visit->visit_number)
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.nextStep', 'Awaiting Reception check-in')
            ->where('visit.clearance.clearanceNumber', $financialClearance->clearance_number)
            ->missing('visit.bill')
            ->missing('visit.payment')
            ->missing('visit.receipt')
            ->missing('visit.auditLogs')
            ->missing('visit.consultation')
        );
});

it('checks in an eligible Visit and redirects to its immutable detail', function () {
    $receptionist = checkInControllerReceptionist();
    [$visit] = checkInControllerEligibleVisit();

    $response = $this->actingAs($receptionist)
        ->post(route('check-ins.store', $visit));

    $visitCheckIn = VisitCheckIn::query()->sole();

    $response
        ->assertRedirect(route('check-ins.show', $visitCheckIn))
        ->assertSessionHas('status', "Check-in {$visitCheckIn->check_in_number} was completed.");

    expect($visitCheckIn->checkedInBy->is($receptionist))->toBeTrue()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor consultation')
        ->and(AuditLog::query()->where('action', AuditAction::VisitCheckedIn)->count())->toBe(1);
});

it('rejects client-controlled check-in and workflow fields', function () {
    $receptionist = checkInControllerReceptionist();
    [$visit] = checkInControllerEligibleVisit();
    $payload = [
        'id' => 999_999,
        'visit_id' => 999_999,
        'patient_id' => 999_999,
        'bill_id' => 999_999,
        'payment_id' => 999_999,
        'receipt_id' => 999_999,
        'financial_clearance_id' => 999_999,
        'check_in_number' => 'CHK-SUPPLIED',
        'checked_in_by_user_id' => 999_999,
        'checked_in_at' => '2020-01-01 00:00:00',
        'status' => 'checked_in',
    ];

    $this->actingAs($receptionist)
        ->post(route('check-ins.store', $visit), $payload)
        ->assertSessionHasErrors(array_keys($payload));

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created);
});

it('rejects unbilled unpaid uncleared and duplicate check-in attempts without audit residue', function () {
    $receptionist = checkInControllerReceptionist();
    $unbilledVisit = Visit::factory()->create();
    $openVisit = Visit::factory()->create();
    checkInControllerBillWithItem($openVisit);
    $unclearedVisit = Visit::factory()->create();
    $unclearedBill = checkInControllerBillWithItem($unclearedVisit);
    $payment = Payment::factory()->for($unclearedBill)->create();
    Receipt::factory()->for($payment)->create();
    $unclearedBill->status = BillStatus::Paid;
    $unclearedBill->save();

    foreach ([$unbilledVisit, $openVisit, $unclearedVisit] as $visit) {
        $this->actingAs($receptionist)
            ->get(route('check-ins.create', $visit))
            ->assertNotFound();
        $this->actingAs($receptionist)
            ->post(route('check-ins.store', $visit))
            ->assertSessionHasErrors('visit');
    }

    [$eligibleVisit] = checkInControllerEligibleVisit();
    $this->actingAs($receptionist)
        ->post(route('check-ins.store', $eligibleVisit))
        ->assertRedirect();
    $this->actingAs($receptionist)
        ->post(route('check-ins.store', $eligibleVisit))
        ->assertSessionHasErrors('visit');

    expect(VisitCheckIn::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCheckedIn)->count())->toBe(1);
});

it('renders sanitized check-in detail and removes the Visit from the queue', function () {
    $receptionist = checkInControllerReceptionist();
    [$visit, $financialClearance] = checkInControllerEligibleVisit();
    $visitCheckIn = app(CheckInVisit::class)->handle($receptionist, $visit);

    $this->actingAs($receptionist)
        ->get(route('check-ins.show', $visitCheckIn))
        ->assertInertia(fn (Assert $page) => $page
            ->component('check-ins/show')
            ->where('checkIn.checkInNumber', $visitCheckIn->check_in_number)
            ->where('checkIn.checkedInBy', $receptionist->name)
            ->where('checkIn.visit.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('checkIn.visit.nextStep', 'Ready for Doctor consultation')
            ->where('checkIn.clearance.clearanceNumber', $financialClearance->clearance_number)
            ->where('checkIn.patient.patientNumber', $visit->patient->patient_number)
            ->missing('checkIn.checked_in_by_user_id')
            ->missing('checkIn.bill')
            ->missing('checkIn.payment')
            ->missing('checkIn.receipt')
            ->missing('checkIn.auditLogs')
            ->missing('checkIn.consultation')
        );

    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data', [])
            ->where('visits.pagination.total', 0)
        );
});

it('projects checked-in workflow consistently across Visit Patient and linked Appointment screens', function () {
    $receptionist = checkInControllerReceptionist();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
    ]);
    [$visit, $financialClearance] = checkInControllerEligibleVisit($patient, [], $visit);
    $visitCheckIn = app(CheckInVisit::class)->handle($receptionist, $visit);

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('visit.nextStep', 'Ready for Doctor consultation')
            ->where('visit.canCheckIn', false)
            ->where('visit.checkIn', [
                'id' => $visitCheckIn->id,
                'checkInNumber' => $visitCheckIn->check_in_number,
                'checkedInAt' => $visitCheckIn->checked_in_at->toIso8601String(),
            ])
            ->missing('visit.payment')
            ->missing('visit.receipt')
            ->missing('visit.consultation')
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('visitHistory.data.0.nextStep', 'Ready for Doctor consultation')
            ->missing('visitHistory.data.0.payment')
            ->missing('visitHistory.data.0.consultation')
        );

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.status', ['value' => 'scheduled', 'label' => 'Scheduled'])
            ->where('appointment.linkedVisit.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('appointment.linkedVisit.nextStep', 'Ready for Doctor consultation')
            ->missing('appointment.linkedVisit.payment')
            ->missing('appointment.linkedVisit.consultation')
        );

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($financialClearance->fresh()->bill->visit_id)->toBe($visit->id)
        ->and(Schema::hasTable('consultations'))->toBeTrue()
        ->and(Consultation::query()->count())->toBe(0);
});

it('redirects guests from every Reception check-in endpoint', function () {
    [$visit] = checkInControllerEligibleVisit();
    $visitCheckIn = VisitCheckIn::factory()->for($visit)->create();

    $this->get(route('check-ins.index'))->assertRedirect(route('login'));
    $this->get(route('check-ins.create', $visit))->assertRedirect(route('login'));
    $this->post(route('check-ins.store', $visit))->assertRedirect(route('login'));
    $this->get(route('check-ins.show', $visitCheckIn))->assertRedirect(route('login'));
});

it('denies every non-Receptionist role from direct check-in URLs', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    [$visit] = checkInControllerEligibleVisit();
    $visitCheckIn = VisitCheckIn::factory()->for($visit)->create();

    $this->actingAs($actor)->get(route('check-ins.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('check-ins.create', $visit))->assertForbidden();
    $this->actingAs($actor)->post(route('check-ins.store', $visit))->assertForbidden();
    $this->actingAs($actor)->get(route('check-ins.show', $visitCheckIn))->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out and denies an inactive Receptionist', function () {
    $inactiveReceptionist = User::factory()->forRole(StaffRole::Receptionist)->inactive()->create();
    [$visit] = checkInControllerEligibleVisit();

    $this->actingAs($inactiveReceptionist)
        ->post(route('check-ins.store', $visit))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('exposes only immutable Reception check-in routes', function () {
    expect(Route::has('check-ins.index'))->toBeTrue()
        ->and(Route::has('check-ins.create'))->toBeTrue()
        ->and(Route::has('check-ins.store'))->toBeTrue()
        ->and(Route::has('check-ins.show'))->toBeTrue()
        ->and(Route::has('check-ins.update'))->toBeFalse()
        ->and(Route::has('check-ins.destroy'))->toBeFalse();
});

function checkInControllerReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}

/**
 * @param  array<string, mixed>  $visitAttributes
 * @return array{Visit, FinancialClearance}
 */
function checkInControllerEligibleVisit(
    ?Patient $patient = null,
    array $visitAttributes = [],
    ?Visit $visit = null,
): array {
    $patient ??= Patient::factory()->create();
    $visit ??= Visit::factory()->for($patient)->create($visitAttributes);
    $bill = checkInControllerBillWithItem($visit);
    $payment = Payment::factory()->for($bill)->create();
    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();
    $financialClearance = FinancialClearance::factory()->for($bill)->create();

    return [$visit->fresh('patient'), $financialClearance];
}

function checkInControllerBillWithItem(Visit $visit): Bill
{
    $bill = Bill::factory()->for($visit)->create();
    $service = ServiceCatalogItem::factory()->create();

    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
