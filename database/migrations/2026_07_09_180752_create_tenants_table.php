<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('pending');
            $table->string('plan')->default('free');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'slug']);
            $table->unique(['application_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
