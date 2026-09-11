<?php

namespace App\Filament\Auth;

use App\Notifications\AdminResetPassword;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;
use Throwable;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public const MESSAGE = 'Wenn für diese E-Mail-Adresse ein Administratorkonto besteht, erhalten Sie einen Link zum Zurücksetzen.';
    public const HELP = 'Bitte prüfen Sie auch den Spam-Ordner. Falls keine E-Mail ankommt, warten Sie mindestens eine Minute und versuchen Sie es erneut oder wenden Sie sich an die Administration.';

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();
        Password::broker('users')->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword $user, #[SensitiveParameter] string $token): void {
                if (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
                    return;
                }

                try {
                    // Synchronous delivery: no worker is required on shared hosting.
                    // Never write a bearer link into a production log/array transport.
                    $transport = config('mail.mailers.'.config('mail.default').'.transport');
                    if ($transport !== 'smtp' && ! (app()->environment(['local', 'testing']) && $transport === 'array')) {
                        throw new \RuntimeException('Password reset requires SMTP.');
                    }
                    $user->notifyNow(new AdminResetPassword($token));
                    event(new PasswordResetLinkSent($user));
                } catch (Throwable) {
                    // Do not disclose account existence, SMTP credentials or the token.
                    // Keep the broker throttle even on failure; a retry after 60s replaces the token.
                    Log::warning('Admin password reset email delivery failed; inspect mail configuration privately.');
                }
            },
        );

        // Identical notification AND form state for sent, unknown and throttled email,
        // including delivery failure. Broker retains its timebox and per-account throttle.
        Notification::make()->title(self::MESSAGE)->body(self::HELP)->success()->send();
        $this->form->fill();
    }
}
