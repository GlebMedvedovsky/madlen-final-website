<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PreviewBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CreatePreview extends Command
{
    protected $signature = 'madlen:preview {email : E-Mail-Adresse eines vorhandenen Administrators}';

    protected $description = 'Erstellt eine geschützte Vorschau im konfigurierten lokalen oder externen Modus';

    public function handle(PreviewBuilder $builder): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Administrator nicht gefunden. Zuerst madlen:admin ausführen.');

            return self::FAILURE;
        }

        Auth::login($user);
        try {
            $preview = $builder->build();
            $this->info(route('admin.preview', ['token' => $preview->token]));
            $this->comment('Die Vorschau ist nur in einer angemeldeten Admin-Sitzung bis '.$preview->expires_at->format('d.m.Y H:i').' erreichbar.');

            return self::SUCCESS;
        } finally {
            Auth::logout();
        }
    }
}
