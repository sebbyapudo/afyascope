<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:dashboard.view'])
    ->name('dashboard');
