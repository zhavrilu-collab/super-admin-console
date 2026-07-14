<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('cookie_banner');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('plan');
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_price_id')->nullable();
            $table->string('status');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
        });
    }
};
