<?php

namespace App;

enum StaffRole: string
{
    case Receptionist = 'receptionist';
    case Accountant = 'accountant';
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case Administrator = 'administrator';
    case Management = 'management';

    public function displayName(): string
    {
        return match ($this) {
            self::Receptionist => 'Receptionist',
            self::Accountant => 'Accountant / Cashier',
            self::Doctor => 'Doctor / Endoscopist',
            self::Nurse => 'Nurse / Clinical Staff',
            self::Administrator => 'Administrator',
            self::Management => 'Management',
        };
    }

    /**
     * @return list<StaffPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Doctor,
            self::Nurse => [StaffPermission::DashboardView],
            self::Accountant => [
                StaffPermission::DashboardView,
                StaffPermission::BillingView,
                StaffPermission::BillingCreate,
                StaffPermission::PaymentsView,
                StaffPermission::PaymentsCreate,
                StaffPermission::ClearanceView,
                StaffPermission::ClearanceCreate,
            ],
            self::Receptionist => [
                StaffPermission::DashboardView,
                StaffPermission::PatientsCreate,
                StaffPermission::PatientsView,
                StaffPermission::PatientsUpdate,
                StaffPermission::VisitsCreate,
                StaffPermission::VisitsView,
                StaffPermission::AppointmentsView,
                StaffPermission::AppointmentsCreate,
                StaffPermission::AppointmentsUpdate,
                StaffPermission::CheckInView,
                StaffPermission::CheckInCreate,
            ],
            self::Administrator => [
                StaffPermission::DashboardView,
                StaffPermission::UsersView,
                StaffPermission::UsersManage,
                StaffPermission::RolesView,
                StaffPermission::AuditView,
            ],
            self::Management => [
                StaffPermission::DashboardView,
                StaffPermission::AuditView,
            ],
        };
    }
}
