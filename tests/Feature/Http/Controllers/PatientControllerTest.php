<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\PatientSex;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows a Receptionist to access every delivered Patient page', function () {
    $receptionist = patientRegistryReceptionist();
    $patient = Patient::factory()->create();

    $this->actingAs($receptionist)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/index'));
    $this->actingAs($receptionist)
        ->get(route('patients.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/create'));
    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/show'));
    $this->actingAs($receptionist)
        ->get(route('patients.edit', $patient))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('patients/edit'));
});

it('forbids every non-Receptionist role from direct Patient registry URLs', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $patient = Patient::factory()->withoutOptionalDemographics()->create();

    $this->actingAs($actor)->get(route('patients.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.create'))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.show', $patient))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.edit', $patient))->assertForbidden();
    $this->actingAs($actor)
        ->post(route('patients.possible-duplicates'), duplicateCheckPayload())
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('patients.store'), validPatientHttpPayload())
        ->assertForbidden();
    $this->actingAs($actor)
        ->put(route('patients.update', $patient), validPatientHttpPayload())
        ->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('redirects guests away from every Patient registry endpoint', function () {
    $patient = Patient::factory()->withoutOptionalDemographics()->create();

    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->get(route('patients.create'))->assertRedirect(route('login'));
    $this->get(route('patients.show', $patient))->assertRedirect(route('login'));
    $this->get(route('patients.edit', $patient))->assertRedirect(route('login'));
    $this->post(route('patients.possible-duplicates'), duplicateCheckPayload())
        ->assertRedirect(route('login'));
    $this->post(route('patients.store'), validPatientHttpPayload())
        ->assertRedirect(route('login'));
    $this->put(route('patients.update', $patient), validPatientHttpPayload())
        ->assertRedirect(route('login'));
});

