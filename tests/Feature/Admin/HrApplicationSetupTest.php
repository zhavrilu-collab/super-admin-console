<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Services\Admin\Sync\HrSaasSyncDriver;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrApplicationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_registers_hr_application_with_sync_driver(): void
    {
        $this->seed(\Database\Seeders\AdminConsoleSeeder::class);

        $application = Application::query()->where('slug', 'hr-saas')->first();

        $this->assertNotNull($application);
        $this->assertSame('SuperSkyCrew', $application->name);
        $this->assertSame(HrSaasSyncDriver::class, $application->sync_driver);
        $this->assertSame('http://127.0.0.1:8004', $application->api_base_url);
        $this->assertSame('SuperSkyClub', Application::query()->where('slug', 'udruga-saas')->value('name'));
        $this->assertSame('SuperSkyLaw', Application::query()->where('slug', 'legal-saas')->value('name'));
        $this->assertNull(Application::query()->where('slug', 'opg-saas')->first());
    }

    public function test_hr_default_subscription_plans_and_features_are_seeded(): void
    {
        $application = Application::query()->create([
            'name' => 'HR SaaS',
            'slug' => 'hr-saas',
            'description' => 'Test',
        ]);

        app(SubscriptionPlanService::class)->seedHrDefaults($application);

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'basic',
            'name' => 'Osnovni',
        ]);

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'premium',
            'name' => 'Premium',
        ]);

        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'employee_limit',
        ]);
        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'clock_mobile',
        ]);

        $default = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('is_default', true)
            ->value('slug');

        $this->assertSame('basic', $default);

        $basic = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('slug', 'basic')
            ->first();

        $this->assertSame(10, $basic?->features['employee_limit'] ?? null);
        $this->assertTrue((bool) ($basic?->features['clock_mobile'] ?? false));
        $this->assertFalse((bool) ($basic?->features['clock_kiosk'] ?? true));
    }

    public function test_hr_application_seeder_is_safe_to_run_on_production(): void
    {
        config([
            'saas_applications.applications.hr-saas.base_url' => 'https://crew.superskytech.com',
            'saas_applications.applications.hr-saas.api_key' => 'prod-hr-key',
        ]);

        $this->seed(\Database\Seeders\HrApplicationSeeder::class);

        $application = Application::query()->where('slug', 'hr-saas')->first();

        $this->assertNotNull($application);
        $this->assertSame('SuperSkyCrew', $application->name);
        $this->assertSame(HrSaasSyncDriver::class, $application->sync_driver);
        $this->assertSame('https://crew.superskytech.com', $application->api_base_url);
        $this->assertSame('prod-hr-key', $application->api_sync_key);
        $this->assertSame(0, \App\Models\Tenant::query()->count());
    }
}
