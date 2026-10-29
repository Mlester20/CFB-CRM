<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SaimsConnectionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadSaimsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Saims\CustomerController;
use App\Http\Controllers\Saims\InvoiceController;
use App\Http\Controllers\Saims\ProductController;
use App\Http\Controllers\Saims\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::prefix('leads')->name('leads.')->middleware('permission:leads.view')->group(function () {
        Route::get('/', [LeadController::class, 'index'])->name('index');
        Route::get('export', [LeadController::class, 'export'])->name('export');
        Route::get('saims-customers', [LeadSaimsController::class, 'customers'])->middleware('throttle:30,1')->name('saims-customers');
        Route::get('create', [LeadController::class, 'create'])->name('create');
        Route::post('/', [LeadController::class, 'store'])->name('store');
        Route::delete('/', [LeadController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('{lead}', [LeadController::class, 'show'])->whereNumber('lead')->name('show');
        Route::get('{lead}/saims', [LeadSaimsController::class, 'panel'])->whereNumber('lead')->middleware('throttle:60,1')->name('saims-panel');
        Route::get('{lead}/edit', [LeadController::class, 'edit'])->whereNumber('lead')->name('edit');
        Route::put('{lead}', [LeadController::class, 'update'])->whereNumber('lead')->name('update');
        Route::delete('{lead}', [LeadController::class, 'destroy'])->whereNumber('lead')->name('destroy');
    });

    // Read-only SAIMS pages. Each module has its own permission: a user without it
    // never reaches the controller, so nothing is called on SAIMS for them.
    Route::prefix('saims')->name('saims.')->group(function () {
        Route::middleware('permission:saims.customers')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{id}', [CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
        });

        Route::middleware('permission:saims.products')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('products.show');
        });

        Route::middleware('permission:saims.orders')->group(function () {
            Route::get('orders', [SalesOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{id}', [SalesOrderController::class, 'show'])->whereNumber('id')->name('orders.show');
        });

        Route::middleware('permission:saims.invoices')->group(function () {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id')->name('invoices.show');
        });
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('saims', [SaimsConnectionController::class, 'show'])->name('saims');
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
