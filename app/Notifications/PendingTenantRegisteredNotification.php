<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingTenantRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public ?string $contactEmail = null,
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
        $applicationName = $this->tenant->application?->name ?? 'Nepoznata aplikacija';

        $message = (new MailMessage)
            ->subject('Novi tenant na čekanju: '.$this->tenant->name)
            ->greeting('Pozdrav, '.$notifiable->name.'!')
            ->line('Registriran je novi tenant koji čeka odobrenje u Super-Admin konzoli.')
            ->line('Aplikacija: '.$applicationName)
            ->line('Tenant: '.$this->tenant->name.' ('.$this->tenant->slug.')')
            ->line('Plan: '.($this->tenant->resolvedSubscriptionPlan()?->name ?? $this->tenant->plan));

        if ($this->contactEmail !== null && $this->contactEmail !== '') {
            $message->line('Kontakt e-mail: '.$this->contactEmail);
        }

        return $message
            ->action('Otvori nadzornu ploču', route('admin.dashboard'))
            ->line('Prijavite se i odobrite tenant prije nego što korisnik može koristiti aplikaciju.');
    }
}
