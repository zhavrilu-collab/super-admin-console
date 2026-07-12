<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateConsoleMailSettingsRequest;
use App\Services\Admin\ConsoleSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly ConsoleSettingsService $consoleSettings,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'mail' => $this->consoleSettings->mailSettingsForForm(),
            'hasMailPassword' => $this->consoleSettings->get(ConsoleSettingsService::MAIL_PASSWORD) !== null,
            'hasWebhookSecret' => $this->consoleSettings->webhookSecret() !== null,
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

        $this->consoleSettings->applyMailConfiguration();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Postavke konzole su spremljene.');
    }
}
