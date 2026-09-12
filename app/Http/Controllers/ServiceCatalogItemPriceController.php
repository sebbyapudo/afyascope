<?php

namespace App\Http\Controllers;

use App\Actions\Administration\UpdateServiceCatalogItemPrice;
use App\Http\Requests\UpdateServiceCatalogItemPriceRequest;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ServiceCatalogItemPriceController extends Controller
{
    public function __invoke(
        UpdateServiceCatalogItemPriceRequest $request,
        ServiceCatalogItem $serviceCatalogItem,
        UpdateServiceCatalogItemPrice $updateServiceCatalogItemPrice,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $service = $updateServiceCatalogItemPrice->handle(
            $actor,
            $serviceCatalogItem,
            $request->unitPriceMinor(),
            $request->expectedUnitPriceMinor(),
        );

        return redirect()->route('service-catalog.show', $service)->with(
            'status',
            "{$service->name}'s current price was updated.",
        );
    }
}
