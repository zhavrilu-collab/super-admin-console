<?php

namespace Tests\Feature\Billing;

use App\Enums\DunningResolution;
use App\Enums\TenantStatus;
use App\Models\Application;
use App\Models\SubscriptionDunningCase;
use App\Models\Tenant;
use App\Notifications\DunningSuspendedTenantNotification;
use App\Notifications\PaymentFailedReminderNotification;
use App\Services\Billing\BillingDunningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SeedsSubscriptionPlans;
use Tests\TestCase;

class BillingDunningServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSubscriptionPlans;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.dunning.reminder_days' => [3, 5, 7],
            'billing.dunning.suspend_after_days' => 14,
            'billing.dunning.downgrade_on_suspend' => true,
            'saas_applications.applications.udruga-saas' => [
                'driver' => \App\Services\Admin\Sync\UdrugaSaasSyncDriver::class,
                'base_url' => 'http://udruga-saas.test',
                'api_key' => 'test-sync-key',
            ],
        ]);

        Http::fake([
            'http://udruga-saas.test/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_record_payment_failure_creates_open_dunning_case(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '10',
            'name' => 'Dunning test',
            'slug' => 'dunning-test',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        $service = app(BillingDunningService::class);

        $case = $service->recordPaymentFailure($tenant, null, [
            'id' => 'in_test_1',
            'subscription' => 'sub_test_1',
            'customer_email' => 'owner@udruga.hr',
        ]);

        $this->assertSame($tenant->id, $case->tenant_id);
        $this->assertNull($case->resolved_at);
        $this->assertSame('owner@udruga.hr', $case->contact_email);
        $this->assertDatabaseHas('subscription_dunning_cases', [
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_1',
        ]);
    }

    public function test_process_scheduled_actions_sends_day_three_reminder(): void
    {
        Notification::fake();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '11',
            'name' => 'Reminder test',
            'slug' => 'reminder-test',
            'status' => 'active',
            'plan' => 'standard',
        ]);

        SubscriptionDunningCase::query()->create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_2',
            'stripe_subscription_id' => 'sub_test_2',
            'contact_email' => 'owner@udruga.hr',
            'started_at' => now()->subDays(3),
            'suspend_after_at' => now()->addDays(11),
        ]);

        $result = app(BillingDunningService::class)->processScheduledActions();

        $this->assertSame(1, $result['reminders_sent']);
        $this->assertSame(0, $result['suspensions_applied']);

        Notification::assertSentOnDemand(
            PaymentFailedReminderNotification::class,
            function ($notification, array $channels, object $notifiable) {
                return $notifiable->routes['mail'] === 'owner@udruga.hr'
                    && $notification->reminderDay === 3;
            },
        );
    }

    public function test_process_scheduled_actions_suspends_tenant_after_grace_period(): void
    {
        Notification::fake();

        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '12',
            'name' => 'Suspend test',
            'slug' => 'suspend-test',
            'status' => 'active',
            'plan' => 'premium',
        ]);

        SubscriptionDunningCase::query()->create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_3',
            'stripe_subscription_id' => 'sub_test_3',
            'contact_email' => 'owner@udruga.hr',
            'started_at' => now()->subDays(14),
            'suspend_after_at' => now()->subMinute(),
            'reminder_stage' => 3,
        ]);

        $result = app(BillingDunningService::class)->processScheduledActions();

        $this->assertSame(0, $result['reminders_sent']);
        $this->assertSame(1, $result['suspensions_applied']);

        $tenant->refresh();

        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertSame('basic', $tenant->plan);

        $this->assertDatabaseHas('subscription_dunning_cases', [
            'tenant_id' => $tenant->id,
            'resolution' => DunningResolution::Suspended->value,
        ]);

        Notification::assertSentOnDemand(DunningSuspendedTenantNotification::class);
    }

    public function test_resolve_for_paid_reactivates_suspended_tenant(): void
    {
        $application = Application::query()->create([
            'name' => 'Udruga SaaS',
            'slug' => 'udruga-saas',
            'description' => 'Test',
        ]);
        $this->seedSubscriptionPlans($application);

        $tenant = Tenant::query()->create([
            'application_id' => $application->id,
            'external_id' => '13',
            'name' => 'Recovery test',
            'slug' => 'recovery-test',
            'status' => 'suspended',
            'plan' => 'basic',
        ]);

        SubscriptionDunningCase::query()->create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_4',
            'started_at' => now()->subDays(20),
            'suspend_after_at' => now()->subDays(6),
            'resolution' => DunningResolution::Suspended,
            'resolved_at' => now()->subDays(6),
        ]);

        SubscriptionDunningCase::query()->create([
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_5',
            'started_at' => now()->subDays(2),
            'suspend_after_at' => now()->addDays(12),
        ]);

        app(BillingDunningService::class)->resolveForTenant($tenant, DunningResolution::Paid);

        $tenant->refresh();

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertDatabaseHas('subscription_dunning_cases', [
            'tenant_id' => $tenant->id,
            'stripe_invoice_id' => 'in_test_5',
            'resolution' => DunningResolution::Paid->value,
        ]);
    }
}
