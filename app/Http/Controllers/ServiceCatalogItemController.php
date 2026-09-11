<?php

namespace App\Http\Controllers;

use App\Actions\Administration\CreateServiceCatalogItem;
use App\Actions\Administration\UpdateServiceCatalogItem;
use App\BillType;
use App\Http\Requests\StoreServiceCatalogItemRequest;
use App\Http\Requests\UpdateServiceCatalogItemRequest;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(BillType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $category = $filters['category'] ?? null;
        $statusFilter = $filters['status'] ?? null;

        $services = ServiceCatalogItem::query()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when(is_string($category), fn (Builder $query) => $query->where('category', $category))
            ->when($statusFilter === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->withCount(['billItems', 'procedureBillingHandoffs', 'procedureDecisions'])
            ->orderBy('category')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('service-catalog/index', [
            'services' => [
                'data' => $services->getCollection()
                    ->map(fn (ServiceCatalogItem $service): array => $this->serviceData($service))
                    ->values(),
                'pagination' => [
                    'currentPage' => $services->currentPage(),
                    'from' => $services->firstItem(),
                    'lastPage' => $services->lastPage(),
                    'to' => $services->lastItem(),
                    'total' => $services->total(),
                ],
            ],
            'filters' => [
                'q' => $search,
                'category' => is_string($category) ? $category : null,
                'status' => is_string($statusFilter) ? $statusFilter : null,
            ],
            'categories' => $this->categoryOptions(),
            'status' => is_string($request->session()->get('status'))
                ? $request->session()->get('status')
                : null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('service-catalog/create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(
        StoreServiceCatalogItemRequest $request,
        CreateServiceCatalogItem $createServiceCatalogItem,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $service = $createServiceCatalogItem->handle($actor, $request->serviceAttributes());

        return redirect()->route('service-catalog.show', $service)->with(
            'status',
            "{$service->name} was created.",
        );
    }

    public function show(Request $request, ServiceCatalogItem $serviceCatalogItem): Response
    {
        $serviceCatalogItem->loadCount(['billItems', 'procedureBillingHandoffs', 'procedureDecisions']);

        return Inertia::render('service-catalog/show', [
            'service' => $this->serviceData($serviceCatalogItem),
            'status' => is_string($request->session()->get('status'))
                ? $request->session()->get('status')
                : null,
        ]);
    }

    public function edit(ServiceCatalogItem $serviceCatalogItem): Response
    {
        $serviceCatalogItem->loadCount(['billItems', 'procedureBillingHandoffs', 'procedureDecisions']);

        return Inertia::render('service-catalog/edit', [
            'service' => $this->serviceData($serviceCatalogItem),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(
        UpdateServiceCatalogItemRequest $request,
        ServiceCatalogItem $serviceCatalogItem,
        UpdateServiceCatalogItem $updateServiceCatalogItem,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $service = $updateServiceCatalogItem->handle(
            $actor,
            $serviceCatalogItem,
            $request->serviceAttributes(),
        );

        return redirect()->route('service-catalog.show', $service)->with(
            'status',
            "{$service->name} was updated.",
        );
    }

    /**
     * @return array{id: int, name: string, category: array{value: string, label: string}, unitPriceMinor: int, isActive: bool, isReferenced: bool, usage: array{billItems: int, procedureDecisions: int}, createdAt: string, updatedAt: string}
     */
    private function serviceData(ServiceCatalogItem $service): array
    {
        $billItemsCount = (int) ($service->getAttribute('bill_items_count') ?? 0);
        $procedureBillingHandoffsCount = (int) ($service->getAttribute('procedure_billing_handoffs_count') ?? 0);
        $procedureDecisionsCount = (int) ($service->getAttribute('procedure_decisions_count') ?? 0);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'category' => [
                'value' => $service->category->value,
                'label' => $service->category->displayName(),
            ],
            'unitPriceMinor' => $service->unit_price_minor,
            'isActive' => $service->is_active,
            'isReferenced' => $billItemsCount > 0
                || $procedureBillingHandoffsCount > 0
                || $procedureDecisionsCount > 0,
            'usage' => [
                'billItems' => $billItemsCount,
                'procedureDecisions' => $procedureDecisionsCount,
            ],
            'createdAt' => $service->created_at?->toIso8601String() ?? '',
            'updatedAt' => $service->updated_at?->toIso8601String() ?? '',
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function categoryOptions(): array
    {
        return array_map(
            static fn (BillType $type): array => [
                'value' => $type->value,
                'label' => $type->displayName(),
            ],
            BillType::cases(),
        );
    }
}
