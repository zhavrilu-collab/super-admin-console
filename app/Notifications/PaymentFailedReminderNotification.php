<?php

namespace App\Notifications;

use App\Models\SubscriptionDunningCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SubscriptionDunningCase $dunningCase,
        public int $reminderDay,
    ) {
        $this->dunningCase->loadMissing(['tenant.application']);
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
        $tenant = $this->dunningCase->tenant;
        $applicationName = $tenant->application?->name ?? 'aplikaciju';
        $daysUntilSuspend = max(0, now()->diffInDays($this->dunningCase->suspend_after_at, false));

        $message = (new MailMessage)
            ->subject('Problem s uplatom pretplate — '.$tenant->name)
            ->greeting('Pozdrav!')
            ->line('Nismo uspjeli naplatiti pretplatu za '.$applicationName.'.')
            ->line('Organizacija: '.$tenant->name);

        if ($this->reminderDay <= 7) {
            $message->line('Ovo je '.$this->reminderDay.'. podsjetnik. Molimo ažurirajte način plaćanja što prije.');
        }

        if ($daysUntilSuspend > 0) {
            $message->line('Ako uplata ne prođe u sljedećih '.$daysUntilSuspend.' dana, pristup organizaciji može biti suspendiran.');
        } else {
            $message->line('Rok za uplatu je istekao ili ističe danas — pristup može biti suspendiran.');
        }

        return $message
            ->line('Stripe će automatski pokušati ponovno naplatiti karticu prema svom rasporedu.')
            ->line('Ako trebate pomoć, kontaktirajte podršku.');
    }
}
