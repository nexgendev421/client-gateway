<?php

use App\Http\Controllers\Admin\ActivationCodeController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnsureAdminIsActive;
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

Route::middleware(['auth', EnsureAdminIsActive::class])->group(function (): void {
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
    Route::get('/admin/{section}', AdminSectionController::class)
        ->whereIn('section', ['customers', 'stores', 'devices', 'activation-codes', 'licences', 'system-health', 'audit-logs', 'admin-users', 'settings'])
        ->name('admin.section');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
