<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows an Administrator to browse newest-first audit summaries through the safe projection', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $patient = Patient::factory()->create();
    $olderAuditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'action' => AuditAction::PatientRegistered,
        'subject_type' => Patient::class,
        'subject_id' => $patient->id,
        'after_values' => [
            'patient_number' => $patient->patient_number,
            'clinical_note' => 'must-never-render',
        ],
        'created_at' => now()->subMinute(),
    ]);
    $newerAuditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'action' => AuditAction::StaffUpdated,
        'before_values' => ['is_active' => true],
        'after_values' => [
            'is_active' => false,
            'password_hash' => 'must-never-render',
        ],
        'created_at' => now(),
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('events', count(AuditAction::cases()))
            ->has('subjectTypes')
            ->has('auditLogs.data', 2)
            ->where('auditLogs.data.0.id', $newerAuditLog->id)
            ->where('auditLogs.data.0.actor', [
                'id' => $administrator->id,
                'name' => $administrator->name,
                'email' => $administrator->email,
                'isActive' => true,
                'roleAtEvent' => null,
            ])
            ->where('auditLogs.data.0.action.value', AuditAction::StaffUpdated->value)
            ->where('auditLogs.data.0.changes', [[
                'field' => 'is_active',
                'label' => 'Status',
                'before' => true,
                'after' => false,
            ]])
            ->where('auditLogs.data.1.id', $olderAuditLog->id)
            ->where('auditLogs.data.1.subject.reference', $patient->patient_number)
            ->where('auditLogs.pagination.total', 2)
            ->where('filters.q', '')
        )
        ->assertDontSee('must-never-render');
});

it('allows an Administrator to inspect one event with only allowlisted structured data', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['name' => 'Gastroscopy']);
    $auditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'action' => AuditAction::ServicePriceUpdated,
        'subject_type' => ServiceCatalogItem::class,
        'subject_id' => $service->id,
        'before_values' => ['unit_price_minor' => 100000],
        'after_values' => ['unit_price_minor' => 125000],
        'metadata' => [
            'currency' => 'KES',
            'unknown_future_key' => 'must-never-render',
            'reset_token' => 'must-never-render-either',
        ],
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $auditLog))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/show')
            ->where('auditLog.id', $auditLog->id)
            ->where('auditLog.action', [
                'value' => AuditAction::ServicePriceUpdated->value,
                'label' => AuditAction::ServicePriceUpdated->displayName(),
            ])
            ->where('auditLog.subject', [
                'type' => 'Service catalog item',
                'reference' => null,
                'internalId' => $service->id,
            ])
            ->where('auditLog.changes', [[
                'field' => 'unit_price_minor',
                'label' => 'Unit price (minor units)',
                'before' => 100000,
                'after' => 125000,
            ]])
            ->where('auditLog.metadata', [[
                'field' => 'currency',
                'label' => 'Currency',
                'value' => 'KES',
            ]])
        )
        ->assertDontSee('must-never-render');
});

it('never projects clinical narratives recovery observations discharge instructions or credentials', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $auditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'action' => AuditAction::ConsultationAssessmentUpdated,
        'subject_type' => Visit::class,
        'after_values' => [
            'consultation_number' => 'CON-000001',
            'assessment_note' => 'private-assessment-narrative',
            'procedure_notes' => 'private-procedure-narrative',
            'recovery_vitals' => 'private-vitals-payload',
            'discharge_instructions' => 'private-discharge-instructions',
            'password_hash' => 'private-password-hash',
            'api_token' => 'private-api-token',
        ],
        'metadata' => ['unknown_secret' => 'private-unknown-value'],
    ]);

    $response = $this->actingAs($administrator)->get(route('audit-logs.show', $auditLog));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auditLog.changes', [[
            'field' => 'consultation_number',
            'label' => 'Consultation reference',
            'before' => null,
            'after' => 'CON-000001',
        ]])
        ->where('auditLog.metadata', [])
    );

    foreach ([
        'private-assessment-narrative',
        'private-procedure-narrative',
        'private-vitals-payload',
        'private-discharge-instructions',
        'private-password-hash',
        'private-api-token',
        'private-unknown-value',
    ] as $prohibitedValue) {
        $response->assertDontSee($prohibitedValue);
    }
});

