<?php

use Illuminate\Support\Facades\Route;

// Public device and provisioning APIs will be added under this versioned group.
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Intentionally empty until API functionality is implemented.
});
