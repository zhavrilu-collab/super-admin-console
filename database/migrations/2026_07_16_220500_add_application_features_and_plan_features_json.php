<?php

use App\Models\Application;
use App\Models\ApplicationFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('type', 20)->default('boolean');
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['application_id', 'key']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('cookie_banner');
        });

        $this->backfillPlansAndCatalog();
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('features');
        });

        Schema::dropIfExists('application_features');
    }

    private function backfillPlansAndCatalog(): void
    {
        $catalog = [
            ['key' => 'member_limit', 'label' => 'Limit članova / korisnika', 'type' => 'limit', 'unit' => 'members', 'sort_order' => 10],
            ['key' => 'subdomain', 'label' => 'Poddomena', 'type' => 'boolean', 'unit' => null, 'sort_order' => 20],
            ['key' => 'custom_domain', 'label' => 'Vlastita domena', 'type' => 'boolean', 'unit' => null, 'sort_order' => 30],
            ['key' => 'editable_sections', 'label' => 'Uređivanje sekcija javnog weba', 'type' => 'boolean', 'unit' => null, 'sort_order' => 40],
            ['key' => 'cookie_banner', 'label' => 'Cookie banner', 'type' => 'boolean', 'unit' => null, 'sort_order' => 50],
        ];

        foreach (Application::query()->cursor() as $application) {
            foreach ($catalog as $feature) {
                ApplicationFeature::query()->updateOrCreate(
                    [
                        'application_id' => $application->id,
                        'key' => $feature['key'],
                    ],
                    [
                        'label' => $feature['label'],
                        'description' => null,
                        'type' => $feature['type'],
                        'unit' => $feature['unit'],
                        'sort_order' => $feature['sort_order'],
                    ],
                );
            }
        }

        foreach (SubscriptionPlan::query()->cursor() as $plan) {
            $features = [
                'member_limit' => $plan->member_limit,
                'subdomain' => (bool) $plan->subdomain,
                'custom_domain' => (bool) $plan->custom_domain,
                'editable_sections' => (bool) $plan->editable_sections,
                'cookie_banner' => (bool) $plan->cookie_banner,
            ];

            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }
};
