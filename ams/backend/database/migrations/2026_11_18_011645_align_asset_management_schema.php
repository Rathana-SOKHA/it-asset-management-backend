<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && ! Schema::hasTable('asset_categories')) {
            Schema::rename('categories', 'asset_categories');
        }

        if (Schema::hasTable('maintenances') && ! Schema::hasTable('maintenance_records')) {
            Schema::rename('maintenances', 'maintenance_records');
        }

        if (Schema::hasTable('role_has_permissions') && ! Schema::hasTable('role_permissions')) {
            Schema::rename('role_has_permissions', 'role_permissions');
        }

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_order_number')->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('status')->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('assets', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('assets', 'purchase_id')) {
                $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        Schema::create('asset_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_assignment_id')->constrained('asset_assignments')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at');
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number')->unique();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['inventory_audit_id', 'asset_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
        Schema::dropIfExists('asset_returns');

        Schema::table('assets', function (Blueprint $table) {
            foreach (['brand_id', 'supplier_id', 'purchase_id'] as $column) {
                if (Schema::hasColumn('assets', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('brands');

        if (Schema::hasTable('maintenance_records') && ! Schema::hasTable('maintenances')) {
            Schema::rename('maintenance_records', 'maintenances');
        }
        if (Schema::hasTable('asset_categories') && ! Schema::hasTable('categories')) {
            Schema::rename('asset_categories', 'categories');
        }
        if (Schema::hasTable('role_permissions') && ! Schema::hasTable('role_has_permissions')) {
            Schema::rename('role_permissions', 'role_has_permissions');
        }
    }
};
