<?php

use App\AuditAction;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('lists all catalog states in deterministic category and name order', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $procedure = ServiceCatalogItem::factory()->procedure()->inactive()->create([
        'name' => 'Colonoscopy',
        'unit_price_minor' => 350_000,
    ]);
    $consultation = ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 150_000,
    ]);

    $this->actingAs($administrator)
        ->get(route('service-catalog.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-catalog/index')
            ->where('services.pagination.total', 2)
            ->where('services.data.0.id', $consultation->id)
            ->where('services.data.0.category', ['value' => 'consultation', 'label' => 'Consultation'])
            ->where('services.data.0.unitPriceMinor', 150_000)
            ->where('services.data.0.isActive', true)
            ->where('services.data.1.id', $procedure->id)
            ->where('services.data.1.isActive', false)
            ->where('categories', [
                ['value' => 'consultation', 'label' => 'Consultation'],
                ['value' => 'procedure', 'label' => 'Procedure'],
            ])
            ->where('auth.capabilities.manageServiceCatalog', true)
            ->missing('services.data.0.billItems')
            ->missing('services.data.0.procedureDecisions')
        );
});

it('searches and filters the catalog without hiding inactive administrative records by default', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    ServiceCatalogItem::factory()->create(['name' => 'Standard consultation']);
    $inactiveProcedure = ServiceCatalogItem::factory()->procedure()->inactive()->create([
        'name' => 'Upper endoscopy',
    ]);
    ServiceCatalogItem::factory()->procedure()->create(['name' => 'Colonoscopy']);

    $this->actingAs($administrator)
        ->get(route('service-catalog.index', [
            'q' => 'endoscopy',
            'category' => 'procedure',
            'status' => 'inactive',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.id', $inactiveProcedure->id)
            ->where('filters', [
                'q' => 'endoscopy',
                'category' => 'procedure',
                'status' => 'inactive',
            ])
        );
});

it('renders create show and edit pages with minimal typed catalog data', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Clinical consultation',
        'unit_price_minor' => 120_000,
    ]);
    BillItem::factory()
        ->for(Bill::factory())
        ->for($service, 'serviceCatalogItem')
        ->create();

    $this->actingAs($administrator)
        ->get(route('service-catalog.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-catalog/create')
            ->has('categories', 2)
        );
    $this->actingAs($administrator)
        ->get(route('service-catalog.show', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-catalog/show')
            ->where('service.name', 'Clinical consultation')
            ->where('service.isReferenced', true)
            ->where('service.usage.billItems', 1)
            ->where('service.usage.procedureDecisions', 0)
            ->missing('service.billItems')
        );
    $this->actingAs($administrator)
        ->get(route('service-catalog.edit', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-catalog/edit')
            ->where('service.id', $service->id)
            ->where('service.isReferenced', true)
        );
});

it('creates a normalized active service using a decimal major-unit price', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    $response = $this->actingAs($administrator)->post(route('service-catalog.store'), [
        'name' => '  Flexible sigmoidoscopy  ',
        'category' => 'PROCEDURE',
        'unit_price' => '2500.75',
        'is_active' => false,
        'unit_price_minor' => 1,
    ]);
    $service = ServiceCatalogItem::query()->where('name', 'Flexible sigmoidoscopy')->sole();

    $response
        ->assertRedirect(route('service-catalog.show', $service))
        ->assertSessionHas('status');
    expect($service->category)->toBe(BillType::Procedure)
        ->and($service->unit_price_minor)->toBe(250_075)
        ->and($service->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::ServiceCreated)->count())->toBe(1);
});

it('validates positive precise prices categories names and per-category uniqueness', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    ServiceCatalogItem::factory()->create(['name' => 'Standard consultation']);
    ServiceCatalogItem::factory()->procedure()->create(['name' => 'Shared name']);

    $this->actingAs($administrator)
        ->post(route('service-catalog.store'), [
            'name' => 'Standard consultation',
            'category' => 'consultation',
            'unit_price' => '100.001',
        ])
        ->assertSessionHasErrors(['name', 'unit_price']);

    $this->actingAs($administrator)
        ->post(route('service-catalog.store'), [
            'name' => '',
            'category' => 'other',
            'unit_price' => '0',
        ])
        ->assertSessionHasErrors(['name', 'category', 'unit_price']);

    $this->actingAs($administrator)
        ->post(route('service-catalog.store'), [
            'name' => 'Shared name',
            'category' => 'consultation',
            'unit_price' => '100.00',
        ])
        ->assertSessionDoesntHaveErrors();

    expect(ServiceCatalogItem::query()->where('name', 'Shared name')->count())->toBe(2);
});

it('updates current configuration and changes availability without deleting the service', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Initial service',
        'unit_price_minor' => 100_000,
    ]);

    $this->actingAs($administrator)
        ->put(route('service-catalog.update', $service), [
            'name' => 'Revised service',
            'category' => 'procedure',
            'unit_price' => '1250.50',
            'is_active' => false,
        ])
        ->assertRedirect(route('service-catalog.show', $service));

    expect($service->fresh()->name)->toBe('Revised service')
        ->and($service->fresh()->category)->toBe(BillType::Procedure)
        ->and($service->fresh()->unit_price_minor)->toBe(125_050)
        ->and($service->fresh()->is_active)->toBeTrue();

    $this->actingAs($administrator)
        ->patch(route('service-catalog.status.update', $service), ['is_active' => false])
        ->assertRedirect();

    expect($service->fresh()->is_active)->toBeFalse()
        ->and(ServiceCatalogItem::query()->whereKey($service)->exists())->toBeTrue();
});

it('enforces database uniqueness for concurrent-style duplicate writes', function () {
    ServiceCatalogItem::factory()->create([
        'name' => 'Unique consultation',
        'category' => BillType::Consultation,
    ]);

    expect(fn () => ServiceCatalogItem::factory()->create([
        'name' => 'Unique consultation',
        'category' => BillType::Consultation,
    ]))->toThrow(QueryException::class);
});

it('forbids all other roles from every catalog URL', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $service = ServiceCatalogItem::factory()->create();

    $this->actingAs($actor)->get(route('service-catalog.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('service-catalog.create'))->assertForbidden();
    $this->actingAs($actor)->get(route('service-catalog.show', $service))->assertForbidden();
    $this->actingAs($actor)->get(route('service-catalog.edit', $service))->assertForbidden();
    $this->actingAs($actor)->post(route('service-catalog.store'), [])->assertForbidden();
    $this->actingAs($actor)->put(route('service-catalog.update', $service), [])->assertForbidden();
    $this->actingAs($actor)
        ->patch(route('service-catalog.status.update', $service), ['is_active' => false])
        ->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

it('redirects guests and denies inactive Administrators', function () {
    $service = ServiceCatalogItem::factory()->create();
    $inactiveAdministrator = User::factory()
        ->forRole(StaffRole::Administrator)
        ->inactive()
        ->create();

    $this->get(route('service-catalog.index'))->assertRedirect(route('login'));
    $this->post(route('service-catalog.store'), [])->assertRedirect(route('login'));
    $this->actingAs($inactiveAdministrator)
        ->get(route('service-catalog.show', $service))
        ->assertRedirect(route('login'));
});

it('exposes no catalog deletion route', function () {
    $routes = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('service-catalog.destroy'))->toBeFalse()
        ->and($routes->contains(
            fn (Illuminate\Routing\Route $route): bool => in_array('DELETE', $route->methods(), true)
                && str_starts_with($route->uri(), 'administration/services'),
        ))->toBeFalse();
});
