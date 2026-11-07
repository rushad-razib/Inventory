<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('currency', 3)->default('BDT');
            $table->unsignedInteger('po_approval_threshold')->default(20000);
            $table->unsignedInteger('adjustment_approval_threshold')->default(5000);
            $table->unsignedInteger('points_earn_per')->default(100);
            $table->unsignedInteger('point_redeem_value')->default(1);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('department_id')->nullable()->after('password')->constrained('departments')->nullOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->after('department_id')->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('warehouse_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedTinyInteger('supply_mode');
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->unsignedTinyInteger('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unit_of_measure')->default('each');
            $table->unsignedInteger('sell_price');
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->foreignUuid('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->unsignedInteger('agreed_unit_cost');
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('mobile')->unique();
            $table->string('name')->nullable();
            $table->integer('points_balance')->default(0);
            $table->timestamps();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('imageable');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedInteger('byte_size');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->uuidMorphs('auditable');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('images');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('company_settings');
    }
};
