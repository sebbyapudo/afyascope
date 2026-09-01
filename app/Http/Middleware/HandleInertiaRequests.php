<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\StaffPermission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->loadMissing('role.permissions');
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'role' => $user instanceof User ? [
                    'slug' => $user->role->slug,
                    'displayName' => $user->role->name,
                ] : null,
                'capabilities' => [
                    'viewDashboard' => $user?->can(StaffPermission::DashboardView) ?? false,
                    'viewUsers' => $user?->can('viewAny', User::class) ?? false,
                    'manageUsers' => $user?->can('create', User::class) ?? false,
                    'viewRoles' => $user?->can('viewAny', Role::class) ?? false,
                    'viewAudit' => $user?->can(StaffPermission::AuditView) ?? false,
                    'viewPatients' => $user?->can('viewAny', Patient::class) ?? false,
                    'createPatients' => $user?->can('create', Patient::class) ?? false,
                    'updatePatients' => $user?->can(StaffPermission::PatientsUpdate) ?? false,
                    'viewVisits' => $user?->can('viewAny', Visit::class) ?? false,
                    'createVisits' => $user?->can('create', Visit::class) ?? false,
                    'viewAppointments' => $user?->can('viewAny', Appointment::class) ?? false,
                    'createAppointments' => $user?->can('create', Appointment::class) ?? false,
                    'updateAppointments' => $user?->can(StaffPermission::AppointmentsUpdate) ?? false,
                    'viewBilling' => $user?->can('viewAny', Bill::class) ?? false,
                    'createBilling' => $user?->can('create', Bill::class) ?? false,
                    'viewPayments' => $user?->can('viewAny', Payment::class) ?? false,
                    'createPayments' => $user?->can('create', Payment::class) ?? false,
                    'viewClearance' => $user?->can('viewAny', FinancialClearance::class) ?? false,
                    'createClearance' => $user?->can('create', FinancialClearance::class) ?? false,
                ],
            ],
        ];
    }
}
