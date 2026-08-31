<?php

namespace App;

enum StaffPermission: string
{
    case DashboardView = 'dashboard.view';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';
    case PatientsCreate = 'patients.create';
    case PatientsView = 'patients.view';
    case PatientsUpdate = 'patients.update';
    case VisitsCreate = 'visits.create';
    case VisitsView = 'visits.view';
    case AppointmentsView = 'appointments.view';
    case AppointmentsCreate = 'appointments.create';
    case AppointmentsUpdate = 'appointments.update';
    case BillingView = 'billing.view';

    public function displayName(): string
    {
        return match ($this) {
            self::DashboardView => 'View dashboard',
            self::UsersView => 'View staff users',
            self::UsersManage => 'Manage staff users',
            self::RolesView => 'View roles',
            self::AuditView => 'View audit log',
            self::PatientsCreate => 'Register patients',
            self::PatientsView => 'View patients',
            self::PatientsUpdate => 'Update patient demographics',
            self::VisitsCreate => 'Create visits',
            self::VisitsView => 'View visits',
            self::AppointmentsView => 'View appointments',
            self::AppointmentsCreate => 'Create appointments',
            self::AppointmentsUpdate => 'Update appointments',
            self::BillingView => 'View billing',
        };
    }
}
