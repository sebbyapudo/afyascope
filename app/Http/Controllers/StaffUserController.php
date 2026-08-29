<?php

namespace App\Http\Controllers;

use App\Actions\Staff\CreateStaffUser;
use App\Actions\Staff\UpdateStaffUser;
use App\Http\Requests\StoreStaffUserRequest;
use App\Http\Requests\UpdateStaffUserRequest;
use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $staffUsers = User::query()
            ->with('role:id,slug,name')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'role_id', 'name', 'email', 'is_active'])
            ->map(fn (User $staffUser): array => $this->staffUserData($staffUser));

        $status = $request->session()->get('status');

        return Inertia::render('staff/index', [
            'staffUsers' => $staffUsers,
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
            'staffUser' => $this->staffUserData($staffUser),
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
