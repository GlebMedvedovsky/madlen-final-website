<?php

namespace App\Models;

use App\Models\Concerns\RecordsRevisions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasUuids, RecordsRevisions, SoftDeletes;

    protected $fillable = [
        'key', 'title_de', 'title_en', 'description_de', 'description_en', 'icon',
        'highlighted', 'active_de', 'active_en', 'position', 'source_imported_at',
    ];

    protected function casts(): array
    {
        return [
            'highlighted' => 'boolean', 'active_de' => 'boolean', 'active_en' => 'boolean',
            'source_imported_at' => 'datetime',
        ];
    }

    public function isLocaleReady(string $locale): bool
    {
        return filled($this->{"title_{$locale}"}) && filled($this->{"description_{$locale}"});
    }

    public function englishReadinessLabel(): string
    {
        if ($this->key === 'editing' && ! $this->active_en && ! $this->isLocaleReady('en')) {
            return 'Zusammengefasst';
        }

        return $this->active_en && $this->isLocaleReady('en') ? 'Bereit' : 'Unvollständig';
    }
}
