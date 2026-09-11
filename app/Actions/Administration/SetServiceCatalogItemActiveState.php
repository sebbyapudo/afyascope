<?php

namespace App\Actions\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetServiceCatalogItemActiveState
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, ServiceCatalogItem $service, bool $isActive): ServiceCatalogItem
    {
        Gate::forUser($actor)->authorize('update', $service);

        return DB::transaction(function () use ($actor, $service, $isActive): ServiceCatalogItem {
            $lockedService = ServiceCatalogItem::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedService->is_active === $isActive) {
                return $lockedService;
            }

            $beforeState = $lockedService->is_active;
            $lockedService->is_active = $isActive;
            $lockedService->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: $isActive ? AuditAction::ServiceActivated : AuditAction::ServiceDeactivated,
                subject: $lockedService,
                beforeValues: ['is_active' => $beforeState],
                afterValues: ['is_active' => $isActive],
            );

            return $lockedService->refresh();
        });
    }
}
