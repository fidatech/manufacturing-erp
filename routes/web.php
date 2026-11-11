<?php

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Accounts\ChartController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Assets\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\HrRecordController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockDocController;
use App\Http\Controllers\Items\ItemController;
use App\Http\Controllers\Lab\LabTestController;
use App\Http\Controllers\Money\JournalController;
use App\Http\Controllers\Money\MoneyController;
use App\Http\Controllers\Money\PettyCashController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\Production\ProductionController;
use App\Http\Controllers\Production\RecipeController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Trade\PriceListController;
use App\Http\Controllers\Trade\PurchaseController;
use App\Http\Controllers\Trade\SaleController;
use App\Http\Controllers\Setup\AccountTypeController;
use App\Http\Controllers\Setup\CompanyController;
use App\Http\Controllers\Setup\CurrencyController;
use App\Http\Controllers\Setup\ExchangeRateController;
use App\Http\Controllers\Setup\ItemCategoryController;
use App\Http\Controllers\Setup\LookupController;
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
    Route::get('setup/company/logo', [CompanyController::class, 'logo'])->name('company.logo');
    $crud('lists/{type}', LookupController::class, 'lookups');

    // Users, roles, audit log, documents
    $crud('users', UserController::class, 'users');
    $crud('roles', RoleController::class, 'roles');
    Route::get('permissions', [RoleController::class, 'permissions'])->name('permissions');
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

    // Manufacturing: recipes and production batches
    $crud('recipes', RecipeController::class, 'recipes');
    Route::get('production', [ProductionController::class, 'index'])->name('production.index');
    Route::get('production/create', [ProductionController::class, 'create'])->name('production.create');
    Route::get('production/report', [ProductionController::class, 'report'])->name('production.report');
    Route::post('production', [ProductionController::class, 'store'])->name('production.store');
    Route::get('production/{id}', [ProductionController::class, 'show'])->name('production.show')->whereNumber('id');
    Route::post('production/{id}/cancel', [ProductionController::class, 'cancel'])->name('production.cancel')->whereNumber('id');

    // Laboratory and quality control
    Route::get('lab', [LabTestController::class, 'index'])->name('lab.index');
    Route::get('lab/create', [LabTestController::class, 'create'])->name('lab.create');
    Route::post('lab', [LabTestController::class, 'store'])->name('lab.store');
    Route::get('lab/{id}', [LabTestController::class, 'show'])->name('lab.show')->whereNumber('id');
    Route::delete('lab/{id}', [LabTestController::class, 'destroy'])->name('lab.destroy')->whereNumber('id');

    // Purchases: orders, bills, goods received, returns
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{id}', [PurchaseController::class, 'show'])->name('purchases.show')->whereNumber('id');
    Route::post('purchases/{id}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive')->whereNumber('id');
    Route::post('purchases/{id}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->whereNumber('id');

    // Sales: quotations, bills, delivery, returns
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{id}', [SaleController::class, 'show'])->name('sales.show')->whereNumber('id');
    Route::get('sales/{id}/print', [SaleController::class, 'print'])->name('sales.print')->whereNumber('id');
    Route::post('sales/{id}/delivery', [SaleController::class, 'delivery'])->name('sales.delivery')->whereNumber('id');
    Route::post('sales/{id}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel')->whereNumber('id');

    $crud('price-lists', PriceListController::class, 'price-lists');

    // Stock
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/batches', [StockController::class, 'batches'])->name('stock.batches');
    Route::get('stock/card/{id}', [StockController::class, 'card'])->name('stock.card')->whereNumber('id');
    Route::get('stock/{type}', [StockDocController::class, 'index'])->name('stock-docs.index')->whereIn('type', StockDocController::TYPES);
    Route::get('stock/{type}/create', [StockDocController::class, 'create'])->name('stock-docs.create')->whereIn('type', StockDocController::TYPES);
    Route::post('stock/{type}', [StockDocController::class, 'store'])->name('stock-docs.store')->whereIn('type', StockDocController::TYPES);
    Route::get('stock/{type}/{id}', [StockDocController::class, 'show'])->name('stock-docs.show')->whereIn('type', StockDocController::TYPES)->whereNumber('id');
    Route::post('stock/{type}/{id}/cancel', [StockDocController::class, 'cancel'])->name('stock-docs.cancel')->whereIn('type', StockDocController::TYPES)->whereNumber('id');

    // Assets, depreciation and amortization
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::get('assets/write-off/{kind}', [AssetController::class, 'writeOff'])->name('assets.write-off')->whereIn('kind', ['depreciation', 'amortization']);
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::post('assets/depreciate', [AssetController::class, 'depreciate'])->name('assets.depreciate');
    Route::post('assets/undo', [AssetController::class, 'undo'])->name('assets.undo');
    Route::post('assets/{id}/dispose', [AssetController::class, 'dispose'])->name('assets.dispose')->whereNumber('id');

    // Human resource
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    $crud('hr/{screen}', HrRecordController::class, 'hr');

    // Payroll
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
    Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{id}', [PayrollController::class, 'show'])->name('payroll.show')->whereNumber('id');
    Route::delete('payroll/{id}', [PayrollController::class, 'destroy'])->name('payroll.destroy')->whereNumber('id');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/profit-and-loss', [ReportController::class, 'profit'])->name('reports.profit');
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance');
    Route::get('reports/trial-balance', [ReportController::class, 'trial'])->name('reports.trial');
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash');
    Route::get('reports/balances', [ReportController::class, 'balances'])->name('reports.balances');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
    Route::get('reports/people/{group}', [ReportController::class, 'parties'])->name('reports.parties');

    // Money and day book
    Route::get('money', [MoneyController::class, 'index'])->name('money.index');
    Route::get('petty-cash', PettyCashController::class)->name('petty-cash');
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
