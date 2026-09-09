<?php

namespace App\Models;

use App\Models\Concerns\RecordsRevisions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ContentEntry extends Model
{
    use HasUuids, RecordsRevisions, SoftDeletes;

    protected $fillable = [
        'key', 'group_name', 'type', 'value_de', 'value_en', 'seo', 'position', 'source_imported_at',
    ];

    protected function casts(): array
    {
        return ['seo' => 'array', 'source_imported_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (ContentEntry $entry): void {
            if ($entry->type !== 'json') return;
            foreach (['value_de', 'value_en'] as $field) {
                if (blank($entry->{$field})) continue;
                json_decode($entry->{$field});
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw ValidationException::withMessages([$field => 'Der JSON-Inhalt ist ungültig: '.json_last_error_msg()]);
                }
            }
        });
    }

    public function adminLabel(): string
    {
        return [
            'translations.nav' => 'Navigation',
            'translations.home' => 'Startseite',
            'translations.portfolio' => 'Portfolio',
            'translations.services' => 'Leistungen',
            'translations.about' => 'Über mich',
            'translations.contact' => 'Kontakt',
            'translations.footer' => 'Fußbereich',
            'legal.legal-notice' => 'Impressum',
            'legal.privacy' => 'Datenschutz',
            'legal.terms' => 'AGB / Hinweise',
        ][$this->key] ?? $this->key;
    }
}
