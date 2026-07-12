<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_application_changes_visible_tenants(): void
    {
        $user = User::factory()->superAdmin()->create();

        $udrugaSaas = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);

        $opgSaas = Application::query()->create([
            'name' => 'OPG SaaS',
            'slug' => 'opg-saas',
            'description' => 'Test',
        ]);

        Tenant::query()->create([
            'application_id' => $udrugaSaas->id,
            'external_id' => 'udruga-1',
            'name' => 'Udruga Alpha',
            'slug' => 'udruga-alpha',
            'status' => TenantStatus::Active,
            'plan' => 'basic',
        ]);

        Tenant::query()->create([
            'application_id' => $opgSaas->id,
            'external_id' => 'opg-1',
            'name' => 'OPG Beta',
            'slug' => 'opg-beta',
            'status' => TenantStatus::Active,
            'plan' => 'basic',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $udrugaSaas->id])
            ->get(route('admin.dashboard'))
            ->assertSee('Udruga Alpha')
            ->assertDontSee('OPG Beta');

        $response = $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $udrugaSaas->id])
            ->post(route('admin.switch-app', $opgSaas));

        $response->assertRedirect();
        $response->assertSessionHas(AdminSession::ACTIVE_APP_ID, $opgSaas->id);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $opgSaas->id])
            ->get(route('admin.dashboard'))
            ->assertSee('OPG Beta')
            ->assertDontSee('Udruga Alpha');
    }
}
