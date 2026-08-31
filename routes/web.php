<?php

use App\Http\Controllers\AppointmentCancelController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentNoShowController;
use App\Http\Controllers\AppointmentVisitController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientDuplicateController;
use App\Http\Controllers\PatientVisitController;
use App\Http\Controllers\StaffUserController;
use App\Http\Controllers\VisitController;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:dashboard.view'])
    ->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->can('viewAny', Appointment::class)
        ->name('appointments.index');
    Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])
        ->can('update', 'appointment')
        ->name('appointments.edit');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])
        ->can('update', 'appointment')
        ->name('appointments.update');
    Route::post('/appointments/{appointment}/cancel', AppointmentCancelController::class)
        ->can('update', 'appointment')
        ->name('appointments.cancel');
    Route::post('/appointments/{appointment}/no-show', AppointmentNoShowController::class)
        ->can('update', 'appointment')
        ->name('appointments.no-show');
    Route::get('/appointments/{appointment}/visit/create', [AppointmentVisitController::class, 'create'])
        ->can('view', 'appointment')
        ->can('create', Visit::class)
        ->name('appointments.visit.create');
    Route::post('/appointments/{appointment}/visit', [AppointmentVisitController::class, 'store'])
        ->can('view', 'appointment')
        ->can('create', Visit::class)
        ->name('appointments.visit.store');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->can('view', 'appointment')
        ->name('appointments.show');

    Route::get('/visits', [VisitController::class, 'index'])
        ->can('viewAny', Visit::class)
        ->name('visits.index');
    Route::get('/visits/{visit}', [VisitController::class, 'show'])
        ->can('view', 'visit')
        ->name('visits.show');

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
    Route::get('/patients/{patient}/appointments/create', [PatientAppointmentController::class, 'create'])
        ->can('create', Appointment::class)
        ->name('patients.appointments.create');
    Route::post('/patients/{patient}/appointments', [PatientAppointmentController::class, 'store'])
        ->can('create', Appointment::class)
        ->name('patients.appointments.store');
    Route::get('/patients/{patient}/visits/create', [PatientVisitController::class, 'create'])
        ->can('create', Visit::class)
        ->name('patients.visits.create');
    Route::post('/patients/{patient}/visits', [PatientVisitController::class, 'store'])
        ->can('create', Visit::class)
        ->name('patients.visits.store');
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