it('renders a deterministic paginated Patient registry and preserves search state', function () {
    $receptionist = patientRegistryReceptionist();
    Patient::factory()->count(16)->create([
        'first_name' => 'Registry',
        'last_name' => 'Patient',
    ]);

    $this->actingAs($receptionist)
        ->get(route('patients.index', ['q' => ' Registry ', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/index')
            ->where('filters.q', 'Registry')
            ->has('patients.data', 1)
            ->where('patients.pagination.currentPage', 2)
            ->where('patients.pagination.total', 16)
            ->where('patients.pagination.lastPage', 2)
        );
});

it('searches by exact Patient reference, partial names, and partial phone', function () {
    $receptionist = patientRegistryReceptionist();
    $target = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
        'phone' => '+254700123456',
    ]);
    Patient::factory()->create([
        'first_name' => 'Different',
        'middle_name' => null,
        'last_name' => 'Person',
        'phone' => '+254711999999',
    ]);

    foreach ([$target->patient_number, 'Ami', 'Wanj', 'Kam', '+254700'] as $search) {
        $this->actingAs($receptionist)
            ->get(route('patients.index', ['q' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('patients.data', 1)
                ->where('patients.data.0.id', $target->id)
                ->where('filters.q', $search)
            );
    }
});

it('treats search wildcard characters as literal input', function () {
    $receptionist = patientRegistryReceptionist();
    Patient::factory()->create(['first_name' => 'Amina']);

    $this->actingAs($receptionist)
        ->get(route('patients.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('patients.data', 0)
            ->where('filters.q', '%')
        );
});

it('renders a useful empty Patient registry result payload', function () {
    $receptionist = patientRegistryReceptionist();

    $this->actingAs($receptionist)
        ->get(route('patients.index', ['q' => 'NoSuchPatient']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('patients.data', 0)
            ->where('patients.pagination.total', 0)
            ->where('filters.q', 'NoSuchPatient')
        );
});

it('registers a Patient through HTTP and redirects to the generated administrative profile', function () {
    $receptionist = patientRegistryReceptionist();

    $response = $this->actingAs($receptionist)
        ->post(route('patients.store'), validPatientHttpPayload([
            'first_name' => '  Amina  ',
            'phone' => '+254 700-123-456',
            'email' => ' AMINA@EXAMPLE.COM ',
        ]));

    $patient = Patient::query()->sole();
    $response
        ->assertRedirect(route('patients.show', $patient))
        ->assertSessionHas('status', "Patient {$patient->patient_number} was registered.");
    expect($patient->patient_number)->toMatch('/^PAT-\d{6,}$/')
        ->and($patient->first_name)->toBe('Amina')
        ->and($patient->phone)->toBe('+254700123456')
        ->and($patient->email)->toBe('amina@example.com');
    expect(AuditLog::query()->sole()->action)->toBe(AuditAction::PatientRegistered);

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('patient.patientNumber', $patient->patient_number)
            ->where('status', "Patient {$patient->patient_number} was registered.")
        );
});

it('rejects identifiers and invalid demographics supplied through Patient HTTP input', function (array $payload, string $errorKey) {
    $receptionist = patientRegistryReceptionist();

    $this->actingAs($receptionist)
        ->post(route('patients.store'), validPatientHttpPayload($payload))
        ->assertSessionHasErrors($errorKey);

    expect(Patient::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'Patient reference' => [['patient_number' => 'PAT-999999'], 'patient_number'],
    'primary key' => [['id' => 999999], 'id'],
    'future date of birth' => [['date_of_birth' => '2999-01-01'], 'date_of_birth'],
    'invalid sex' => [['sex' => 'invalid'], 'sex'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
]);

it('keeps phone and email non-unique during HTTP registration', function () {
    $receptionist = patientRegistryReceptionist();
    $shared = [
        'phone' => '+254700111222',
        'email' => 'shared.patient@example.com',
    ];

    $this->actingAs($receptionist)
        ->post(route('patients.store'), validPatientHttpPayload($shared))
        ->assertRedirect();
    $this->actingAs($receptionist)
        ->post(route('patients.store'), validPatientHttpPayload([
            ...$shared,
            'first_name' => 'Second',
        ]))
        ->assertRedirect();

    expect(Patient::query()->where($shared)->count())->toBe(2);
});

it('surfaces deterministic possible matches by phone email or name and date of birth', function () {
    $receptionist = patientRegistryReceptionist();
    $phoneMatch = Patient::factory()->create([
        'phone' => '+254700123456',
        'email' => 'other-phone@example.com',
    ]);
    $emailMatch = Patient::factory()->create([
        'phone' => '+254711111111',
        'email' => 'match@example.com',
    ]);
    $nameAndDobMatch = Patient::factory()->create([
        'first_name' => 'Amina',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'phone' => null,
        'email' => null,
    ]);

    $response = $this->actingAs($receptionist)
        ->postJson(route('patients.possible-duplicates'), [
            'first_name' => ' amina ',
            'last_name' => ' KAMAU ',
            'date_of_birth' => '1990-04-12',
            'phone' => '+254 700-123-456',
            'email' => ' MATCH@EXAMPLE.COM ',
        ])
        ->assertOk()
        ->assertJsonCount(3, 'matches');

    expect(collect($response->json('matches'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$phoneMatch->id, $emailMatch->id, $nameAndDobMatch->id])->sort()->values()->all());
});

it('does not block or merge a legitimate duplicate-like Patient', function () {
    $receptionist = patientRegistryReceptionist();
    Patient::factory()->create([
        'first_name' => 'Amina',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'phone' => '+254700123456',
        'email' => 'shared@example.com',
    ]);

    $this->actingAs($receptionist)
        ->postJson(route('patients.possible-duplicates'), duplicateCheckPayload())
        ->assertOk()
        ->assertJsonCount(1, 'matches');
    $this->actingAs($receptionist)
        ->post(route('patients.store'), validPatientHttpPayload())
        ->assertRedirect();

    expect(Patient::query()->count())->toBe(2)
        ->and(Patient::query()->distinct()->count('patient_number'))->toBe(2);
});

it('shows only the current administrative Patient data', function () {
    $receptionist = patientRegistryReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
    ]);

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('patient.id', $patient->id)
            ->where('patient.patientNumber', $patient->patient_number)
            ->where('patient.name', 'Amina Wanjiku Kamau')
            ->missing('patient.visits')
            ->missing('patient.charges')
            ->missing('patient.payments')
            ->missing('patient.clinical')
        );
});

it('updates normalized Patient demographics through HTTP and audits meaningful changes', function () {
    $receptionist = patientRegistryReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female,
        'phone' => '+254700000001',
        'email' => 'old@example.com',
        'address' => 'Nairobi',
    ]);
    $patientNumber = $patient->patient_number;

    $this->actingAs($receptionist)
        ->put(route('patients.update', $patient), validPatientHttpPayload([
            'middle_name' => ' Wanjiku ',
            'phone' => '+254 700-000-002',
            'email' => ' NEW@EXAMPLE.COM ',
        ]))
        ->assertRedirect(route('patients.show', $patient));

    $patient->refresh();
    expect($patient->patient_number)->toBe($patientNumber)
        ->and($patient->middle_name)->toBe('Wanjiku')
        ->and($patient->phone)->toBe('+254700000002')
        ->and($patient->email)->toBe('new@example.com');
    expect(AuditLog::query()->sole()->action)->toBe(AuditAction::PatientUpdated);
});

it('does not audit a no-op Patient HTTP update', function () {
    $receptionist = patientRegistryReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female,
        'phone' => '+254700123456',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
    ]);

    $this->actingAs($receptionist)
        ->put(route('patients.update', $patient), validPatientHttpPayload())
        ->assertRedirect(route('patients.show', $patient));

    expect(AuditLog::query()->count())->toBe(0);
});

it('exposes no Patient deletion route', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('patients.destroy'))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => in_array('DELETE', $route->methods(), true)
                && str_starts_with($route->uri(), 'patients'),
        ))->toBeFalse();
});

function patientRegistryReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validPatientHttpPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female->value,
        'phone' => '+254700123456',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
        ...$overrides,
    ];
}

/**
 * @return array<string, string>
 */
function duplicateCheckPayload(): array
{
    return [
        'first_name' => 'Amina',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'phone' => '+254700123456',
        'email' => 'shared@example.com',
    ];
}
