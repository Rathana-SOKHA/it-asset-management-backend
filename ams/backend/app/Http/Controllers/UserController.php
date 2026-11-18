<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return User::with(['roles', 'employee.department'])->latest()->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'is_active' => 'sometimes|boolean',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = new User($data);
            $user->is_active = $data['is_active'] ?? true;
            $user->save();
            $user->assignRole($data['role']);

            return $user;
        });

        return response()->json($user->load('roles', 'employee.department'), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$user->id,
            'employee_id' => 'sometimes|nullable|integer|exists:employees,id',
            'is_active' => 'sometimes|boolean',
        ]);

        abort_if(
            $user->is($request->user()) && array_key_exists('is_active', $data) && ! filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN),
            422,
            'You cannot deactivate your own account.',
        );
        $user->update($data);

        if (array_key_exists('is_active', $data) && ! $user->is_active) {
            $user->tokens()->delete();
        }

        return $user->fresh()->load('roles', 'employee.department');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');
        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        return response()->noContent();
    }

    public function assignRole(Request $request, User $user)
    {
        $roleName = $request->validate(['role' => 'required|string|exists:roles,name'])['role'];
        abort_if($user->is($request->user()) && $roleName !== 'Super Admin', 422, 'You cannot remove your own administrator role.');
        $user->syncRoles($roleName);

        return $user->load('roles');
    }

    public function roles()
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function permissions()
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function syncRolePermissions(Request $request, Role $role)
    {
        $permissions = $request->validate([
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ])['permissions'];
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    public function resetPassword(Request $request, User $user)
    {
        $password = $request->validate(['password' => 'required|string|min:8'])['password'];
        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();

        return response()->noContent();
    }
}
