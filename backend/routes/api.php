<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CashierReportController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ReturnRefundController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPreferenceController;
use App\Http\Controllers\Api\WarehouseReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/settings/public', [SystemSettingController::class, 'getPublic']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::middleware('can:reports.view')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/sales-chart', [DashboardController::class, 'salesChart']);
        Route::get('/dashboard/top-products', [DashboardController::class, 'topProducts']);
        Route::get('/dashboard/sales-by-payment', [DashboardController::class, 'salesByPaymentMethod']);
        Route::get('/dashboard/inventory-status', [DashboardController::class, 'inventoryStatus']);
        Route::get('/dashboard/sales-by-cashier', [DashboardController::class, 'salesByCashier']);
        Route::get('/dashboard/sales-by-hour', [DashboardController::class, 'salesByHour']);
        Route::get('/dashboard/alerts', [DashboardController::class, 'alerts']);
        Route::get('/reports/sales/export', [ReportExportController::class, 'sales']);
        Route::get('/reports/inventory-movements/export', [ReportExportController::class, 'inventoryMovements']);
        Route::get('/reports/inventory-stock/export', [ReportExportController::class, 'inventoryStock']);
    });

    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:users.create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:users.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');
    Route::get('/roles', [UserController::class, 'roles'])->middleware('can:users.view');

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:users.view');
    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])->middleware('can:users.view');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('can:users.create');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('can:users.edit');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('can:users.delete');

    Route::get('/roles-list', [RoleController::class, 'index'])->middleware('can:users.view');
    Route::get('/roles-list/{role}', [RoleController::class, 'show'])->middleware('can:users.view');
    Route::post('/roles-list', [RoleController::class, 'store'])->middleware('can:users.create');
    Route::put('/roles-list/{role}', [RoleController::class, 'update'])->middleware('can:users.edit');
    Route::delete('/roles-list/{role}', [RoleController::class, 'destroy'])->middleware('can:users.delete');
    Route::post('/roles-list/{role}/sync-permissions', [RoleController::class, 'syncPermissions'])->middleware('can:users.edit');

    Route::get('/categories', [CategoryController::class, 'index'])->middleware('can:categories.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:categories.create');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('can:categories.view');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('can:categories.edit');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:categories.delete');

    Route::get('/products', [ProductController::class, 'index'])->middleware('can:products.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('can:products.view');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('can:products.edit');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:products.delete');

    Route::get('/sales', [SaleController::class, 'index'])->middleware('can:sales.view');
    Route::post('/sales', [SaleController::class, 'store'])->middleware('can:sales.create');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('can:sales.view');
    Route::put('/sales/{sale}', [SaleController::class, 'update'])->middleware('can:sales.edit');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->middleware('can:sales.delete');

    Route::middleware('can:sales.view')->group(function () {
        Route::get('/cashier-reports/cashiers', [CashierReportController::class, 'cashiers']);
        Route::get('/cashier-reports/sales-overview', [CashierReportController::class, 'salesOverview']);
        Route::get('/cashier-reports/sales', [CashierReportController::class, 'sales']);
        Route::get('/cashier-reports/voided', [CashierReportController::class, 'voided']);
        Route::get('/cashier-reports/daily-summary', [CashierReportController::class, 'dailySummary']);
        Route::get('/cashier-reports/products', [CashierReportController::class, 'products']);
    });

    Route::middleware('can:cash-registers.view')->group(function () {
        Route::get('/cashier-reports/shifts', [CashierReportController::class, 'shifts']);
        Route::get('/cashier-reports/closures', [CashierReportController::class, 'closures']);
        Route::get('/cashier-reports/discrepancies', [CashierReportController::class, 'discrepancies']);
        Route::get('/cashier-reports/cash-movements', [CashierReportController::class, 'cashMovements']);
    });

    Route::post('/cash-movements', [CashMovementController::class, 'store'])->middleware('can:cash-registers.open');

    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->middleware('can:inventory.low-stock');
    Route::get('/inventory/report', [InventoryController::class, 'report'])->middleware('can:inventory.reports');

    Route::middleware('can:inventory.reports')->group(function () {
        Route::get('/warehouse-reports/movements', [WarehouseReportController::class, 'movements']);
        Route::get('/warehouse-reports/low-stock', [WarehouseReportController::class, 'lowStock']);
        Route::get('/warehouse-reports/inbound', [WarehouseReportController::class, 'inbound']);
        Route::get('/warehouse-reports/losses', [WarehouseReportController::class, 'losses']);
        Route::get('/warehouse-reports/valuation', [WarehouseReportController::class, 'valuation']);
        Route::get('/warehouse-reports/slow-moving', [WarehouseReportController::class, 'slowMoving']);
        Route::get('/warehouse-reports/supplier-purchases', [WarehouseReportController::class, 'supplierPurchases']);
    });
    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('can:inventory.view');
    Route::post('/inventory', [InventoryController::class, 'store'])->middleware('can:inventory.create');
    Route::get('/inventory/{movement}', [InventoryController::class, 'show'])->middleware('can:inventory.view');

    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('can:suppliers.create');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('can:suppliers.view');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('can:suppliers.edit');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('can:suppliers.delete');

    Route::post('/returns/{returnRefund}/process', [ReturnRefundController::class, 'process'])->middleware('can:returns.process');
    Route::get('/returns', [ReturnRefundController::class, 'index'])->middleware('can:returns.view');
    Route::post('/returns', [ReturnRefundController::class, 'store'])->middleware('can:returns.create');
    Route::get('/returns/{returnRefund}', [ReturnRefundController::class, 'show'])->middleware('can:returns.view');
    Route::put('/returns/{returnRefund}', [ReturnRefundController::class, 'update'])->middleware('can:returns.edit');
    Route::delete('/returns/{returnRefund}', [ReturnRefundController::class, 'destroy'])->middleware('can:returns.delete');

    Route::post('/discounts/validate', [DiscountController::class, 'validate'])->middleware('can:discounts.validate');
    Route::get('/discounts', [DiscountController::class, 'index'])->middleware('can:discounts.view');
    Route::post('/discounts', [DiscountController::class, 'store'])->middleware('can:discounts.create');
    Route::get('/discounts/{discount}', [DiscountController::class, 'show'])->middleware('can:discounts.view');
    Route::put('/discounts/{discount}', [DiscountController::class, 'update'])->middleware('can:discounts.edit');
    Route::delete('/discounts/{discount}', [DiscountController::class, 'destroy'])->middleware('can:discounts.delete');

    Route::get('/cash-registers/current', [CashRegisterController::class, 'getCurrent'])->middleware('can:cash-registers.view');
    Route::post('/cash-registers/{cashRegister}/open', [CashRegisterController::class, 'open'])->middleware('can:cash-registers.open');
    Route::post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close'])->middleware('can:cash-registers.close');
    Route::get('/cash-registers', [CashRegisterController::class, 'index'])->middleware('can:cash-registers.view');
    Route::post('/cash-registers', [CashRegisterController::class, 'store'])->middleware('can:cash-registers.create');
    Route::get('/cash-registers/{cashRegister}', [CashRegisterController::class, 'show'])->middleware('can:cash-registers.view');
    Route::put('/cash-registers/{cashRegister}', [CashRegisterController::class, 'update'])->middleware('can:cash-registers.edit');
    Route::delete('/cash-registers/{cashRegister}', [CashRegisterController::class, 'destroy'])->middleware('can:cash-registers.delete');

    Route::get('/settings/get', [SystemSettingController::class, 'get'])->middleware('can:settings.view');
    Route::post('/settings/set', [SystemSettingController::class, 'set'])->middleware('can:settings.edit');
    Route::get('/settings/group', [SystemSettingController::class, 'getByGroup'])->middleware('can:settings.view');
    Route::get('/me/preferences', [UserPreferenceController::class, 'show']);
    Route::put('/me/preferences', [UserPreferenceController::class, 'update']);

    Route::get('/settings/schema', [SystemSettingController::class, 'schema']);
    Route::put('/settings/bulk', [SystemSettingController::class, 'bulkUpdate']);
    Route::post('/settings/reset', [SystemSettingController::class, 'reset']);
    Route::get('/settings/history', [SystemSettingController::class, 'history'])->middleware('can:settings.view');
    Route::post('/settings/logo', [SystemSettingController::class, 'uploadLogo'])->middleware('can:settings.edit');
    Route::delete('/settings/logo', [SystemSettingController::class, 'deleteLogo'])->middleware('can:settings.edit');
    Route::get('/settings', [SystemSettingController::class, 'index'])->middleware('can:settings.view');
    Route::post('/settings', [SystemSettingController::class, 'store'])->middleware('can:settings.edit');
    Route::get('/settings/{setting}', [SystemSettingController::class, 'show'])->middleware('can:settings.view');
    Route::put('/settings/{setting}', [SystemSettingController::class, 'update'])->middleware('can:settings.edit');
    Route::delete('/settings/{setting}', [SystemSettingController::class, 'destroy'])->middleware('can:settings.edit');

    Route::middleware('can:backups.manage')->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups', [BackupController::class, 'store']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restoreFile']);
        Route::get('/backups/{filename}', [BackupController::class, 'download']);
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy']);
    });
});
