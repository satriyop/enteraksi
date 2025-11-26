<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearnerDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Learner Dashboard
Route::get('learner/dashboard', LearnerDashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('learner.dashboard');

require __DIR__.'/settings.php';
