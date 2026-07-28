<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\ApplicationFeature;
use App\Models\User;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class ApplicationFeatureCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    public function test_super_admin_can_view_and_seed_feature_catalog(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->get(route('admin.application-features.index'))
            ->assertOk()
            ->assertSee('Značajke paketa')
            ->assertSee('member_limit')
            ->assertSee('subdomain')
            ->assertSee('newsletters')
            ->assertSee('email_templates')
            ->assertSee('finance');

        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'cookie_banner',
        ]);
        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'application_form_builder',
        ]);
    }

    public function test_super_admin_can_add_custom_feature(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.application-features.store'), [
                'key' => 'sms_reminders',
                'label' => 'SMS podsjetnici',
                'type' => 'boolean',
                'sort_order' => 90,
            ])
            ->assertRedirect(route('admin.application-features.index'));

        $this->assertDatabaseHas('application_features', [
            'application_id' => $application->id,
            'key' => 'sms_reminders',
            'label' => 'SMS podsjetnici',
        ]);
    }

    public function test_builtin_features_cannot_be_deleted(): void
    {
        $user = User::factory()->superAdmin()->create();
        $application = Application::query()->create([
            'name' => 'Test SaaS',
            'slug' => 'test-app',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $feature = ApplicationFeature::query()
            ->where('application_id', $application->id)
            ->where('key', 'subdomain')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->delete(route('admin.application-features.destroy', $feature))
            ->assertRedirect(route('admin.application-features.index'));

        $this->assertDatabaseHas('application_features', [
            'id' => $feature->id,
            'key' => 'subdomain',
        ]);
    }
}
