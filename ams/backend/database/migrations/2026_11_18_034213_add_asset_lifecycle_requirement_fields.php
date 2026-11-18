<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('employee_id');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('employee_number')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
        });

        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->date('expected_return_at')->nullable()->after('assigned_at');
            $table->timestamp('handover_confirmed_at')->nullable()->after('expected_return_at');
        });

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('type');
        });
        DB::table('maintenance_records')->whereNotNull('completed_at')->update(['status' => 'completed']);

        Schema::table('warranties', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('asset_id')->constrained()->nullOnDelete();
        });

        Schema::table('inventory_audit_items', function (Blueprint $table): void {
            $table->foreignId('actual_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('condition')->nullable();
        });

        Schema::table('attachments', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->change();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_id');
            $table->foreignId('asset_id')->nullable(false)->change();
        });

        Schema::table('inventory_audit_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actual_location_id');
            $table->dropColumn('condition');
        });

        Schema::table('warranties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->dropColumn(['expected_return_at', 'handover_confirmed_at']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['employee_number', 'phone']);
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
