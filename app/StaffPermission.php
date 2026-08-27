<?php

namespace App;

enum StaffPermission: string
{
    case DashboardView = 'dashboard.view';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';

    public function displayName(): string
    {
        return match ($this) {
            self::DashboardView => 'View dashboard',
            self::UsersView => 'View staff users',
            self::UsersManage => 'Manage staff users',
            self::RolesView => 'View roles',
            self::AuditView => 'View audit log',
        };
    }
}
