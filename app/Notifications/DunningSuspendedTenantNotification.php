<?php

namespace App\Notifications;

use App\Models\SubscriptionDunningCase;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DunningSuspendedTenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public SubscriptionDunningCase $dunningCase,
    ) {
        $this->tenant->loadMissing('application');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicationName = $this->tenant->application?->name ?? 'aplikaciju';

        return (new MailMessage)
            ->subject('Pristup suspendiran — '.$this->tenant->name)
            ->greeting('Pozdrav!')
            ->line('Pristup organizaciji '.$this->tenant->name.' za '.$applicationName.' je suspendiran zbog neuspjele uplate pretplate.')
            ->line('Nakon uspješne uplate pristup će se automatski vratiti.')
            ->line('Za pomoć kontaktirajte podršku.');
    }
}
