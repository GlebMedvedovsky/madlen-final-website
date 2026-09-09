<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'label_de'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public function adminLabel(): string
    {
        return [
            'primaryLocale' => 'Hauptsprache',
            'contactEmail' => 'Kontakt-E-Mail',
            'siteUrl' => 'Website-Adresse',
        ][$this->key] ?? $this->label_de;
    }
}
