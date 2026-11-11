<?php

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Accounts\ChartController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Items\ItemController;
use App\Http\Controllers\Money\JournalController;
use App\Http\Controllers\Money\MoneyController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\Setup\AccountTypeController;
use App\Http\Controllers\Setup\CompanyController;
use App\Http\Controllers\Setup\CurrencyController;
use App\Http\Controllers\Setup\ExchangeRateController;
use App\Http\Controllers\Setup\ItemCategoryController;
use App\Http\Controllers\Setup\UnitController;
use App\Http\Controllers\Setup\WarehouseController;
use Illuminate\Support\Facades\Route;

// The six routes of a simple list-and-form screen.
$crud = function (string $uri, string $controller, string $name): void {
    Route::get($uri, [$controller, 'index'])->name("$name.index");
    Route::get("$uri/create", [$controller, 'create'])->name("$name.create");
    Route::post($uri, [$controller, 'store'])->name("$name.store");
    Route::get("$uri/{id}/edit", [$controller, 'edit'])->name("$name.edit")->whereNumber('id');
    Route::put("$uri/{id}", [$controller, 'update'])->name("$name.update")->whereNumber('id');
    Route::delete("$uri/{id}", [$controller, 'destroy'])->name("$name.destroy")->whereNumber('id');
};

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () use ($crud) {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('preferences/calendar', [PreferenceController::class, 'calendar'])->name('preferences.calendar');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Setup
    $crud('setup/units', UnitController::class, 'units');
    $crud('setup/warehouses', WarehouseController::class, 'warehouses');
    $crud('setup/currencies', CurrencyController::class, 'currencies');
    $crud('setup/rates', ExchangeRateController::class, 'rates');
    $crud('setup/account-types', AccountTypeController::class, 'account-types');
    $crud('setup/item-groups', ItemCategoryController::class, 'item-categories');
    Route::get('setup/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('setup/company', [CompanyController::class, 'update'])->name('company.update');

    // Users, roles, audit log, documents
    $crud('users', UserController::class, 'users');
    $crud('roles', RoleController::class, 'roles');
    Route::get('audit-log', [AuditController::class, 'index'])->name('audit.index');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{id}', [DocumentController::class, 'show'])->name('documents.show')->whereNumber('id');
    Route::delete('documents/{id}', [DocumentController::class, 'destroy'])->name('documents.destroy')->whereNumber('id');

    // Accounts: customers, suppliers, banks, sarafi, shareholders, employees ...
    Route::get('chart-of-accounts', [ChartController::class, 'index'])->name('chart');
    Route::get('statement/{id}', [ChartController::class, 'statement'])->name('statement')->whereNumber('id');
    $crud('accounts/{type}', AccountController::class, 'accounts');

    // Products and raw materials
    $crud('items/{type}', ItemController::class, 'items');

    // Money and day book
    Route::get('money', [MoneyController::class, 'index'])->name('money.index');
    Route::get('money/profit', [MoneyController::class, 'profitForm'])->name('money.profit');
    Route::post('money/profit', [MoneyController::class, 'profitStore'])->name('money.profit.store');
    Route::get('money/{kind}/create', [MoneyController::class, 'create'])->name('money.create');
    Route::post('money/{kind}', [MoneyController::class, 'store'])->name('money.store');
    Route::delete('money/{id}', [MoneyController::class, 'destroy'])->name('money.destroy')->whereNumber('id');
    Route::get('day-book', [JournalController::class, 'index'])->name('journal.index');
    Route::get('day-book/create', [JournalController::class, 'create'])->name('journal.create');
    Route::post('day-book', [JournalController::class, 'store'])->name('journal.store');
    Route::delete('day-book/{id}', [JournalController::class, 'destroy'])->name('journal.destroy')->whereNumber('id');
});
