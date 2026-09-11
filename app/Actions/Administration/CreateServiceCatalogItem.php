<?php

namespace App\Actions\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateServiceCatalogItem
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /** @param array{name: string, category: string, unit_price_minor: int} $attributes */
    public function handle(User $actor, array $attributes): ServiceCatalogItem
    {
        Gate::forUser($actor)->authorize('create', ServiceCatalogItem::class);

        return DB::transaction(function () use ($actor, $attributes): ServiceCatalogItem {
            $service = new ServiceCatalogItem;
            $service->name = $attributes['name'];
            $service->category = BillType::from($attributes['category']);
            $service->unit_price_minor = $attributes['unit_price_minor'];
            $service->is_active = true;
            $service->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ServiceCreated,
                subject: $service,
                afterValues: [
                    'name' => $service->name,
                    'category' => $service->category->value,
                    'unit_price_minor' => $service->unit_price_minor,
                    'is_active' => $service->is_active,
                ],
            );

            return $service;
        });
    }
}
