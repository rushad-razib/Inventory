<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('damaged')->default(0);
            $table->integer('in_transit')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('goods_receipt_id')->nullable();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedInteger('unit_cost')->default(0);
            $table->unsignedTinyInteger('ownership');
            $table->unsignedInteger('qty_received');
            $table->integer('qty_on_hand');
            $table->integer('qty_damaged')->default(0);
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id', 'ownership', 'received_at']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedTinyInteger('status');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('transfer_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['transfer_id', 'product_id']);
        });

        Schema::create('transfer_line_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_line_id')->constrained('transfer_lines')->cascadeOnDelete();
            $table->foreignUuid('source_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->foreignUuid('destination_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->unsignedTinyInteger('ownership');
            $table->unsignedInteger('unit_cost');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->unsignedTinyInteger('reason');
            $table->unsignedTinyInteger('status');
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->unsignedTinyInteger('type');
            $table->integer('quantity');
            $table->unsignedInteger('unit_cost');
            $table->uuidMorphs('source');
            $table->string('idempotency_key')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['warehouse_id', 'product_id', 'occurred_at']);
            $table->index('type');
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('operation');
            $table->string('key');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('response');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'operation', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('adjustments');
        Schema::dropIfExists('transfer_line_lots');
        Schema::dropIfExists('transfer_lines');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('stock_balances');
    }
};
