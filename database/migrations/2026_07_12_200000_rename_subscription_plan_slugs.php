<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $planMap = [
            'free' => 'basic',
            'premium' => 'standard',
            'business' => 'premium',
        ];

        foreach ($planMap as $from => $to) {
            DB::table('tenants')->where('plan', $from)->update(['plan' => $to]);
        }

        foreach ($planMap as $from => $to) {
            DB::table('subscription_plans')->where('slug', $from)->update(['slug' => $to]);
        }
    }

    public function down(): void
    {
        $planMap = [
            'basic' => 'free',
            'standard' => 'premium',
            'premium' => 'business',
        ];

        foreach ($planMap as $from => $to) {
            DB::table('tenants')->where('plan', $from)->update(['plan' => $to]);
        }

        foreach ($planMap as $from => $to) {
            DB::table('subscription_plans')->where('slug', $from)->update(['slug' => $to]);
        }
    }
};
