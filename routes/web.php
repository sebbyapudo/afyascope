<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientDuplicateController;
use App\Http\Controllers\StaffUserController;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:dashboard.view'])
    ->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/patients', [PatientController::class, 'index'])
        ->can('viewAny', Patient::class)
        ->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])
        ->can('create', Patient::class)
        ->name('patients.create');
    Route::post('/patients/possible-duplicates', PatientDuplicateController::class)
        ->can('create', Patient::class)
        ->name('patients.possible-duplicates');
    Route::post('/patients', [PatientController::class, 'store'])
        ->can('create', Patient::class)
        ->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])
        ->can('view', 'patient')
        ->name('patients.show');
    Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])
        ->can('update', 'patient')
        ->name('patients.edit');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])
        ->can('update', 'patient')
        ->name('patients.update');

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
