<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Clone the URL generator: neither Host/X-Forwarded-Host nor APP_URL can
        // turn the email into a password-reset poisoning link. Do not change other URLs.
        $urls = clone app('url');
        $urls->forceRootUrl('https://admin.madebymadlen.de');
        $urls->forceScheme('https');
        $url = $urls->temporarySignedRoute('filament.admin.auth.password-reset.reset', now()->addMinutes(60), [
            'email' => $notifiable->getEmailForPasswordReset(),
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Madlen · Passwort zurücksetzen')
            ->greeting('Hallo!')
            ->line('Für Ihr Madlen-Administratorkonto wurde ein Link zum Zurücksetzen des Passworts angefordert.')
            ->action('Passwort zurücksetzen', $url)
            ->line('Dieser Link ist 60 Minuten gültig und kann nur einmal verwendet werden.')
            ->line('Wenn Sie diese Anfrage nicht gestellt haben, müssen Sie nichts unternehmen. Ihr Passwort bleibt unverändert.')
            ->salutation('Viele Grüße, Ihr Madlen-Team');
    }

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->locale('de');
    }
}
