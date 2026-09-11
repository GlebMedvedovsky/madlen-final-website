<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ResetPassword extends BaseResetPassword
{
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password.label'))
            ->password()
            ->autocomplete('new-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rule(Password::min(12)->mixedCase()->numbers()->symbols())
            ->same('passwordConfirmation')
            ->validationAttribute('Passwort')
            ->maxLength(72)
            ->rule(static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                // Bcrypt only uses 72 bytes; never silently accept an ignored suffix.
                if (strlen((string) $value) > 72 || str_contains((string) $value, "\0")) {
                    $fail('Das Passwort darf höchstens 72 UTF-8-Bytes und keine Nullzeichen enthalten.');
                }
            })
            ->helperText('Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben, einer Zahl und einem Sonderzeichen. Höchstens 72 UTF-8-Bytes.');
    }

    public function resetPassword(): ?PasswordResetResponse
    {
        // Serialize consumption of the native broker token. Two simultaneous valid
        // submissions must not both change the password before the broker deletes it.
        $connection = DB::connection(config('auth.passwords.users.connection'));

        return $connection->transaction(function () use ($connection): ?PasswordResetResponse {
            $connection->table(config('auth.passwords.users.table'))
                ->where('email', $this->email)->lockForUpdate()->first();

            return parent::resetPassword();
        });
    }

    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        $broker = PasswordBroker::broker('users');
        $user = $broker->getUser(['email' => $this->email]);
        // Only a valid bearer token may learn that a proposed password is the old
        // one. A successful reset must actually retire the previous password.
        if ($user && $broker->tokenExists($user, (string) $this->token)
            && Hash::check($data['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => 'Bitte wählen Sie ein anderes Passwort als Ihr bisheriges.',
            ]);
        }

        return parent::getCredentialsFromFormData($data);
    }
}
