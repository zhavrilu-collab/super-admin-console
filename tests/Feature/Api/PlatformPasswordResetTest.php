<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\AppPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_email_points_back_to_the_requesting_app(): void
    {
        Notification::fake();

        config([
            'saas_applications.applications.hr-saas.base_url' => 'https://hr.superskytech.com',
        ]);

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ana@example.com',
            'application_slug' => 'hr-saas',
        ])->assertOk()
            ->assertJsonPath('status', 'passwords.sent');

        Notification::assertSentTo($user, AppPasswordResetNotification::class, function (AppPasswordResetNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertStringContainsString(
                'https://hr.superskytech.com/resetiranje-lozinke/'.$notification->token,
                (string) $mail->actionUrl,
            );

            return true;
        });
    }

    public function test_reset_password_updates_the_platform_user(): void
    {
        Notification::fake();

        config([
            'saas_applications.applications.hr-saas.base_url' => 'https://hr.superskytech.com',
        ]);

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ana@example.com',
            'application_slug' => 'hr-saas',
        ])->assertOk();

        Notification::assertSentTo($user, AppPasswordResetNotification::class, function (AppPasswordResetNotification $notification) use ($user) {
            $this->postJson('/api/v1/auth/reset-password', [
                'email' => 'ana@example.com',
                'token' => $notification->token,
                'password' => 'nova-lozinka',
                'password_confirmation' => 'nova-lozinka',
            ])->assertOk()
                ->assertJsonPath('status', 'passwords.reset');

            return true;
        });

        $this->assertTrue(Hash::check('nova-lozinka', $user->fresh()->password));
    }
}
