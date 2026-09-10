<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class TenantDeleteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_delete_pushes_remote_delete_then_removes_local_tenant(): void
    {
        Http::fake([
            'http://saas.test/api/admin/organizations/42' => Http::response([
                'data' => ['id' => 42, 'slug' => 'za-brisanje'],
                'meta' => ['deleted' => true],
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
            'external_id' => '42',
            'name' => 'Za brisanje',
            'slug' => 'za-brisanje',
            'status' => 'pending',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->delete(route('admin.tenants.destroy', $tenant))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TenantDeleted->value,
            'subject_id' => $tenant->id,
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'http://saas.test/api/admin/organizations/42';
        });
    }

    public function test_delete_treats_remote_404_as_success(): void
    {
        Http::fake([
            'http://saas.test/api/admin/organizations/99' => Http::response(['message' => 'Not found'], 404),
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
            'external_id' => '99',
            'name' => 'Orphan',
            'slug' => 'orphan',
            'status' => 'pending',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->delete(route('admin.tenants.destroy', $tenant))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }
}
