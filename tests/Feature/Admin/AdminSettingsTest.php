<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Billing\StripeBillingService;
use App\Support\AdminSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_and_update_mail_settings(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Postavke konzole')
            ->assertSee('E-mail (SMTP)')
            ->assertSee('Stripe naplata')
            ->assertSee('Uplata na poslovni račun');

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => 1])
            ->patch(route('admin.settings.mail'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.example.com',
                'mail_port' => 587,
                'mail_username' => 'mailer',
                'mail_password' => 'secret-pass',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'Admin',
                'webhook_secret' => 'wh-secret',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(ConsoleSettingsService::class);

        $this->assertSame('smtp', $settings->get(ConsoleSettingsService::MAIL_MAILER));
        $this->assertSame('smtp.example.com', $settings->get(ConsoleSettingsService::MAIL_HOST));
        $this->assertSame('secret-pass', $settings->get(ConsoleSettingsService::MAIL_PASSWORD));
        $this->assertSame('wh-secret', $settings->webhookSecret());
    }

    public function test_super_admin_can_update_stripe_settings(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => 1])
            ->patch(route('admin.settings.mail'), [
                'mail_mailer' => 'log',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'Admin',
                'stripe_publishable_key' => 'pk_test_abc',
                'stripe_secret_key' => 'sk_test_xyz',
                'stripe_webhook_secret' => 'whsec_test',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(ConsoleSettingsService::class);

        $this->assertSame('pk_test_abc', $settings->get(ConsoleSettingsService::STRIPE_PUBLISHABLE_KEY));
        $this->assertSame('sk_test_xyz', $settings->get(ConsoleSettingsService::STRIPE_SECRET_KEY));
        $this->assertSame('whsec_test', $settings->get(ConsoleSettingsService::STRIPE_WEBHOOK_SECRET));
        $this->assertTrue(app(StripeBillingService::class)->isConfigured());
    }

    public function test_super_admin_can_update_bank_transfer_settings(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->withSession([AdminSession::ACTIVE_APP_ID => 1])
            ->patch(route('admin.settings.mail'), [
                'mail_mailer' => 'log',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'Admin',
                'bank_transfer_enabled' => '1',
                'bank_transfer_iban' => 'HR12 3456 7890 1234 5678 9',
                'bank_transfer_recipient' => 'Test Primatelj d.o.o.',
                'bank_transfer_payment_days' => 10,
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(ConsoleSettingsService::class);

        $this->assertTrue($settings->bankTransferEnabled());
        $this->assertSame('HR1234567890123456789', $settings->bankTransferIban());
        $this->assertSame('Test Primatelj d.o.o.', $settings->bankTransferRecipient());
        $this->assertSame(10, $settings->bankTransferPaymentDays());
    }
}
