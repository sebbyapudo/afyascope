<?php

namespace App\Actions\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateServiceCatalogItem
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /** @param array{name: string, category: string, unit_price_minor: int} $attributes */
    public function handle(User $actor, ServiceCatalogItem $service, array $attributes): ServiceCatalogItem
    {
        Gate::forUser($actor)->authorize('update', $service);

        return DB::transaction(function () use ($actor, $service, $attributes): ServiceCatalogItem {
            $lockedService = ServiceCatalogItem::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $category = BillType::from($attributes['category']);

            if ($lockedService->category !== $category && $lockedService->hasHistoricalUsage()) {
                throw ValidationException::withMessages([
                    'category' => 'The category cannot be changed after the service has been used.',
                ]);
            }

            $beforeValues = [];
            $afterValues = [];

            foreach ([
                'name' => $attributes['name'],
                'category' => $category->value,
                'unit_price_minor' => $attributes['unit_price_minor'],
            ] as $field => $value) {
                $currentValue = $field === 'category'
                    ? $lockedService->category->value
                    : $lockedService->getAttribute($field);

                if ($currentValue !== $value) {
                    $beforeValues[$field] = $currentValue;
                    $afterValues[$field] = $value;
                }
            }

            if ($beforeValues === []) {
                return $lockedService;
            }

            $lockedService->name = $attributes['name'];
            $lockedService->category = $category;
            $lockedService->unit_price_minor = $attributes['unit_price_minor'];
            $lockedService->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ServiceUpdated,
                subject: $lockedService,
                beforeValues: $beforeValues,
                afterValues: $afterValues,
            );

            return $lockedService->refresh();
        });
    }
}
