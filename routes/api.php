<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('health')->group(function () {
    Route::get('/ping', [HealthController::class, 'ping'])->name('api.health.ping');
    Route::get('/check', [HealthController::class, 'check'])->name('api.health.check');
    Route::get('/ready', [HealthController::class, 'ready'])->name('api.health.ready');
});
