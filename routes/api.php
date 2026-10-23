<?php

use App\Http\Controllers\Api\ActivationController;
use Illuminate\Support\Facades\Route;

// Versioned APIs for public device provisioning and client control-plane calls.
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/activate', [ActivationController::class, 'activate'])
        ->middleware('throttle:device-activation')
        ->name('activate');
});
