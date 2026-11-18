<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->timestamps(); });
        Schema::create('employees', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('position')->nullable(); $t->string('status')->default('active'); $t->timestamps();
        });
        Schema::table('users', fn(Blueprint $t) => $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete());
        Schema::create('locations', function (Blueprint $t) {
            $t->id(); $t->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->enum('type', ['office', 'building', 'floor', 'room']); $t->string('name'); $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->timestamps(); });
        Schema::create('assets', function (Blueprint $t) {
            $t->id(); $t->string('asset_tag')->unique(); $t->string('name');
            $t->foreignId('category_id')->constrained(); $t->string('brand')->nullable();
            $t->string('model')->nullable(); $t->string('serial_no')->nullable();
            $t->enum('status', ['active', 'damaged', 'in_repair', 'retired'])->default('active');
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('purchase_date')->nullable(); $t->decimal('purchase_cost', 12, 2)->nullable(); $t->timestamps();
        });
        Schema::create('asset_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('assigned_at'); $t->timestamp('returned_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('warranties', function (Blueprint $t) {
            $t->id(); $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->string('vendor')->nullable(); $t->date('start_date'); $t->date('end_date'); $t->timestamps();
        });
        Schema::create('maintenances', function (Blueprint $t) {
            $t->id(); $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['repair', 'scheduled']); $t->text('description')->nullable(); $t->decimal('cost', 12, 2)->nullable();
            $t->date('scheduled_at')->nullable(); $t->date('completed_at')->nullable();
            $t->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['maintenances','warranties','asset_assignments','assets','categories','locations'] as $t) Schema::dropIfExists($t);
        Schema::table('users', fn(Blueprint $t) => $t->dropConstrainedForeignId('employee_id'));
        Schema::dropIfExists('employees'); Schema::dropIfExists('departments');
    }
};
