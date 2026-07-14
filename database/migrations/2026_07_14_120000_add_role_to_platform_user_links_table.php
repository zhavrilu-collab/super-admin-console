<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_user_links', function (Blueprint $table) {
            $table->string('role', 32)->default('member')->after('external_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('platform_user_links', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
