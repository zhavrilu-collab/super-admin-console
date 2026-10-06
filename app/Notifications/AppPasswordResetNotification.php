<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AppPasswordResetNotification extends ResetPassword
{
    public function __construct(#[\SensitiveParameter] $token, public string $resetBaseUrl)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova lozinka')
            ->line('Primili ste ovu poruku jer je zatražena nova lozinka za vaš račun.')
            ->action('Postavi novu lozinku', $this->resetUrl($notifiable))
            ->line('Ako niste tražili novu lozinku, zanemarite ovu poruku.');
    }

    protected function resetUrl($notifiable): string
    {
        return $this->resetBaseUrl.'/resetiranje-lozinke/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
