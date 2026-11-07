<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->unsignedTinyInteger('status');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('points_redeemed');
            $table->unsignedInteger('cash_and_card_paid');
            $table->unsignedInteger('total');
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('shelf_total');
            $table->unsignedInteger('points_redeemed')->default(0);
            $table->unsignedInteger('cash_paid')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->timestamps();
            $table->unique(['sale_id', 'product_id']);
        });

        Schema::create('sale_line_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignUuid('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost');
            $table->unsignedTinyInteger('ownership');
            $table->timestamps();
        });

        Schema::create('tenders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedTinyInteger('type');
            $table->unsignedInteger('amount');
            $table->timestamps();
            $table->unique(['sale_id', 'type']);
        });

        Schema::create('customer_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('status');
            $table->timestamps();
            $table->index(['sale_id', 'status']);
        });

        Schema::create('customer_return_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
            $table->foreignUuid('sale_line_id')->constrained('sale_lines')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedTinyInteger('reason');
            $table->unsignedInteger('cash_amount');
            $table->unsignedInteger('points_restored');
            $table->unsignedInteger('points_clawed_back');
            $table->timestamps();
            $table->unique(['customer_return_id', 'sale_line_id']);
        });

        Schema::create('customer_point_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedTinyInteger('type');
            $table->integer('points');
            $table->uuidMorphs('source');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->index(['customer_id', 'occurred_at']);
        });

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('location');
            $table->unsignedTinyInteger('direction');
            $table->unsignedTinyInteger('type');
            $table->unsignedInteger('amount');
            $table->uuidMorphs('source');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->index(['location', 'occurred_at']);
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost');
            $table->foreignUuid('customer_return_id')->nullable()->constrained('customer_returns')->nullOnDelete();
            $table->unsignedTinyInteger('status');
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->unsignedInteger('credited_amount')->default(0)->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropColumn('credited_amount');
        });

        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('customer_point_entries');
        Schema::dropIfExists('customer_return_lines');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('sale_line_allocations');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
