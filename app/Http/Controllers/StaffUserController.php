<?php

namespace App\Http\Controllers;

use App\Actions\Staff\CreateStaffUser;
use App\Actions\Staff\UpdateStaffUser;
use App\Http\Requests\StoreStaffUserRequest;
use App\Http\Requests\UpdateStaffUserRequest;
use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class StaffUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(StaffRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $roleFilter = $filters['role'] ?? null;
        $statusFilter = $filters['status'] ?? null;

        $staffUsers = User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(is_string($roleFilter), function (Builder $query) use ($roleFilter): void {
                $query->whereHas('role', fn (Builder $query) => $query->where('slug', $roleFilter));
            })
            ->when($statusFilter === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->with('role:id,slug,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $status = $request->session()->get('status');

        return Inertia::render('staff/index', [
            'staffUsers' => [
                'data' => $staffUsers->getCollection()
                    ->map(fn (User $staffUser): array => $this->staffUserData($staffUser))
                    ->values(),
                'pagination' => [
                    'currentPage' => $staffUsers->currentPage(),
                    'from' => $staffUsers->firstItem(),
                    'lastPage' => $staffUsers->lastPage(),
                    'to' => $staffUsers->lastItem(),
                    'total' => $staffUsers->total(),
                ],
            ],
            'filters' => [
                'q' => $search,
                'role' => is_string($roleFilter) ? $roleFilter : null,
                'status' => is_string($statusFilter) ? $statusFilter : null,
            ],
            'roles' => $this->roleOptions(),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('staff/create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    /**
     * Display the specified staff account.
     */
    public function show(Request $request, User $staffUser): Response
    {
        $staffUser->loadMissing('role:id,slug,name');

        return Inertia::render('staff/show', [
            'staffUser' => $this->staffUserDetailData($staffUser),
            'status' => is_string($request->session()->get('status'))
                ? $request->session()->get('status')
                : null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffUserRequest $request, CreateStaffUser $createStaffUser): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $staffUser = $createStaffUser->handle($actor, $request->staffAttributes());

        return redirect()->route('staff.index')->with(
            'status',
            "{$staffUser->name} was added and sent a secure password setup invitation.",
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $staffUser): Response
    {
        $staffUser->loadMissing('role:id,slug,name');

        return Inertia::render('staff/edit', [
            'staffUser' => $this->staffUserDetailData($staffUser),
            'roles' => $this->roleOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateStaffUserRequest $request,
        User $staffUser,
        UpdateStaffUser $updateStaffUser,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updatedStaffUser = $updateStaffUser->handle($actor, $staffUser, $request->staffAttributes());

        if ($actor->is($updatedStaffUser)) {
            $actor->refresh();
        }

        $redirectRoute = $actor->can('viewAny', User::class) ? 'staff.index' : 'dashboard';

        return redirect()->route($redirectRoute)->with(
            'status',
            "{$updatedStaffUser->name} was updated.",
        );
    }

    /**
     * @return array{id: int, name: string, email: string, role: array{slug: string, displayName: string}, isActive: bool}
     */
    private function staffUserData(User $staffUser): array
    {
        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'role' => [
                'slug' => $staffUser->role->slug,
                'displayName' => $staffUser->role->name,
            ],
            'isActive' => $staffUser->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, role: array{slug: string, displayName: string}, isActive: bool, createdAt: string, updatedAt: string, isFinalActiveAdministrator: bool}
     */
    private function staffUserDetailData(User $staffUser): array
    {
        return [
            ...$this->staffUserData($staffUser),
            'createdAt' => $staffUser->created_at?->toIso8601String() ?? '',
            'updatedAt' => $staffUser->updated_at?->toIso8601String() ?? '',
            'isFinalActiveAdministrator' => $this->isFinalActiveAdministrator($staffUser),
        ];
    }

    private function isFinalActiveAdministrator(User $staffUser): bool
    {
        if (! $staffUser->is_active || $staffUser->role->slug !== StaffRole::Administrator->value) {
            return false;
        }

        return User::query()
            ->where('role_id', $staffUser->role_id)
            ->where('is_active', true)
            ->count() === 1;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        /** @var Collection<string, Role> $roles */
        $roles = Role::query()
            ->whereIn('slug', array_column(StaffRole::cases(), 'value'))
            ->get(['id', 'slug', 'name'])
            ->keyBy('slug');

        return array_map(static function (StaffRole $staffRole) use ($roles): array {
            $role = $roles->get($staffRole->value);

            if (! $role instanceof Role) {
                throw new LogicException("The canonical {$staffRole->displayName()} role is missing.");
            }

            return [
                'value' => $staffRole->value,
                'label' => $role->name,
            ];
        }, StaffRole::cases());
    }
}
