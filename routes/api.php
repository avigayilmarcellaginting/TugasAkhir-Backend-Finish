<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TenantController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
    });
});

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::group(['prefix' => 'branches'], function () {
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
    });

    Route::group(['prefix' => 'users'], function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/permissions', [UserController::class, 'getPermissions']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
    });

    Route::group(['prefix' => 'shifts'], function () {
        Route::get('/current', [ShiftController::class, 'current']);
        Route::post('/start', [ShiftController::class, 'start']);
        Route::post('/end', [ShiftController::class, 'end']);
    });

    Route::group(['prefix' => 'inventory'], function () {
        Route::get('/categories', [InventoryController::class, 'getCategories']);
        Route::post('/categories', [InventoryController::class, 'storeCategory']);
        Route::put('/categories/{category}', [InventoryController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [InventoryController::class, 'deleteCategory']);

        Route::get('/products', [InventoryController::class, 'getProducts']);
        Route::post('/products', [InventoryController::class, 'storeProduct']);
        Route::put('/products/{product}', [InventoryController::class, 'updateProduct']);
        Route::delete('/products/{product}', [InventoryController::class, 'deleteProduct']);
        Route::post('/products/{product}/stock', [InventoryController::class, 'updateStock']);
    });

    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('promotions', PromotionController::class);
    Route::apiResource('expenses', ExpenseController::class);

    Route::group(['prefix' => 'reports'], function () {
        Route::get('/profit-loss', [ReportController::class, 'profitAndLoss']);
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet']);
        Route::get('/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('/shifts', [ReportController::class, 'shiftLog']);
    });

    Route::post('/tenant/upgrade', [TenantController::class, 'upgrade']);

    Route::get('/dashboard/admin', [DashboardController::class, 'admin']);
});
