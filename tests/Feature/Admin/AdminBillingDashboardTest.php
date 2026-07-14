<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class AdminBillingDashboardTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_guest_cannot_access_billing_dashboard(): void
    {
        $this->get(route('admin.billing.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_billing_dashboard(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee('Naplata i MRR')
            ->assertSee('MRR (mjesečno)')
            ->assertSee('Churn (30 dana)');
    }
}
