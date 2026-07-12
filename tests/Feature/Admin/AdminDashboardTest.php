<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_dashboard_shows_tenant_statistics(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-1',
            'name' => 'Aktivna udruga',
            'slug' => 'aktivna-udruga',
            'status' => TenantStatus::Active,
            'plan' => 'standard',
        ]);

        Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => 'ext-2',
            'name' => 'Suspendirana udruga',
            'slug' => 'suspendirana-udruga',
            'status' => TenantStatus::Suspended,
            'plan' => 'basic',
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Ukupno tenanata');
        $response->assertSee('Aktivna udruga');
        $response->assertSee('Suspendirana udruga');
        $response->assertSee('2', false);
    }
}