it('represents bootstrap events without fabricating an actor', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $auditLog = AuditLog::factory()->create([
        'actor_id' => null,
        'action' => AuditAction::AdministratorBootstrapped,
        'subject_id' => $administrator->id,
        'before_values' => null,
        'after_values' => ['name' => $administrator->name],
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $auditLog))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.actor', null)
            ->where('auditLog.subject.reference', $administrator->name)
        );
});

it('keeps an inactive historical actor visible without claiming a historical role', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $historicalActor = User::factory()->forRole(StaffRole::Doctor)->create([
        'name' => 'Historical Doctor',
        'is_active' => false,
    ]);
    $auditLog = AuditLog::factory()->create(['actor_id' => $historicalActor->id]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $auditLog))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.actor.name', 'Historical Doctor')
            ->where('auditLog.actor.isActive', false)
            ->where('auditLog.actor.roleAtEvent', null)
        );
});

it('does not misrepresent an actors current role as their role at event time', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $auditLog = AuditLog::factory()->create(['actor_id' => $actor->id]);
    $managementRole = Role::query()->where('slug', StaffRole::Management->value)->firstOrFail();
    $actor->role()->associate($managementRole);
    $actor->save();

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $auditLog))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.actor.id', $actor->id)
            ->where('auditLog.actor.roleAtEvent', null)
            ->missing('auditLog.actor.role')
        );
});

it('keeps completed Visit and renamed or deactivated service snapshots readable', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $visit = Visit::factory()->create();
    DB::table('visits')->where('id', $visit->id)->update([
        'status' => VisitStatus::Completed->value,
        'completed_at' => now(),
    ]);
    $visit->refresh();
    $visitAudit = AuditLog::factory()->create([
        'action' => AuditAction::VisitCompleted,
        'subject_type' => Visit::class,
        'subject_id' => $visit->id,
        'after_values' => [
            'visit_number' => $visit->visit_number,
            'status' => 'completed',
        ],
    ]);
    $service = ServiceCatalogItem::factory()->create(['name' => 'Current renamed service', 'is_active' => false]);
    $serviceAudit = AuditLog::factory()->create([
        'action' => AuditAction::ServiceUpdated,
        'subject_type' => ServiceCatalogItem::class,
        'subject_id' => $service->id,
        'before_values' => ['name' => 'Original service name'],
        'after_values' => ['name' => 'Name recorded at event time'],
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $visitAudit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.subject.reference', $visit->visit_number)
            ->where('auditLog.changes.1.after', 'completed')
        );

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $serviceAudit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.subject.reference', 'Name recorded at event time')
        );
});

it('supports composable event actor subject reference and date filters', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $matchingActor = User::factory()->create(['name' => 'Filter Actor']);
    $otherActor = User::factory()->create(['name' => 'Other Actor']);
    $matchingPatient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();
    $matching = AuditLog::factory()->create([
        'actor_id' => $matchingActor->id,
        'action' => AuditAction::PatientRegistered,
        'subject_type' => Patient::class,
        'subject_id' => $matchingPatient->id,
        'after_values' => ['patient_number' => $matchingPatient->patient_number],
        'created_at' => '2026-09-10 10:00:00',
    ]);
    AuditLog::factory()->create([
        'actor_id' => $otherActor->id,
        'action' => AuditAction::PatientRegistered,
        'subject_type' => Patient::class,
        'subject_id' => $otherPatient->id,
        'after_values' => ['patient_number' => $otherPatient->patient_number],
        'created_at' => '2026-09-10 11:00:00',
    ]);
    AuditLog::factory()->create([
        'actor_id' => $matchingActor->id,
        'action' => AuditAction::StaffUpdated,
        'created_at' => '2026-09-11 10:00:00',
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index', [
            'event' => AuditAction::PatientRegistered->value,
            'actor' => 'Filter Actor',
            'subject_type' => Patient::class,
            'subject_reference' => $matchingPatient->patient_number,
            'date_from' => '2026-09-10',
            'date_to' => '2026-09-10',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auditLogs.data', 1)
            ->where('auditLogs.data.0.id', $matching->id)
            ->where('filters.event', AuditAction::PatientRegistered->value)
            ->where('filters.actor', 'Filter Actor')
            ->where('filters.subjectType', Patient::class)
            ->where('filters.subjectReference', $matchingPatient->patient_number)
            ->where('filters.dateFrom', '2026-09-10')
            ->where('filters.dateTo', '2026-09-10')
        );
});

