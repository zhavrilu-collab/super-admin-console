<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'standard')
            ->update(['name' => 'Standardni']);
    }

    public function down(): void
    {
        DB::table('subscription_plans')
            ->where('slug', 'standard')
            ->update(['name' => 'Profesionalni']);
    }
};
