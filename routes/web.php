<?php

use App\Http\Controllers\Admin\ActivationCodeController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AppVersionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\LicenceController;
use App\Http\Controllers\Admin\MfaController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminMfaIsVerified;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('auth.login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('login.store');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/mfa/challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'verify'])->middleware('throttle:mfa')->name('mfa.challenge.verify');
    Route::post('/mfa/cancel', [MfaChallengeController::class, 'cancel'])->name('mfa.challenge.cancel');
});

Route::middleware(['auth', EnsureAdminIsActive::class, EnsureAdminMfaIsVerified::class])->group(function (): void {
    Route::get('/admin/mfa/setup', [MfaController::class, 'setup'])->name('admin.mfa.setup');
    Route::post('/admin/mfa/setup/begin', [MfaController::class, 'begin'])->middleware('throttle:mfa')->name('admin.mfa.setup.begin');
    Route::post('/admin/mfa/setup/confirm', [MfaController::class, 'confirm'])->middleware('throttle:mfa')->name('admin.mfa.setup.confirm');
    Route::post('/admin/mfa/reset', [MfaController::class, 'reset'])->middleware('throttle:mfa')->name('admin.mfa.reset');
    Route::post('/admin/mfa/disable', [MfaController::class, 'disable'])->middleware('throttle:mfa')->name('admin.mfa.disable');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('/admin/customers/create', [CustomerController::class, 'create'])->name('admin.customers.create');
    Route::post('/admin/customers', [CustomerController::class, 'store'])->name('admin.customers.store');
    Route::get('/admin/customers/{customer}/edit', [CustomerController::class, 'edit'])->whereNumber('customer')->name('admin.customers.edit');
    Route::put('/admin/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('admin.customers.update');
    Route::patch('/admin/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('admin.customers.patch');
    Route::post('/admin/customers/{customer}/suspend', [CustomerController::class, 'suspend'])->whereNumber('customer')->name('admin.customers.suspend');
    Route::post('/admin/customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->whereNumber('customer')->name('admin.customers.reactivate');
    Route::post('/admin/customers/{customer}/archive', [CustomerController::class, 'archive'])->whereNumber('customer')->name('admin.customers.archive');
    Route::get('/admin/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('admin.customers.show');
    Route::get('/admin/activation-codes', [ActivationCodeController::class, 'index'])->name('admin.activation-codes.index');
    Route::post('/admin/activation-codes/{activationCode}/revoke', [ActivationCodeController::class, 'revoke'])->whereNumber('activationCode')->name('admin.activation-codes.revoke');
    Route::get('/admin/stores', [StoreController::class, 'index'])->name('admin.stores.index');
    Route::get('/admin/stores/create', [StoreController::class, 'create'])->name('admin.stores.create');
    Route::post('/admin/stores', [StoreController::class, 'store'])->name('admin.stores.store');
    Route::get('/admin/stores/{store}/edit', [StoreController::class, 'edit'])->whereNumber('store')->name('admin.stores.edit');
    Route::put('/admin/stores/{store}', [StoreController::class, 'update'])->whereNumber('store')->name('admin.stores.update');
    Route::patch('/admin/stores/{store}', [StoreController::class, 'update'])->whereNumber('store')->name('admin.stores.patch');
    Route::post('/admin/stores/{store}/suspend', [StoreController::class, 'suspend'])->whereNumber('store')->name('admin.stores.suspend');
    Route::post('/admin/stores/{store}/reactivate', [StoreController::class, 'reactivate'])->whereNumber('store')->name('admin.stores.reactivate');
    Route::post('/admin/stores/{store}/test-connection', [StoreController::class, 'testConnection'])->whereNumber('store')->name('admin.stores.test-connection');
    Route::get('/admin/stores/{store}/activation-codes', [ActivationCodeController::class, 'storeIndex'])->whereNumber('store')->name('admin.stores.activation-codes.index');
    Route::get('/admin/stores/{store}/activation-codes/create', [ActivationCodeController::class, 'create'])->whereNumber('store')->name('admin.stores.activation-codes.create');
    Route::post('/admin/stores/{store}/activation-codes', [ActivationCodeController::class, 'generate'])->whereNumber('store')->name('admin.stores.activation-codes.store');
    Route::get('/admin/stores/{store}', [StoreController::class, 'show'])->whereNumber('store')->name('admin.stores.show');
    Route::get('/admin/system-health', SystemHealthController::class)->name('admin.system-health.index');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/admin/settings', [AppVersionController::class, 'edit'])->name('admin.settings.app-versions');
    Route::put('/admin/settings/app-versions', [AppVersionController::class, 'update'])->name('admin.settings.app-versions.update');
    Route::get('/admin/devices', [DeviceController::class, 'index'])->name('admin.devices.index');
    Route::get('/admin/devices/{device}', [DeviceController::class, 'show'])->whereNumber('device')->name('admin.devices.show');
    Route::patch('/admin/devices/{device}/rename', [DeviceController::class, 'rename'])->whereNumber('device')->name('admin.devices.rename');
    Route::patch('/admin/devices/{device}/move', [DeviceController::class, 'move'])->whereNumber('device')->name('admin.devices.move');
    Route::post('/admin/devices/{device}/disable', [DeviceController::class, 'disable'])->whereNumber('device')->name('admin.devices.disable');
    Route::post('/admin/devices/{device}/reactivate', [DeviceController::class, 'reactivate'])->whereNumber('device')->name('admin.devices.reactivate');
    Route::post('/admin/devices/{device}/revoke', [DeviceController::class, 'revoke'])->whereNumber('device')->name('admin.devices.revoke');
    Route::get('/admin/licences', [LicenceController::class, 'index'])->name('admin.licences.index');
    Route::get('/admin/licences/create', [LicenceController::class, 'create'])->name('admin.licences.create');
    Route::post('/admin/licences', [LicenceController::class, 'store'])->name('admin.licences.store');
    Route::get('/admin/licences/{licence}/edit', [LicenceController::class, 'edit'])->whereNumber('licence')->name('admin.licences.edit');
    Route::put('/admin/licences/{licence}', [LicenceController::class, 'update'])->whereNumber('licence')->name('admin.licences.update');
    Route::post('/admin/licences/{licence}/extend', [LicenceController::class, 'extend'])->whereNumber('licence')->name('admin.licences.extend');
    Route::post('/admin/licences/{licence}/status/{state}', [LicenceController::class, 'transition'])->whereNumber('licence')->whereIn('state', ['past-due', 'grace', 'active', 'restricted', 'suspended', 'cancelled'])->name('admin.licences.transition');
    Route::get('/admin/licences/{licence}', [LicenceController::class, 'show'])->whereNumber('licence')->name('admin.licences.show');
    Route::get('/admin/admin-users', [AdminUserController::class, 'index'])->name('admin.admin-users.index');
    Route::get('/admin/admin-users/create', [AdminUserController::class, 'create'])->name('admin.admin-users.create');
    Route::post('/admin/admin-users', [AdminUserController::class, 'store'])->name('admin.admin-users.store');
    Route::get('/admin/admin-users/{adminUser}/edit', [AdminUserController::class, 'edit'])->whereNumber('adminUser')->name('admin.admin-users.edit');
    Route::put('/admin/admin-users/{adminUser}', [AdminUserController::class, 'update'])->whereNumber('adminUser')->name('admin.admin-users.update');
    Route::post('/admin/admin-users/{adminUser}/disable', [AdminUserController::class, 'disable'])->whereNumber('adminUser')->name('admin.admin-users.disable');
    Route::post('/admin/admin-users/{adminUser}/enable', [AdminUserController::class, 'enable'])->whereNumber('adminUser')->name('admin.admin-users.enable');
    Route::get('/admin/{section}', AdminSectionController::class)
        ->whereIn('section', ['customers', 'stores', 'activation-codes', 'system-health', 'admin-users', 'settings'])
        ->name('admin.section');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
