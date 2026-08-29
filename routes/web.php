<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StaffUserController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:dashboard.view'])
    ->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->can('viewAny', AuditLog::class)
        ->name('audit-logs.index');

    Route::get('/staff', [StaffUserController::class, 'index'])
        ->can('viewAny', User::class)
        ->name('staff.index');
    Route::get('/staff/create', [StaffUserController::class, 'create'])
        ->can('create', User::class)
        ->name('staff.create');
    Route::post('/staff', [StaffUserController::class, 'store'])
        ->can('create', User::class)
        ->name('staff.store');
    Route::get('/staff/{staffUser}/edit', [StaffUserController::class, 'edit'])
        ->can('update', 'staffUser')
        ->name('staff.edit');
    Route::put('/staff/{staffUser}', [StaffUserController::class, 'update'])
        ->can('update', 'staffUser')
        ->name('staff.update');
});
