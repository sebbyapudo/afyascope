<?php

namespace App\Http\Controllers;

use App\Actions\Administration\SetServiceCatalogItemActiveState;
use App\Http\Requests\UpdateServiceCatalogItemStatusRequest;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ServiceCatalogItemStatusController extends Controller
{
    public function __invoke(
        UpdateServiceCatalogItemStatusRequest $request,
        ServiceCatalogItem $serviceCatalogItem,
        SetServiceCatalogItemActiveState $setActiveState,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $service = $setActiveState->handle($actor, $serviceCatalogItem, $request->isActive());
        $state = $service->is_active ? 'activated' : 'deactivated';

        return back()->with('status', "{$service->name} was {$state}.");
    }
}
