<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_dunning_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('suspend_after_at');
            $table->unsignedTinyInteger('reminder_stage')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'resolved_at']);
            $table->index('suspend_after_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_dunning_cases');
    }
};