it('supports general search by readable event actor email and safe subject reference', function (string $search) {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $actor = User::factory()->create(['email' => 'audit-search@example.test']);
    $patient = Patient::factory()->create();
    $auditLog = AuditLog::factory()->create([
        'actor_id' => $actor->id,
        'action' => AuditAction::PatientRegistered,
        'subject_type' => Patient::class,
        'subject_id' => $patient->id,
        'after_values' => ['patient_number' => $patient->patient_number],
    ]);
    $resolvedSearch = $search === 'subject-reference' ? $patient->patient_number : $search;

    $this->actingAs($administrator)
        ->get(route('audit-logs.index', ['q' => $resolvedSearch]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auditLogs.data', 1)
            ->where('auditLogs.data.0.id', $auditLog->id)
        );
})->with([
    'readable event label' => 'Patient registered',
    'actor email' => 'audit-search@example.test',
    'subject reference' => 'subject-reference',
]);

it('preserves canonical Management audit visibility and denies operational roles', function (StaffRole $staffRole, bool $allowed) {
    $user = User::factory()->forRole($staffRole)->create();
    $auditLog = AuditLog::factory()->create();

    $registryResponse = $this->actingAs($user)->get(route('audit-logs.index'));
    $detailResponse = $this->actingAs($user)->get(route('audit-logs.show', $auditLog));

    if ($allowed) {
        $registryResponse->assertOk();
        $detailResponse->assertOk();
    } else {
        $registryResponse->assertForbidden();
        $detailResponse->assertForbidden();
    }
})->with([
    'Management has canonical audit.view' => [StaffRole::Management, true],
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
]);

it('redirects guests and denies inactive Administrators', function () {
    $auditLog = AuditLog::factory()->create();

    $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
    $this->get(route('audit-logs.show', $auditLog))->assertRedirect(route('login'));

    $inactiveAdministrator = User::factory()->forRole(StaffRole::Administrator)->create(['is_active' => false]);
    $this->actingAs($inactiveAdministrator)->get(route('audit-logs.index'))->assertRedirect(route('login'));
    $this->actingAs($inactiveAdministrator)->get(route('audit-logs.show', $auditLog))->assertRedirect(route('login'));
});

it('paginates audit history at twenty-five entries in deterministic newest-first order', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->create();
    AuditLog::factory()->count(26)->create([
        'actor_id' => $administrator->id,
        'subject_id' => $subject->id,
        'created_at' => now(),
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auditLogs.data', 25)
            ->where('auditLogs.data.0.id', AuditLog::query()->max('id'))
            ->where('auditLogs.pagination.currentPage', 1)
            ->where('auditLogs.pagination.lastPage', 2)
            ->where('auditLogs.pagination.total', 26)
        );
});

it('creates no audit event merely by browsing searching or reviewing', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $auditLog = AuditLog::factory()->create();
    $beforeCount = AuditLog::query()->count();

    $this->actingAs($administrator)->get(route('audit-logs.index', ['q' => 'staff']))->assertOk();
    $this->actingAs($administrator)->get(route('audit-logs.show', $auditLog))->assertOk();

    expect(AuditLog::query()->count())->toBe($beforeCount);
});

it('exposes no mutable audit routes', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('audit-logs.index'))->toBeTrue()
        ->and(Route::has('audit-logs.show'))->toBeTrue()
        ->and(Route::has('audit-logs.store'))->toBeFalse()
        ->and(Route::has('audit-logs.update'))->toBeFalse()
        ->and(Route::has('audit-logs.destroy'))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'audit-logs')
                && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) !== [],
        ))->toBeFalse();
});
