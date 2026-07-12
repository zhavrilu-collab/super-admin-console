<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\User;
use App\Services\Admin\ConsoleSettingsService;
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
            ->assertSee('E-mail (SMTP)');

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
}
