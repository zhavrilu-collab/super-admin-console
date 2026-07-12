<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use App\Notifications\PendingTenantRegisteredNotification;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class PendingTenantNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webhook.secret' => 'test-webhook-secret',
            'saas_applications.applications.udruga-saas' => [
                'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
                'base_url' => 'http://udruga-saas.test',
                'api_key' => 'test-sync-key',
            ],
        ]);
    }

    public function test_webhook_notifies_super_admins_about_new_pending_tenant(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->create(['email' => 'regular@example.com']);

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 77,
                    'name' => 'Nova udruga',
                    'slug' => 'nova-udruga',
                    'status' => 'pending',
                    'plan' => 'basic',
                    'email' => 'kontakt@udruga.hr',
                ],
            ])
            ->assertCreated();

        Notification::assertSentTo(
            $superAdmin,
            PendingTenantRegisteredNotification::class,
            function (PendingTenantRegisteredNotification $notification) {
                return $notification->tenant->name === 'Nova udruga'
                    && $notification->contactEmail === 'kontakt@udruga.hr';
            },
        );

        Notification::assertNotSentTo(
            User::query()->where('email', 'regular@example.com')->first(),
            PendingTenantRegisteredNotification::class,
        );
    }

    public function test_webhook_does_not_notify_on_existing_tenant_update(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 12,
                    'name' => 'Prva registracija',
                    'slug' => 'prva-registracija',
                    'status' => 'pending',
                    'plan' => 'basic',
                ],
            ])
            ->assertCreated();

        Notification::assertSentTimes(PendingTenantRegisteredNotification::class, 1);

        $this->withToken('test-webhook-secret')
            ->postJson('/api/webhooks/tenants/registered', [
                'application_slug' => 'udruga-saas',
                'organization' => [
                    'id' => 12,
                    'name' => 'Ažurirano ime',
                    'slug' => 'azurirano-ime',
                    'status' => 'pending',
                    'plan' => 'basic',
                ],
            ])
            ->assertOk();

        Notification::assertSentTimes(PendingTenantRegisteredNotification::class, 1);
    }

    public function test_sync_notifies_super_admins_about_new_pending_tenant(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->superAdmin()->create();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 301,
                        'name' => 'Sync udruga',
                        'slug' => 'sync-udruga',
                        'status' => 'pending',
                        'plan' => 'premium',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $this->actingAs($superAdmin)
            ->withSession([AdminSession::ACTIVE_APP_ID => $application->id])
            ->post(route('admin.sync'))
            ->assertRedirect();

        Notification::assertSentTo(
            $superAdmin,
            PendingTenantRegisteredNotification::class,
            fn (PendingTenantRegisteredNotification $notification) => $notification->tenant->external_id === '301',
        );
    }

    public function test_active_tenant_from_sync_does_not_trigger_notification(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        Http::fake([
            'udruga-saas.test/api/admin/organizations' => Http::response([
                'data' => [
                    [
                        'id' => 88,
                        'name' => 'Aktivna sync',
                        'slug' => 'aktivna-sync',
                        'status' => 'active',
                        'plan' => 'basic',
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $this->artisan('tenants:sync')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
