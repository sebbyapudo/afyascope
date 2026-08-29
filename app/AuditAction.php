<?php

namespace App;

enum AuditAction: string
{
    case AdministratorBootstrapped = 'administrator.bootstrapped';
    case StaffCreated = 'staff.created';
    case StaffUpdated = 'staff.updated';

    public function displayName(): string
    {
        return match ($this) {
            self::AdministratorBootstrapped => 'Initial Administrator created',
            self::StaffCreated => 'Staff account created',
            self::StaffUpdated => 'Staff account updated',
        };
    }
}
