<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\Sync\UdrugaSaasSyncDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_application(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->post(route('admin.applications.store'), [
                'name' => 'OPG SaaS',
                'slug' => 'opg-saas',
                'description' => 'Platforma za OPG-ove',
                'sync_driver' => UdrugaSaasSyncDriver::class,
                'api_base_url' => 'http://opg.test',
                'api_sync_key' => 'opg-key',
            ])
            ->assertRedirect(route('admin.applications.index'));

        $this->assertDatabaseHas('applications', [
            'slug' => 'opg-saas',
            'sync_driver' => UdrugaSaasSyncDriver::class,
            'api_base_url' => 'http://opg.test',
        ]);
    }

    public function test_super_admin_can_update_application(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test App',
            'slug' => 'test-app',
            'description' => 'Old',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.applications.update', $application), [
                'name' => 'Test App Updated',
                'slug' => 'test-app',
                'description' => 'New description',
                'sync_driver' => UdrugaSaasSyncDriver::class,
                'api_base_url' => 'http://updated.test',
                'api_sync_key' => 'new-key',
            ])
            ->assertRedirect(route('admin.applications.index'));

        $application->refresh();

        $this->assertSame('Test App Updated', $application->name);
        $this->assertSame('http://updated.test', $application->api_base_url);
        $this->assertSame('new-key', $application->api_sync_key);
    }

    public function test_application_with_tenants_cannot_be_deleted(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Busy App',
            'slug' => 'busy-app',
        ]);

        Tenant::query()->create([
            'application_id' => $application->id,
            'name' => 'Tenant',
            'slug' => 'tenant-1',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.applications.destroy', $application))
            ->assertRedirect(route('admin.applications.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }

    public function test_empty_application_can_be_deleted(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Empty App',
            'slug' => 'empty-app',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.applications.destroy', $application))
            ->assertRedirect(route('admin.applications.index'));

        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    }
}
