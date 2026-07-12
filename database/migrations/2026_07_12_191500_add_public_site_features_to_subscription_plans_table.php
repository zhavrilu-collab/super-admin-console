<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('subdomain')->default(false)->after('is_default');
            $table->boolean('custom_domain')->default(false)->after('subdomain');
            $table->boolean('editable_sections')->default(false)->after('custom_domain');
            $table->boolean('cookie_banner')->default(false)->after('editable_sections');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'subdomain',
                'custom_domain',
                'editable_sections',
                'cookie_banner',
            ]);
        });
    }
};
