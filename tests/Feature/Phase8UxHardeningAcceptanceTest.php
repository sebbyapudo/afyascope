<?php

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('projects only the operational dashboard contract for each staff role', function (
    StaffRole $role,
    string $kind,
    array $expectedLabels,
) {
    $actor = User::factory()->forRole($role)->create();

    $response = $this->actingAs($actor)->get(route('dashboard'));
    $dashboard = $response->inertiaProps('dashboard');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.kind', $kind)
        ->where('auth.role.slug', $role->value)
    );

    expect(array_keys($dashboard))->toBe([
        'kind',
        'eyebrow',
        'title',
        'description',
        'emptyMessage',
        'period',
        'metrics',
    ])->and(collect($dashboard['metrics'])->pluck('label')->all())->toBe($expectedLabels);

    $encodedDashboard = json_encode($dashboard, JSON_THROW_ON_ERROR);

    expect($encodedDashboard)
        ->not->toContain('patientNumber')
        ->not->toContain('visitNumber')
        ->not->toContain('amountMinor')
        ->not->toContain('clinical_rationale')
        ->not->toContain('presenting_complaint')
        ->not->toContain('metadata')
        ->not->toContain('password')
        ->not->toContain('remember_token');
})->with([
    'Receptionist' => [
        StaffRole::Receptionist,
        'receptionist',
        [
            'Scheduled appointments awaiting attendance',
            'Scheduled for today',
            'Visits awaiting consultation billing',
            'Ready for Reception check-in',
            'Checked-in active Visits',
        ],
    ],
    'Accountant' => [
        StaffRole::Accountant,
        'accountant',
        [
            'Visits awaiting consultation Bill',
            'Procedure decisions awaiting Bill',
            'Consultation Bills awaiting payment',
            'Procedure Bills awaiting payment',
            'Consultation Bills awaiting clearance',
            'Procedure Bills awaiting clearance',
            'Payments recorded today',
            'Clearances granted today',
        ],
    ],
    'Doctor' => [
        StaffRole::Doctor,
        'doctor',
        [
            'Visits ready for consultation',
            'My consultations in progress',
            'My consultations awaiting decision',
            'My procedures ready to start',
            'My procedures in progress',
            'Recovery cases awaiting review',
        ],
    ],
    'Nurse' => [
        StaffRole::Nurse,
        'nurse',
        [
            'Awaiting Nursing preparation',
            'My active preparations',
            'Prepared and ready for procedure',
            'Awaiting Nursing recovery',
            'My recoveries in progress',
            'My Doctor-review cases',
            'My ready-for-discharge cases',
        ],
    ],
    'Administrator' => [
        StaffRole::Administrator,
        'administrator',
        [
            'Active staff accounts',
            'Disabled staff accounts',
            'Active catalog services',
            'Inactive catalog services',
        ],
    ],
    'Management' => [
        StaffRole::Management,
        'management',
        [
            'Visits this month',
            'Completed Visits this month',
            'Bills created this month',
            'Payments recorded this month',
            'Procedures completed this month',
            'Discharges completed this month',
        ],
    ],
]);

it('derives Reception dashboard counts from current scheduling and Visit state', function () {
    $this->travelTo('2026-09-13 10:00:00');
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    Appointment::factory()->create(['scheduled_at' => '2026-09-13 14:00:00']);
    Appointment::factory()->create(['scheduled_at' => '2026-09-14 09:00:00']);
    Visit::factory()->create();

    $metrics = phase8DashboardMetrics(
        $this->actingAs($receptionist)->get(route('dashboard'))->inertiaProps('dashboard.metrics'),
    );

    expect($metrics['Scheduled appointments awaiting attendance'])->toBe(2)
        ->and($metrics['Scheduled for today'])->toBe(1)
        ->and($metrics['Visits awaiting consultation billing'])->toBe(1)
        ->and($metrics['Ready for Reception check-in'])->toBe(0)
        ->and($metrics['Checked-in active Visits'])->toBe(0);
});

