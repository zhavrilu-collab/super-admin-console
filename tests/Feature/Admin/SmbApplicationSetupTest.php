<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SubscriptionPlan;
use App\Services\Admin\Sync\SmbSaasSyncDriver;
use App\Services\Admin\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmbApplicationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_registers_smb_application_with_sync_driver(): void
    {
        $this->seed(\Database\Seeders\AdminConsoleSeeder::class);

        $application = Application::query()->where('slug', 'smb-saas')->first();

        $this->assertNotNull($application);
        $this->assertSame(SmbSaasSyncDriver::class, $application->sync_driver);
        $this->assertSame('http://127.0.0.1:8002', $application->api_base_url);
    }

    public function test_smb_default_subscription_plans_are_seeded(): void
    {
        $application = Application::query()->create([
            'name' => 'SMB SaaS',
            'slug' => 'smb-saas',
            'description' => 'Test',
        ]);

        app(SubscriptionPlanService::class)->seedSmbDefaults($application);

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'basic',
            'name' => 'Starter',
        ]);

        $this->assertDatabaseHas('subscription_plans', [
            'application_id' => $application->id,
            'slug' => 'premium',
            'name' => 'Enterprise',
        ]);

        $default = SubscriptionPlan::query()
            ->where('application_id', $application->id)
            ->where('is_default', true)
            ->value('slug');

        $this->assertSame('basic', $default);
    }
}
