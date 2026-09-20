<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class TenantTrialExtendTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_super_admin_can_extend_tenant_trial(): void
    {
        Http::fake([
            'http://saas.test/api/admin/organizations/12' => Http::response([
                'data' => [
                    'id' => 12,
                    'slug' => 'trial-org',
                    'status' => 'active',
                    'plan' => 'standard',
                    'trial_ends_at' => now()->addDays(20)->toIso8601String(),
                ],
            ], 200),
        ]);

        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            'sync_driver' => UdrugaSaasSyncDriver::class,
            'api_base_url' => 'http://saas.test',
            'api_sync_key' => 'test-key',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '12',
            'name' => 'Trial Org',
            'slug' => 'trial-org',
            'status' => 'active',
            'plan' => 'standard',
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->patch(route('admin.tenants.extend-trial', $tenant), [
                'days' => 14,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $tenant->refresh();
        $this->assertTrue($tenant->onTrial());
        $this->assertSame('standard', $tenant->plan);
        $this->assertGreaterThanOrEqual(19, $tenant->trialDaysRemaining());

        $this->assertTrue(
            AuditLog::query()
                ->where('action', AuditAction::TenantTrialExtended)
                ->where('subject_id', $tenant->id)
                ->exists()
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_ends_with($request->url(), '/api/admin/organizations/12')
                && ($request['extend_trial_days'] ?? null) === 14;
        });
    }

    public function test_tenant_detail_shows_trial_extension_form(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
            'api_base_url' => 'http://127.0.0.1:8000',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '1',
            'name' => 'Trial Org',
            'slug' => 'trial-org',
            'status' => 'active',
            'plan' => 'standard',
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Produljenje probnog perioda')
            ->assertSee('Još 5 dana');
    }
}
