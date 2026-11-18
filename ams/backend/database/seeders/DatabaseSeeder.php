<?php

namespace Database\Seeders;

use App\Models\{Asset, Category, Department, Employee, Location, User, Warranty};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Administrator', 'IT Admin', 'IT Staff', 'Manager', 'Employee'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        foreach (['Laptop', 'Desktop', 'Server', 'Printer', 'Network Device'] as $categoryName) {
            Category::firstOrCreate(['name' => $categoryName]);
        }

        $department = Department::firstOrCreate(['name' => 'Finance']);
        $employee = Employee::firstOrCreate(
            ['email' => 'sokha@example.com'],
            ['name' => 'Sokha', 'department_id' => $department->id, 'position' => 'Accountant'],
        );
        $location = Location::firstOrCreate(['name' => 'HQ Office', 'type' => 'office']);

        foreach ([
            'Administrator' => 'admin',
            'IT Admin' => 'itadmin',
            'IT Staff' => 'itstaff',
            'Manager' => 'manager',
            'Employee' => 'employee',
        ] as $roleName => $loginName) {
            $user = User::firstOrCreate(
                ['email' => "$loginName@example.com"],
                [
                    'name' => $roleName,
                    'password' => Hash::make('password'),
                    'employee_id' => $roleName === 'Employee' ? $employee->id : null,
                ],
            );
            $user->assignRole($roleName);
        }

        $asset = Asset::firstOrCreate(
            ['asset_tag' => 'LT-0001'],
            [
                'name' => 'Dell Latitude 5440',
                'category_id' => 1,
                'brand' => 'Dell',
                'location_id' => $location->id,
                'status' => 'active',
            ],
        );

        Warranty::firstOrCreate(
            ['asset_id' => $asset->id],
            ['vendor' => 'Dell', 'start_date' => now()->subYears(2), 'end_date' => now()->addDays(20)],
        );

        if (!$asset->assignments()->exists()) {
            $asset->assignments()->create([
                'employee_id' => $employee->id,
                'assigned_at' => now(),
            ]);
        }
    }
}
