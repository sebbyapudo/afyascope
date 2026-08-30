<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the canonical roles and permissions are seeded exactly', function () {
    expect(Role::query()->orderBy('slug')->pluck('name', 'slug')->all())->toBe([
        'accountant' => 'Accountant / Cashier',
        'administrator' => 'Administrator',
        'doctor' => 'Doctor / Endoscopist',
        'management' => 'Management',
        'nurse' => 'Nurse / Clinical Staff',
        'receptionist' => 'Receptionist',
    ])->and(Permission::query()->orderBy('slug')->pluck('name', 'slug')->all())->toBe([
        'audit.view' => 'View audit log',
        'dashboard.view' => 'View dashboard',
        'patients.create' => 'Register patients',
        'patients.update' => 'Update patient demographics',
        'patients.view' => 'View patients',
        'roles.view' => 'View roles',
        'users.manage' => 'Manage staff users',
        'users.view' => 'View staff users',
        'visits.create' => 'Create visits',
    ]);
});

test('canonical role permission mappings are exact', function () {
    $expectedMappings = [
        'accountant' => ['dashboard.view'],
        'administrator' => [
            'audit.view',
            'dashboard.view',
            'roles.view',
            'users.manage',
            'users.view',
        ],
        'doctor' => ['dashboard.view'],
        'management' => ['audit.view', 'dashboard.view'],
        'nurse' => ['dashboard.view'],
        'receptionist' => [
            'dashboard.view',
            'patients.create',
            'patients.update',
            'patients.view',
            'visits.create',
        ],
    ];

    $actualMappings = Role::query()
        ->with('permissions')
        ->get()
        ->mapWithKeys(fn (Role $role): array => [
            $role->slug => $role->permissions->pluck('slug')->sort()->values()->all(),
        ])
        ->sortKeys()
        ->all();

    expect($actualMappings)->toBe($expectedMappings)
        ->and(DB::table('permission_role')->count())->toBe(15);
});

test('the rbac seeder is idempotent and repairs canonical mappings', function () {
    $roleIds = Role::query()->orderBy('slug')->pluck('id', 'slug')->all();
    $permissionIds = Permission::query()->orderBy('slug')->pluck('id', 'slug')->all();

    $management = Role::query()->where('slug', 'management')->sole();
    $management->permissions()->sync(
        Permission::query()->where('slug', 'users.manage')->pluck('id'),
    );

    $this->seed(RbacSeeder::class);
    $this->seed(RbacSeeder::class);

    expect(Role::query()->orderBy('slug')->pluck('id', 'slug')->all())->toBe($roleIds)
        ->and(Permission::query()->orderBy('slug')->pluck('id', 'slug')->all())->toBe($permissionIds)
        ->and(Role::query()->count())->toBe(6)
        ->and(Permission::query()->count())->toBe(9)
        ->and($management->fresh()->permissions->pluck('slug')->sort()->values()->all())
        ->toBe(['audit.view', 'dashboard.view']);
});

test('role slugs are unique', function () {
    expect(fn () => Role::query()->create([
        'slug' => 'receptionist',
        'name' => 'Duplicate receptionist',
    ]))->toThrow(QueryException::class);
});

test('permission slugs are unique', function () {
    expect(fn () => Permission::query()->create([
        'slug' => 'dashboard.view',
        'name' => 'Duplicate dashboard permission',
    ]))->toThrow(QueryException::class);
});

test('a role cannot receive the same permission more than once', function () {
    $roleId = Role::query()->where('slug', 'receptionist')->soleValue('id');
    $permissionId = Permission::query()->where('slug', 'dashboard.view')->soleValue('id');

    expect(fn () => DB::table('permission_role')->insert([
        'permission_id' => $permissionId,
        'role_id' => $roleId,
    ]))->toThrow(QueryException::class);
});
