<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('title');
            $table->string('favicon_path')->nullable()->after('logo_path');
        });

        DB::table('company_settings')->whereNull('title')->update(['title' => 'Meridian']);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['title', 'logo_path', 'favicon_path']);
        });
    }
};
