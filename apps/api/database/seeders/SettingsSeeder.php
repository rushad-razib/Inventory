<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanySetting::query()->exists()) {
            return;
        }

        CompanySetting::query()->create([
            'name' => 'Hart & Field',
            'title' => 'Meridian',
            'currency' => 'BDT',
            'po_approval_threshold' => 20000,
            'adjustment_approval_threshold' => 5000,
            'points_earn_per' => 100,
            'point_redeem_value' => 1,
        ]);
    }
}
