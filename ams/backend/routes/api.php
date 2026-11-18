<?php
use App\Http\Controllers\{AssetController, AuthController, CrudController, DashboardController};
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me/assets', [AssetController::class, 'mine']);

    $staff = 'role:Super Admin|IT Admin|IT Staff';
    $admin = 'role:Super Admin|IT Admin';
    $res   = 'employees|departments|locations|categories|warranties|maintenances';

    Route::middleware('role:Super Admin|IT Admin|Manager')->get('dashboard/stats', [DashboardController::class, 'stats']);

    Route::middleware($staff)->group(function () {
        Route::get('assets', [AssetController::class, 'index']);
        Route::get('assets/{asset}', [AssetController::class, 'show']);
        Route::put('assets/{asset}', [AssetController::class, 'update']);
    });
    Route::middleware($admin)->group(function () use ($res) {
        Route::post('assets', [AssetController::class, 'store']);
        Route::delete('assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('assets/{asset}/assign', [AssetController::class, 'assign']);
        Route::post('assets/{asset}/return', [AssetController::class, 'return']);
        Route::post('{resource}', [CrudController::class, 'store'])->where('resource', $res);
        Route::put('{resource}/{id}', [CrudController::class, 'update'])->where('resource', $res);
        Route::delete('{resource}/{id}', [CrudController::class, 'destroy'])->where('resource', $res);
    });
    // master data (read): admins, IT staff and managers
    Route::middleware('role:Super Admin|IT Admin|IT Staff|Manager')->get('{resource}', [CrudController::class, 'index'])->where('resource', $res);

    // user & role management (Super Admin only)
    Route::middleware('role:Super Admin')->group(function () {
        Route::get('users', fn() => \App\Models\User::with('roles')->paginate(50));
        Route::post('users/{user}/role', function (\App\Models\User $user, \Illuminate\Http\Request $r) {
            $user->syncRoles($r->validate(['role' => 'required|exists:roles,name'])['role']);
            return $user->load('roles');
        });
    });
});
