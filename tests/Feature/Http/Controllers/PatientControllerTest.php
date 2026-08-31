<?php

use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\PatientSex;
use App\StaffRole;
use Illuminate\Support\Carbon;
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/show')
            ->where('auth.capabilities.updatePatients', true)
            ->where('auth.capabilities.createVisits', true)
            ->where('auth.capabilities.createAppointments', true)
        );
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

it('shows the complete administrative Patient profile with empty histories', function () {
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
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/show')
            ->where('patient.id', $patient->id)
            ->where('patient.patientNumber', $patient->patient_number)
            ->where('patient.name', 'Amina Wanjiku Kamau')
            ->where('patient.dateOfBirth', '1990-04-12')
            ->where('patient.sex', ['value' => 'female', 'label' => 'Female'])
            ->where('patient.phone', '+254700123456')
            ->where('patient.email', 'amina@example.com')
            ->where('patient.address', 'Nairobi')
            ->where('patient.createdAt', $patient->created_at?->toIso8601String())
            ->has('visitHistory.data', 0)
            ->where('visitHistory.pagination.total', 0)
            ->has('upcomingAppointments.data', 0)
            ->where('upcomingAppointments.pagination.total', 0)
            ->has('pastUnresolvedAppointments.data', 0)
            ->where('pastUnresolvedAppointments.pagination.total', 0)
            ->has('appointmentHistory.data', 0)
            ->where('appointmentHistory.pagination.total', 0)
            ->missing('patient.visits')
            ->missing('patient.charges')
            ->missing('patient.payments')
            ->missing('patient.clinical')
            ->missing('auditLogs')
            ->missing('billing')
            ->missing('clinical')
            ->missing('procedures')
            ->missing('nursing')
        );
});

it('paginates Patient Visit history newest-first without leaking another Patient', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $receptionist = patientRegistryReceptionist();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $visits = Visit::factory()->for($patient)->count(12)->sequence(
            fn ($sequence): array => ['occurred_at' => now()->subDays($sequence->index)],
        )->create();
        $otherVisit = Visit::factory()->for($otherPatient)->create([
            'occurred_at' => now()->addDay(),
        ]);
        $newestFirstIds = $visits->sortByDesc('occurred_at')->pluck('id')->values();

        $this->actingAs($receptionist)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->has('visitHistory.data', 10)
                ->where('visitHistory.data', fn ($history): bool => collect($history)
                    ->pluck('id')->all() === $newestFirstIds->take(10)->all())
                ->where('visitHistory.data.0.nextStep', 'Awaiting consultation billing')
                ->where('visitHistory.pagination.currentPage', 1)
                ->where('visitHistory.pagination.pageName', 'visits_page')
                ->where('visitHistory.pagination.perPage', 10)
                ->where('visitHistory.pagination.total', 12)
                ->where('visitHistory.pagination.lastPage', 2)
                ->where('visitHistory.pagination.nextPageUrl', fn (?string $url): bool => $url !== null && str_contains($url, 'visits_page=2'))
                ->where('visitHistory.data', fn ($history): bool => ! collect($history)
                    ->pluck('id')->contains($otherVisit->id))
            );

        $this->actingAs($receptionist)
            ->get(route('patients.show', [$patient, 'visits_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('visitHistory.data', 2)
                ->where('visitHistory.data', fn ($history): bool => collect($history)
                    ->pluck('id')->all() === $newestFirstIds->slice(10)->values()->all())
                ->where('visitHistory.pagination.currentPage', 2)
                ->where('upcomingAppointments.pagination.currentPage', 1)
                ->where('pastUnresolvedAppointments.pagination.currentPage', 1)
                ->where('appointmentHistory.pagination.currentPage', 1)
            );
    });
});

