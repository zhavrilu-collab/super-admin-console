<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\Sync\LegalSaasSyncDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalApplicationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_registers_legal_application_with_sync_driver(): void
    {
        $this->seed(\Database\Seeders\AdminConsoleSeeder::class);

        $application = Application::query()->where('slug', 'legal-saas')->first();

        $this->assertNotNull($application);
        $this->assertSame('SuperSkyLaw', $application->name);
        $this->assertSame(LegalSaasSyncDriver::class, $application->sync_driver);
        $this->assertSame('http://127.0.0.1:8006', $application->api_base_url);
    }

    public function test_legal_default_subscription_plans_and_features_are_seeded(): void
    {
        $application = Application::query()->create([
            'name' => 'SuperSkyLaw',
            'slug' => 'legal-saas',
            'description' => 'Test',
        ]);

        app(SubscriptionPlanService::class)->seedLegalDefaults($application);

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'basic',
            'name' => 'Basic',
        ]);

        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'matter_limit',
        ]);

        $basic = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'basic')
            ->first();

        $this->assertSame(50, $basic?->features['matter_limit'] ?? null);
        $this->assertFalse((bool) ($basic?->features['client_portal'] ?? true));

        $premium = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'premium')
            ->first();

        $this->assertTrue((bool) ($premium?->features['e_invoice'] ?? false));
    }
}
