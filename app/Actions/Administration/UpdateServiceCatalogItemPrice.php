<?php

namespace App\Actions\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateServiceCatalogItemPrice
{
    private const int MAX_PRICE_MINOR = 999_999_999_999_999_999;

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(
        User $actor,
        ServiceCatalogItem $service,
        int $unitPriceMinor,
        int $expectedUnitPriceMinor,
    ): ServiceCatalogItem {
        Gate::forUser($actor)->authorize('update', $service);

        return DB::transaction(function () use (
            $actor,
            $service,
            $unitPriceMinor,
            $expectedUnitPriceMinor,
        ): ServiceCatalogItem {
            if ($unitPriceMinor < 1 || $unitPriceMinor > self::MAX_PRICE_MINOR) {
                throw ValidationException::withMessages([
                    'unit_price' => 'The price must be a positive amount with no more than two decimal places.',
                ]);
            }

            $lockedService = ServiceCatalogItem::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedService->unit_price_minor !== $expectedUnitPriceMinor) {
                throw ValidationException::withMessages([
                    'unit_price' => 'The current price changed while you were editing. Review it and try again.',
                ]);
            }

            if ($lockedService->unit_price_minor === $unitPriceMinor) {
                return $lockedService;
            }

            $previousPriceMinor = $lockedService->unit_price_minor;
            $lockedService->unit_price_minor = $unitPriceMinor;
            $lockedService->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ServicePriceUpdated,
                subject: $lockedService,
                beforeValues: ['unit_price_minor' => $previousPriceMinor],
                afterValues: ['unit_price_minor' => $unitPriceMinor],
                metadata: ['currency' => 'KES'],
            );

            return $lockedService->refresh();
        });
    }
}