it('derives Accountant dashboard counts from both financial gates', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    Visit::factory()->create();
    Bill::factory()->create();
    ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    $metrics = phase8DashboardMetrics(
        $this->actingAs($accountant)->get(route('dashboard'))->inertiaProps('dashboard.metrics'),
    );

    expect($metrics['Visits awaiting consultation Bill'])->toBe(1)
        ->and($metrics['Procedure decisions awaiting Bill'])->toBe(1)
        ->and($metrics['Consultation Bills awaiting payment'])->toBe(1)
        ->and($metrics['Procedure Bills awaiting payment'])->toBe(0)
        ->and($metrics['Consultation Bills awaiting clearance'])->toBe(0)
        ->and($metrics['Procedure Bills awaiting clearance'])->toBe(0);
});

it('keeps Doctor workload ownership scoped while retaining the shared ready queue', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    VisitCheckIn::factory()->create();
    Consultation::factory()->for($doctor, 'doctor')->create();
    Consultation::factory()->for($otherDoctor, 'doctor')->create();

    $metrics = phase8DashboardMetrics(
        $this->actingAs($doctor)->get(route('dashboard'))->inertiaProps('dashboard.metrics'),
    );

    expect($metrics['Visits ready for consultation'])->toBe(1)
        ->and($metrics['My consultations in progress'])->toBe(1)
        ->and($metrics['My consultations awaiting decision'])->toBe(1)
        ->and($metrics['My procedures ready to start'])->toBe(0)
        ->and($metrics['My procedures in progress'])->toBe(0);
});

it('derives Administrator counts without granting operational workflow data', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    User::factory()->forRole(StaffRole::Receptionist)->inactive()->create();
    ServiceCatalogItem::factory()->create();
    ServiceCatalogItem::factory()->create(['is_active' => false]);

    $dashboard = $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->inertiaProps('dashboard');
    $metrics = phase8DashboardMetrics($dashboard['metrics']);

    expect($metrics)->toBe([
        'Active staff accounts' => 1,
        'Disabled staff accounts' => 1,
        'Active catalog services' => 1,
        'Inactive catalog services' => 1,
    ])->and(json_encode($dashboard, JSON_THROW_ON_ERROR))
        ->not->toContain('awaiting payment')
        ->not->toContain('consultations in progress')
        ->not->toContain('recoveries in progress');
});

it('reuses the current-month Management aggregates without copying report detail', function () {
    $this->travelTo('2026-09-13 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();
    Visit::factory()->create();

    $dashboard = $this->actingAs($management)
        ->get(route('dashboard'))
        ->inertiaProps('dashboard');
    $metrics = phase8DashboardMetrics($dashboard['metrics']);

    expect($dashboard['period'])->toBe([
        'fromDate' => '2026-09-01',
        'throughDate' => '2026-09-13',
    ])->and($metrics['Visits this month'])->toBe(1)
        ->and($metrics['Bills created this month'])->toBe(0)
        ->and($dashboard)->not->toHaveKeys(['stages', 'procedureDistribution', 'financial']);
});

it('redirects guests and inactive staff before dashboard data is built', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $inactive = User::factory()->forRole(StaffRole::Receptionist)->inactive()->create();

    $this->actingAs($inactive)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps invalid workflow submissions user-facing and destructive resources non-deletable', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = Visit::factory()->create();

    $this->actingAs($receptionist)
        ->from(route('visits.show', $visit))
        ->post(route('check-ins.store', $visit))
        ->assertRedirect(route('visits.show', $visit))
        ->assertSessionHasErrors([
            'visit' => 'Check-in requires a fully paid consultation Bill.',
        ]);

    expect(Route::has('staff.destroy'))->toBeFalse()
        ->and(Route::has('service-catalog.destroy'))->toBeFalse();
});

/**
 * @param  list<array{label: string, value: int, description: string}>  $metrics
 * @return array<string, int>
 */
function phase8DashboardMetrics(array $metrics): array
{
    return collect($metrics)->pluck('value', 'label')->all();
}
