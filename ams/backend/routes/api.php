<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me/assets', [AssetController::class, 'mine']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}', [NotificationController::class, 'markRead']);

    $staff = 'role:Super Admin|IT Admin|IT Staff';
    $admin = 'role:Super Admin|IT Admin';
    $assetReaders = 'role:Super Admin|IT Admin|IT Staff|Department Manager|Management';
    $res = 'employees|departments|locations|categories|brands|suppliers|purchases|warranties';

    Route::middleware(['role:Super Admin|IT Admin|Department Manager|Management', 'permission:reports.view'])->get('dashboard/stats', [DashboardController::class, 'stats']);

    Route::middleware($assetReaders)->group(function () {
        Route::middleware('permission:assets.view')->group(function () {
            Route::get('assets', [AssetController::class, 'index']);
            Route::get('assets/{asset}', [AssetController::class, 'show']);
        });
    });
    Route::middleware($staff)->group(function () {
        Route::post('assets', [AssetController::class, 'store'])->middleware('permission:assets.create');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->middleware('permission:assets.update');
        Route::post('assets/{asset}/return', [AssetController::class, 'return'])->middleware('permission:assets.return');
        Route::post('assets/{asset}/attachments', [AttachmentController::class, 'storeForAsset'])->middleware('permission:assets.update');
        Route::post('purchases/{purchase}/attachments', [AttachmentController::class, 'storeForPurchase'])->middleware('permission:purchases.manage');
    });
    Route::middleware([$assetReaders, 'permission:assets.view'])->get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::middleware($admin)->group(function () use ($res) {
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->middleware('permission:assets.delete');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->middleware('permission:assets.delete');
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->middleware('permission:assets.assign');
        Route::post('{resource}', [CrudController::class, 'store'])->where('resource', $res);
        Route::put('{resource}/{id}', [CrudController::class, 'update'])->where('resource', $res);
        Route::delete('{resource}/{id}', [CrudController::class, 'destroy'])->where('resource', $res);
    });
    Route::middleware('role:Super Admin|IT Admin|IT Staff|Department Manager')->group(function () use ($res) {
        Route::get('{resource}', [CrudController::class, 'index'])->where('resource', $res);
    });

    Route::middleware($staff)->group(function () {
        Route::get('maintenances', [CrudController::class, 'index'])->defaults('resource', 'maintenances');
        Route::post('maintenances', [CrudController::class, 'store'])->defaults('resource', 'maintenances');
        Route::put('maintenances/{id}', [CrudController::class, 'update'])->defaults('resource', 'maintenances');
    });
    Route::middleware($admin)->delete('maintenances/{id}', [CrudController::class, 'destroy'])->defaults('resource', 'maintenances');

    Route::middleware(['role:Super Admin|IT Admin', 'permission:activity-logs.view'])->get('activity-logs', [ActivityLogController::class, 'index']);

    Route::middleware([$staff, 'permission:inventory-audits.manage'])->group(function () {
        Route::apiResource('inventory-audits', InventoryAuditController::class);
        Route::post('inventory-audits/{inventoryAudit}/items', [InventoryAuditController::class, 'addItems']);
        Route::patch('inventory-audit-items/{inventoryAuditItem}', [InventoryAuditController::class, 'verifyItem']);
    });
    Route::middleware($assetReaders)->group(function () {
        Route::get('reports/assets.csv', [ReportController::class, 'assetsCsv'])->middleware('permission:reports.view');
    });

    // user & role management (Super Admin only)
    Route::middleware(['role:Super Admin', 'permission:users.manage'])->group(function () {
        Route::apiResource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/role', [UserController::class, 'assignRole']);
        Route::post('users/{user}/password', [UserController::class, 'resetPassword']);
        Route::get('roles', [UserController::class, 'roles']);
        Route::get('permissions', [UserController::class, 'permissions']);
        Route::put('roles/{role}/permissions', [UserController::class, 'syncRolePermissions']);
    });
});
