<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('cash_entry_id')->nullable()->constrained('cash_entries')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('supplier_id');
        });

        Schema::create('supplier_settlement_bills', function (Blueprint $table) {
            $table->foreignUuid('supplier_settlement_id')->constrained('supplier_settlements')->restrictOnDelete();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->primary(['supplier_settlement_id', 'supplier_bill_id']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->foreignUuid('supplier_return_id')->nullable()->constrained('supplier_returns')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('supplier_bill_id');
        });

        Schema::create('supplier_refund_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('supplier_return_id')->constrained('supplier_returns')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedTinyInteger('status');
            $table->foreignUuid('cash_entry_id')->nullable()->constrained('cash_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['supplier_return_id', 'status']);
        });

        Schema::create('supplier_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('amount_applied')->default(0);
            $table->uuidMorphs('source');
            $table->timestamps();
            $table->index('supplier_id');
        });

        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->unsignedInteger('credited_amount')->default(0)->after('unit_cost');
            $table->foreignUuid('sale_line_allocation_id')
                ->nullable()
                ->after('customer_return_id')
                ->constrained('sale_line_allocations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_line_allocation_id');
            $table->dropColumn('credited_amount');
        });

        Schema::dropIfExists('supplier_credits');
        Schema::dropIfExists('supplier_refund_receivables');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('supplier_settlement_bills');
        Schema::dropIfExists('supplier_settlements');
    }
};
