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
            self::Receptionist,
            self::Accountant,
            self::Doctor,
            self::Nurse => [StaffPermission::DashboardView],
            self::Administrator => StaffPermission::cases(),
            self::Management => [
                StaffPermission::DashboardView,
                StaffPermission::AuditView,
            ],
        };
    }
}
