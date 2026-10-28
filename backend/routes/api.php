<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReturnRefundController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth:sanctum');
Route::get('/dashboard/top-products', [DashboardController::class, 'topProducts'])->middleware('auth:sanctum');
Route::get('/dashboard/sales-by-payment', [DashboardController::class, 'salesByPaymentMethod'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'roles']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);

    Route::get('/roles-list', [RoleController::class, 'index']);
    Route::get('/roles-list/{role}', [RoleController::class, 'show']);
    Route::post('/roles-list', [RoleController::class, 'store']);
    Route::put('/roles-list/{role}', [RoleController::class, 'update']);
    Route::delete('/roles-list/{role}', [RoleController::class, 'destroy']);
    Route::post('/roles-list/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::put('/sales/{sale}', [SaleController::class, 'update']);
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::get('/inventory/{movement}', [InventoryController::class, 'show']);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/report', [InventoryController::class, 'report']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

    Route::get('/returns', [ReturnRefundController::class, 'index']);
    Route::post('/returns', [ReturnRefundController::class, 'store']);
    Route::get('/returns/{returnRefund}', [ReturnRefundController::class, 'show']);
    Route::put('/returns/{returnRefund}', [ReturnRefundController::class, 'update']);
    Route::post('/returns/{returnRefund}/process', [ReturnRefundController::class, 'process']);
    Route::delete('/returns/{returnRefund}', [ReturnRefundController::class, 'destroy']);

    Route::get('/discounts', [DiscountController::class, 'index']);
    Route::post('/discounts', [DiscountController::class, 'store']);
    Route::get('/discounts/{discount}', [DiscountController::class, 'show']);
    Route::put('/discounts/{discount}', [DiscountController::class, 'update']);
    Route::delete('/discounts/{discount}', [DiscountController::class, 'destroy']);
    Route::post('/discounts/validate', [DiscountController::class, 'validate']);

    Route::get('/cash-registers', [CashRegisterController::class, 'index']);
    Route::post('/cash-registers', [CashRegisterController::class, 'store']);
    Route::get('/cash-registers/{cashRegister}', [CashRegisterController::class, 'show']);
    Route::put('/cash-registers/{cashRegister}', [CashRegisterController::class, 'update']);
    Route::post('/cash-registers/{cashRegister}/open', [CashRegisterController::class, 'open']);
    Route::post('/cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);
    Route::delete('/cash-registers/{cashRegister}', [CashRegisterController::class, 'destroy']);
    Route::get('/cash-registers/current', [CashRegisterController::class, 'getCurrent']);

    Route::get('/settings', [SystemSettingController::class, 'index']);
    Route::post('/settings', [SystemSettingController::class, 'store']);
    Route::get('/settings/{setting}', [SystemSettingController::class, 'show']);
    Route::put('/settings/{setting}', [SystemSettingController::class, 'update']);
    Route::delete('/settings/{setting}', [SystemSettingController::class, 'destroy']);
    Route::get('/settings/get', [SystemSettingController::class, 'get']);
    Route::post('/settings/set', [SystemSettingController::class, 'set']);
    Route::get('/settings/public', [SystemSettingController::class, 'getPublic']);
    Route::get('/settings/group', [SystemSettingController::class, 'getByGroup']);
});
