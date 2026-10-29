<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedTinyInteger('status');
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_on')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('tax');
            $table->unsignedInteger('total');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('unit_cost');
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('purchase_order_id');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost');
            $table->timestamps();
        });

        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->uuidMorphs('source');
            $table->unsignedTinyInteger('status');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('amount_paid')->default(0);
            $table->date('due_on');
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_id']);
        });

        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
