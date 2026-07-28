<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateConsoleMailSettingsRequest;
use App\Services\Admin\ConsoleSettingsService;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly ConsoleSettingsService $consoleSettings,
        private readonly StripeBillingService $stripeBilling,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'mail' => $this->consoleSettings->mailSettingsForForm(),
            'billing' => $this->consoleSettings->billingSettingsForForm(),
            'hasMailPassword' => $this->consoleSettings->get(ConsoleSettingsService::MAIL_PASSWORD) !== null,
            'hasWebhookSecret' => $this->consoleSettings->webhookSecret() !== null,
            'hasStripeSecretKey' => $this->consoleSettings->get(ConsoleSettingsService::STRIPE_SECRET_KEY) !== null,
            'hasStripeWebhookSecret' => $this->consoleSettings->get(ConsoleSettingsService::STRIPE_WEBHOOK_SECRET) !== null,
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
        ]);
    }

    public function updateMail(UpdateConsoleMailSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->consoleSettings->set(ConsoleSettingsService::MAIL_MAILER, $data['mail_mailer']);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_HOST, $data['mail_host'] ?? null);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_PORT, isset($data['mail_port']) ? (string) $data['mail_port'] : null);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_USERNAME, $data['mail_username'] ?? null);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_ENCRYPTION, $data['mail_encryption'] ?? null);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_FROM_ADDRESS, $data['mail_from_address']);
        $this->consoleSettings->set(ConsoleSettingsService::MAIL_FROM_NAME, $data['mail_from_name']);

        if (! empty($data['mail_password'])) {
            $this->consoleSettings->set(ConsoleSettingsService::MAIL_PASSWORD, $data['mail_password']);
        }

        if (! empty($data['webhook_secret'])) {
            $this->consoleSettings->set(ConsoleSettingsService::WEBHOOK_SECRET, $data['webhook_secret']);
        }

        if (! empty($data['stripe_publishable_key'])) {
            $this->consoleSettings->set(ConsoleSettingsService::STRIPE_PUBLISHABLE_KEY, $data['stripe_publishable_key']);
        }

        if (! empty($data['stripe_secret_key'])) {
            $this->consoleSettings->set(ConsoleSettingsService::STRIPE_SECRET_KEY, $data['stripe_secret_key']);
        }

        if (! empty($data['stripe_webhook_secret'])) {
            $this->consoleSettings->set(ConsoleSettingsService::STRIPE_WEBHOOK_SECRET, $data['stripe_webhook_secret']);
        }

        $this->consoleSettings->set(
            ConsoleSettingsService::BANK_TRANSFER_ENABLED,
            ! empty($data['bank_transfer_enabled']) ? '1' : '0',
        );
        $this->consoleSettings->set(
            ConsoleSettingsService::BANK_TRANSFER_IBAN,
            isset($data['bank_transfer_iban']) ? preg_replace('/\s+/', '', (string) $data['bank_transfer_iban']) : null,
        );
        $this->consoleSettings->set(
            ConsoleSettingsService::BANK_TRANSFER_RECIPIENT,
            $data['bank_transfer_recipient'] ?? null,
        );
        $this->consoleSettings->set(
            ConsoleSettingsService::BANK_TRANSFER_PAYMENT_DAYS,
            isset($data['bank_transfer_payment_days']) ? (string) $data['bank_transfer_payment_days'] : null,
        );

        $this->consoleSettings->applyMailConfiguration();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Postavke konzole su spremljene.');
    }
}
