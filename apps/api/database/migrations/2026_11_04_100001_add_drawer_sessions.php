<?php

use App\Enums\CashLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawer_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedTinyInteger('status');
            $table->unsignedInteger('opening_amount');
            $table->unsignedInteger('counted_amount')->nullable();
            $table->integer('expected_amount')->nullable();
            $table->integer('variance')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('open_key')->nullable()->unique();
            $table->timestamps();
            $table->index('status');
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->foreignUuid('drawer_session_id')
                ->nullable()
                ->after('source_id')
                ->constrained('drawer_sessions')
                ->nullOnDelete();
        });

        DB::table('cash_entries')
            ->where('location', CashLocation::Till->value)
            ->update(['location' => CashLocation::Cash->value]);

        $permission = Permission::findOrCreate('cash.manage', 'web');
        foreach (['Admin', 'Manager'] as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();
            if ($role !== null) {
                $role->givePermissionTo($permission);
            }
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('cash_entries')
            ->where('location', CashLocation::Cash->value)
            ->update(['location' => CashLocation::Till->value]);

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drawer_session_id');
        });

        Schema::dropIfExists('drawer_sessions');
    }
};
