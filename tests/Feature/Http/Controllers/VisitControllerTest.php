<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows a Receptionist to access every delivered Visit page', function () {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    $this->actingAs($receptionist)
        ->get(route('visits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('visits/index'));
    $this->actingAs($receptionist)
        ->get(route('patients.visits.create', $patient))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('visits/create')
            ->where('patient.id', $patient->id)
            ->where('patient.patientNumber', $patient->patient_number)
        );
    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('visits/show'));
});

it('forbids every non-Receptionist role from direct Visit URLs', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    $this->actingAs($actor)->get(route('visits.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('visits.show', $visit))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.visits.create', $patient))->assertForbidden();
    $this->actingAs($actor)->post(route('patients.visits.store', $patient))->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('redirects guests away from every Visit endpoint', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    $this->get(route('visits.index'))->assertRedirect(route('login'));
    $this->get(route('visits.show', $visit))->assertRedirect(route('login'));
    $this->get(route('patients.visits.create', $patient))->assertRedirect(route('login'));
    $this->post(route('patients.visits.store', $patient))->assertRedirect(route('login'));
});

it('renders a deterministic paginated Visit registry and preserves search state', function () {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create(['first_name' => 'Registry']);
    Visit::factory()->for($patient)->count(16)->sequence(
        fn ($sequence): array => ['occurred_at' => now()->subMinutes($sequence->index)],
    )->create();

    $this->actingAs($receptionist)
        ->get(route('visits.index', ['q' => ' Registry ', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('visits/index')
            ->where('filters.q', 'Registry')
            ->has('visits.data', 1)
            ->where('visits.pagination.currentPage', 2)
            ->where('visits.pagination.total', 16)
            ->where('visits.pagination.lastPage', 2)
        );
});

it('searches by Visit reference Patient reference and partial Patient names', function () {
    $receptionist = visitManagementReceptionist();
    $targetPatient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
    ]);
    $targetVisit = Visit::factory()->for($targetPatient)->create();
    Visit::factory()->create();

    foreach ([$targetVisit->visit_number, $targetPatient->patient_number, 'Ami', 'Wanj', 'Kam'] as $search) {
        $this->actingAs($receptionist)
            ->get(route('visits.index', ['q' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $targetVisit->id)
                ->where('filters.q', $search)
            );
    }
});

it('treats Visit search wildcard characters as literal input', function () {
    $receptionist = visitManagementReceptionist();
    Visit::factory()->create();

    $this->actingAs($receptionist)
        ->get(route('visits.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 0)
            ->where('filters.q', '%')
        );
});

it('renders a useful empty Visit registry result payload', function () {
    $receptionist = visitManagementReceptionist();

    $this->actingAs($receptionist)
        ->get(route('visits.index', ['q' => 'NoSuchVisit']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 0)
            ->where('visits.pagination.total', 0)
            ->where('filters.q', 'NoSuchVisit')
        );
});

it('creates a Visit for an existing Patient with server-controlled values and an audit event', function () {
    Carbon::withTestNow('2026-08-31 09:15:00', function (): void {
        $receptionist = visitManagementReceptionist();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($receptionist)
            ->post(route('patients.visits.store', $patient));

        $visit = Visit::query()->sole();
        $response
            ->assertRedirect(route('visits.show', $visit))
            ->assertSessionHas('status', "Visit {$visit->visit_number} was created.");
        expect($visit->patient->is($patient))->toBeTrue()
            ->and($visit->visit_number)->toMatch('/^VIS-\d{6,}$/')
            ->and($visit->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-31 09:15:00')
            ->and($visit->status)->toBe(VisitStatus::Created);
        expect(AuditLog::query()->sole()->action)->toBe(AuditAction::VisitCreated);
    });
});

it('creates another Visit for a returning Patient without duplicating the Patient', function () {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create();

    $this->actingAs($receptionist)->post(route('patients.visits.store', $patient))->assertRedirect();
    $this->actingAs($receptionist)->post(route('patients.visits.store', $patient))->assertRedirect();

    expect(Patient::query()->count())->toBe(1)
        ->and(Visit::query()->count())->toBe(2)
        ->and(Visit::query()->distinct()->count('visit_number'))->toBe(2)
        ->and($patient->visits()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCreated)->count())->toBe(2);
});

it('rejects controlled values supplied through Visit HTTP input', function (string $key, mixed $value) {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create();

    $this->actingAs($receptionist)
        ->post(route('patients.visits.store', $patient), [$key => $value])
        ->assertSessionHasErrors($key);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'primary key' => ['id', 999999],
    'Patient foreign key' => ['patient_id', 999999],
    'Visit reference' => ['visit_number', 'VIS-999999'],
    'occurrence time' => ['occurred_at', '2020-01-01 00:00:00'],
    'status' => ['status', 'financially-cleared'],
]);

it('shows only administrative Visit data and the approved next handoff label', function () {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
    ]);
    $visit = Visit::factory()->for($patient)->create();

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.id', $visit->id)
            ->where('visit.visitNumber', $visit->visit_number)
            ->where('visit.patient.name', 'Amina Wanjiku Kamau')
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.nextStep', 'Awaiting consultation billing')
            ->missing('visit.charges')
            ->missing('visit.payments')
            ->missing('visit.clearance')
            ->missing('visit.clinical')
            ->missing('visit.procedures')
            ->missing('visit.nursing')
        );
});

it('integrates Patient Visits into the profile in deterministic newest-first order', function () {
    $receptionist = visitManagementReceptionist();
    $patient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();
    $visits = Visit::factory()->for($patient)->count(6)->sequence(
        fn ($sequence): array => ['occurred_at' => now()->subDays($sequence->index)],
    )->create();
    Visit::factory()->for($otherPatient)->create(['occurred_at' => now()->addDay()]);
    $expectedIds = $visits->sortByDesc('occurred_at')->take(5)->pluck('id')->values()->all();

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visitHistory.data', 6)
            ->where('visitHistory.data', fn ($visitHistory): bool => collect($visitHistory)
                ->pluck('id')->take(5)->all() === $expectedIds)
            ->where('visitHistory.pagination.total', 6)
            ->missing('patient.visits')
        );
});

it('exposes no Visit update or deletion routes', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('visits.update'))->toBeFalse()
        ->and(Route::has('visits.destroy'))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => in_array('DELETE', $route->methods(), true)
                && str_starts_with($route->uri(), 'visits'),
        ))->toBeFalse();
});

function visitManagementReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}
