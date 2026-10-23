<?php

use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\DeviceControlController;
use App\Http\Controllers\Api\DeviceCredentialController;
use App\Http\Middleware\AuthenticateDeviceCredential;
use Illuminate\Support\Facades\Route;

// Versioned APIs for public device provisioning and client control-plane calls.
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/activate', [ActivationController::class, 'activate'])
        ->middleware('throttle:device-activation')
        ->name('activate');
    Route::post('/device/credentials', [DeviceCredentialController::class, 'exchange'])
        ->middleware('throttle:device-credentials')
        ->name('device.credentials.exchange');
    Route::middleware([AuthenticateDeviceCredential::class, 'throttle:device-credentials'])->prefix('device/credential')->name('device.credential.')->group(function (): void {
        Route::post('/rotate', [DeviceCredentialController::class, 'rotate'])->name('rotate');
        Route::post('/revoke', [DeviceCredentialController::class, 'revoke'])->name('revoke');
    });
    Route::middleware([AuthenticateDeviceCredential::class, 'throttle:device-api'])->prefix('device')->name('device.')->group(function (): void {
        Route::post('/heartbeat', [DeviceControlController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/config', [DeviceControlController::class, 'config'])->name('config');
    });
});
