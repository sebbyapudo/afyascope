<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('lets an Administrator update a price expressed in KES decimal units', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    $this->actingAs($administrator)
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => '1250.75',
            'current_unit_price_minor' => 100_000,
            'unit_price_minor' => 1,
            'is_active' => false,
        ])
        ->assertRedirect(route('service-catalog.show', $service))
        ->assertSessionHas('status');

    expect($service->fresh()->unit_price_minor)->toBe(125_075)
        ->and($service->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::ServicePriceUpdated)->count())->toBe(1);
});

it('rejects invalid or non-positive price input without changing the service', function (string $price) {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    $this->actingAs($administrator)
        ->from(route('service-catalog.show', $service))
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => $price,
            'current_unit_price_minor' => 100_000,
        ])
        ->assertRedirect(route('service-catalog.show', $service))
        ->assertSessionHasErrors('unit_price');

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'zero' => '0',
    'negative' => '-1.00',
    'too precise' => '100.001',
    'malformed' => 'KES 100',
]);

it('rejects stale and malformed current-price tokens without an overwrite', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 110_000]);

    $this->actingAs($administrator)
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => '1250.00',
            'current_unit_price_minor' => 100_000,
        ])
        ->assertSessionHasErrors('unit_price');

    $this->actingAs($administrator)
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => '1250.00',
            'current_unit_price_minor' => 'not-an-integer',
        ])
        ->assertSessionHasErrors('current_unit_price_minor');

    expect($service->fresh()->unit_price_minor)->toBe(110_000)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('renders the current price needed by the focused pricing form without account data', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->inactive()->create(['unit_price_minor' => 120_050]);

    $this->actingAs($administrator)
        ->get(route('service-catalog.show', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-catalog/show')
            ->where('service.id', $service->id)
            ->where('service.unitPriceMinor', 120_050)
            ->where('service.isActive', false)
            ->missing('service.users')
            ->missing('service.auditLogs')
        );
});

it('forbids all other roles from the price update URL', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    $this->actingAs($actor)
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => '1250.00',
            'current_unit_price_minor' => 100_000,
        ])
        ->assertForbidden();

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

it('redirects guests and inactive Administrators without changing pricing', function () {
    $inactiveAdministrator = User::factory()
        ->forRole(StaffRole::Administrator)
        ->inactive()
        ->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);
    $payload = [
        'unit_price' => '1250.00',
        'current_unit_price_minor' => 100_000,
    ];

    $this->patch(route('service-catalog.price.update', $service), $payload)
        ->assertRedirect(route('login'));
    $this->actingAs($inactiveAdministrator)
        ->patch(route('service-catalog.price.update', $service), $payload)
        ->assertRedirect(route('login'));

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
});
