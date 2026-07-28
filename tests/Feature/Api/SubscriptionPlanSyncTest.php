<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class SubscriptionPlanSyncTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        app(ConsoleSettingsService::class)->set(
            ConsoleSettingsService::WEBHOOK_SECRET,
            'test-webhook-secret',
        );
    }

    public function test_sync_payload_includes_features_object_and_legacy_keys(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $response = $this->withToken('test-webhook-secret')
            ->getJson('/api/sync/subscription-plans?application=udruga-saas')
            ->assertOk()
            ->assertJsonPath('meta.application', 'udruga-saas');

        $standard = collect($response->json('data'))->firstWhere('slug', 'standard');

        $this->assertIsArray($standard);
        $this->assertIsArray($standard['features']);
        $this->assertTrue($standard['features']['subdomain']);
        $this->assertSame(500, $standard['features']['member_limit']);
        $this->assertTrue($standard['subdomain']);
        $this->assertSame(500, $standard['member_limit']);
    }
}
