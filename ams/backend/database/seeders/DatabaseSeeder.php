<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = ['Administrator', 'IT Admin', 'IT Staff', 'Department Manager', 'Employee', 'Management'];
        foreach ($roleNames as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $permissionNames = [
            'assets.view', 'assets.create', 'assets.update', 'assets.delete', 'assets.assign', 'assets.return',
            'employees.manage', 'departments.manage', 'locations.manage', 'suppliers.manage', 'purchases.manage',
            'maintenance.manage', 'warranties.manage', 'inventory-audits.manage', 'reports.view', 'users.manage',
            'permissions.manage', 'activity-logs.view', 'employees.view', 'departments.view', 'locations.view',
            'assets.catalog.view', 'suppliers.view', 'purchases.view', 'warranties.view',
        ];
        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $rolePermissions = [
            'Administrator' => $permissionNames,
            'IT Admin' => array_diff($permissionNames, ['users.manage', 'permissions.manage']),
            'IT Staff' => ['assets.view', 'assets.create', 'assets.update', 'assets.return', 'maintenance.manage', 'inventory-audits.manage', 'reports.view', 'employees.view', 'departments.view', 'locations.view', 'assets.catalog.view', 'warranties.view'],
            'Department Manager' => ['assets.view', 'reports.view', 'employees.view', 'departments.view', 'assets.catalog.view'],
            'Employee' => [],
            'Management' => ['assets.view', 'reports.view'],
        ];
        foreach ($rolePermissions as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }

        foreach (['Laptop', 'Desktop', 'Server', 'Printer', 'Network Device'] as $categoryName) {
            Category::firstOrCreate(['name' => $categoryName]);
        }
        $category = Category::where('name', 'Laptop')->firstOrFail();

        $department = Department::firstOrCreate(['name' => 'Finance']);
        $employee = Employee::firstOrCreate(
            ['email' => 'sokha@example.com'],
            ['name' => 'Sokha', 'department_id' => $department->id, 'position' => 'Accountant'],
        );
        $location = Location::firstOrCreate(['name' => 'HQ Office', 'type' => 'office']);

        foreach (
            [
                'Administrator' => 'admin',
                'IT Admin' => 'itadmin',
                'IT Staff' => 'itstaff',
                'Department Manager' => 'manager',
                'Employee' => 'employee',
                'Management' => 'management',
            ] as $roleName => $loginName
        ) {
            $legacyEmail = "$loginName@example.com";
            $email = "$loginName@gmail.com";

            $legacyUser = User::where('email', $legacyEmail)->first();
            if ($legacyUser && ! User::where('email', $email)->exists()) {
                $legacyUser->email = $email;
                $legacyUser->save();
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $roleName,
                    'password' => Hash::make('@admin2026'),
                    'employee_id' => $roleName === 'Employee' ? $employee->id : null,
                    'is_active' => true,
                ],
            );
            $user->syncRoles($roleName);
        }

        $department->update(['manager_id' => User::where('email', 'manager@gmail.com')->value('id')]);

        $asset = Asset::firstOrCreate(
            ['asset_tag' => 'LT-0001'],
            [
                'name' => 'Dell Latitude 5440',
                'category_id' => $category->id,
                'brand' => 'Dell',
                'location_id' => $location->id,
                'status' => 'active',
            ],
        );

        Warranty::firstOrCreate(
            ['asset_id' => $asset->id],
            ['vendor' => 'Dell', 'start_date' => now()->subYears(2), 'end_date' => now()->addDays(20)],
        );

        if (! $asset->assignments()->exists()) {
            $asset->assignments()->create([
                'employee_id' => $employee->id,
                'assigned_at' => now(),
            ]);
        }
    }
}