it('groups and paginates Patient appointments without leaking unrelated records', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $receptionist = patientRegistryReceptionist();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $upcoming = Appointment::factory()->for($patient)->count(6)->sequence(
            fn ($sequence): array => ['scheduled_at' => now()->addDays($sequence->index + 1)],
        )->create();
        $boundaryScheduled = Appointment::factory()->for($patient)->create([
            'scheduled_at' => now(),
        ]);
        $pastUnresolved = Appointment::factory()->for($patient)->count(11)->sequence(
            fn ($sequence): array => ['scheduled_at' => now()->subDays($sequence->index + 1)],
        )->create();
        $historical = Appointment::factory()->for($patient)->count(11)->sequence(
            fn ($sequence): array => ['scheduled_at' => now()->subHours($sequence->index + 1)],
        )->create();
        $historical->each(function (Appointment $appointment, int $index): void {
            $appointment->forceFill([
                'status' => $index % 2 === 0
                    ? AppointmentStatus::Cancelled
                    : AppointmentStatus::NoShow,
            ])->save();
        });
        $otherUpcoming = Appointment::factory()->for($otherPatient)->create([
            'scheduled_at' => now()->addHour(),
        ]);
        $otherPastUnresolved = Appointment::factory()->for($otherPatient)->create([
            'scheduled_at' => now()->subDay(),
        ]);
        $otherHistorical = Appointment::factory()->for($otherPatient)->create([
            'scheduled_at' => now()->subHour(),
        ]);
        $otherHistorical->forceFill(['status' => AppointmentStatus::Cancelled])->save();
        $upcomingIds = collect([$boundaryScheduled])
            ->concat($upcoming)
            ->sortBy(fn (Appointment $appointment): string => $appointment->scheduled_at->toIso8601String())
            ->pluck('id')
            ->values();
        $pastUnresolvedIds = $pastUnresolved->sortByDesc('scheduled_at')->pluck('id')->values();
        $historicalIds = $historical->sortByDesc('scheduled_at')->pluck('id')->values();

        $this->actingAs($receptionist)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->has('upcomingAppointments.data', 5)
                ->where('upcomingAppointments.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('id')->all() === $upcomingIds->take(5)->all())
                ->where('upcomingAppointments.pagination.pageName', 'upcoming_appointments_page')
                ->where('upcomingAppointments.pagination.perPage', 5)
                ->where('upcomingAppointments.pagination.total', 7)
                ->where('upcomingAppointments.pagination.nextPageUrl', fn (?string $url): bool => $url !== null && str_contains($url, 'upcoming_appointments_page=2'))
                ->has('pastUnresolvedAppointments.data', 10)
                ->where('pastUnresolvedAppointments.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('id')->all() === $pastUnresolvedIds->take(10)->all())
                ->where('pastUnresolvedAppointments.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('status.value')->every(fn (string $status): bool => $status === 'scheduled'))
                ->where('pastUnresolvedAppointments.pagination.pageName', 'past_unresolved_appointments_page')
                ->where('pastUnresolvedAppointments.pagination.perPage', 10)
                ->where('pastUnresolvedAppointments.pagination.total', 11)
                ->where('pastUnresolvedAppointments.pagination.nextPageUrl', fn (?string $url): bool => $url !== null && str_contains($url, 'past_unresolved_appointments_page=2'))
                ->has('appointmentHistory.data', 10)
                ->where('appointmentHistory.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('id')->all() === $historicalIds->take(10)->all())
                ->where('appointmentHistory.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('status.value')->every(
                        fn (string $status): bool => in_array($status, ['cancelled', 'no_show'], true),
                    ))
                ->where('appointmentHistory.pagination.pageName', 'appointment_history_page')
                ->where('appointmentHistory.pagination.perPage', 10)
                ->where('appointmentHistory.pagination.total', 11)
                ->where('upcomingAppointments.data', fn ($appointments): bool => ! collect($appointments)
                    ->pluck('id')->contains($otherUpcoming->id))
                ->where('pastUnresolvedAppointments.data', fn ($appointments): bool => ! collect($appointments)
                    ->pluck('id')->contains($otherPastUnresolved->id))
                ->where('appointmentHistory.data', fn ($appointments): bool => ! collect($appointments)
                    ->pluck('id')->contains($otherHistorical->id))
            );

        $this->actingAs($receptionist)
            ->get(route('patients.show', [
                $patient,
                'upcoming_appointments_page' => 2,
                'past_unresolved_appointments_page' => 2,
                'appointment_history_page' => 2,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('upcomingAppointments.data', 2)
                ->where('upcomingAppointments.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('id')->all() === $upcomingIds->slice(5)->values()->all())
                ->where('upcomingAppointments.pagination.currentPage', 2)
                ->has('pastUnresolvedAppointments.data', 1)
                ->where('pastUnresolvedAppointments.data.0.id', $pastUnresolvedIds->last())
                ->where('pastUnresolvedAppointments.pagination.currentPage', 2)
                ->has('appointmentHistory.data', 1)
                ->where('appointmentHistory.data.0.id', $historicalIds->last())
                ->where('appointmentHistory.pagination.currentPage', 2)
                ->where('visitHistory.pagination.currentPage', 1)
            );

        expect($pastUnresolved->every(
            fn (Appointment $appointment): bool => $appointment->fresh()->status === AppointmentStatus::Scheduled,
        ))->toBeTrue()
            ->and($upcoming->count() + 1 + $pastUnresolved->count() + $historical->count())
            ->toBe($patient->appointments()->count());
    });
});

it('exposes only existing administrative facts in Patient histories', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $receptionist = patientRegistryReceptionist();
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create([
            'occurred_at' => '2026-08-30 10:30:00',
        ]);
        $appointment = Appointment::factory()->for($patient)->create([
            'scheduled_at' => '2026-09-02 11:45:00',
        ]);
        $pastUnresolvedAppointment = Appointment::factory()->for($patient)->create([
            'scheduled_at' => '2026-08-29 11:45:00',
        ]);

        $this->actingAs($receptionist)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visitHistory.data.0', [
                    'id' => $visit->id,
                    'visitNumber' => $visit->visit_number,
                    'occurredAt' => '2026-08-30T10:30:00+00:00',
                    'status' => ['value' => 'created', 'label' => 'Created'],
                    'nextStep' => 'Awaiting consultation billing',
                ])
                ->where('upcomingAppointments.data.0', [
                    'id' => $appointment->id,
                    'appointmentNumber' => $appointment->appointment_number,
                    'scheduledAt' => '2026-09-02T11:45:00+00:00',
                    'status' => ['value' => 'scheduled', 'label' => 'Scheduled'],
                ])
                ->where('pastUnresolvedAppointments.data.0', [
                    'id' => $pastUnresolvedAppointment->id,
                    'appointmentNumber' => $pastUnresolvedAppointment->appointment_number,
                    'scheduledAt' => '2026-08-29T11:45:00+00:00',
                    'status' => ['value' => 'scheduled', 'label' => 'Scheduled'],
                ])
                ->missing('visitHistory.data.0.patient')
                ->missing('visitHistory.data.0.charges')
                ->missing('visitHistory.data.0.clinical')
                ->missing('upcomingAppointments.data.0.patient')
                ->missing('upcomingAppointments.data.0.note')
                ->missing('upcomingAppointments.data.0.visit')
                ->missing('upcomingAppointments.data.0.billing')
                ->missing('pastUnresolvedAppointments.data.0.patient')
                ->missing('pastUnresolvedAppointments.data.0.note')
                ->missing('pastUnresolvedAppointments.data.0.visit')
                ->missing('pastUnresolvedAppointments.data.0.billing')
            );
    });
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
