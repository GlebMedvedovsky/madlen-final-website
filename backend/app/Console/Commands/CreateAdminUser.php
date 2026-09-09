<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'madlen:admin {email : E-Mail-Adresse} {--name=Madlen : Anzeigename}';

    protected $description = 'Legt den einzigen lokalen Administrator sicher und interaktiv an';

    public function handle(): int
    {
        $password = $this->secret('Sicheres Passwort (mindestens 12 Zeichen)');
        $confirmation = $this->secret('Passwort wiederholen');
        $data = ['email' => $this->argument('email'), 'password' => $password];
        $validator = Validator::make($data, [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
        ], [
            'email.*' => 'Bitte eine gültige E-Mail-Adresse angeben.',
            'password.*' => 'Das Passwort muss mindestens 12 Zeichen lang sein.',
        ]);
        if ($validator->fails() || ! hash_equals((string) $password, (string) $confirmation)) {
            $this->error($validator->errors()->first() ?: 'Die Passwörter stimmen nicht überein.');
            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $data['email']],
            ['name' => $this->option('name'), 'password' => Hash::make($password)],
        );
        $this->info('Administratorkonto wurde gespeichert.');
        return self::SUCCESS;
    }
}
