<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class MediaAsset extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'source_key', 'kind', 'path', 'derivative_path', 'original_name', 'mime_type',
        'size_bytes', 'width', 'height', 'checksum', 'alt_de', 'alt_en',
        'caption_de', 'caption_en', 'source_managed',
    ];

    protected function casts(): array
    {
        return ['source_managed' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (MediaAsset $asset): void {
            if (! $asset->isForceDeleting() && ($asset->projects()->exists() || $asset->coveredProjects()->exists() || $asset->siteSlots()->exists())) {
                throw ValidationException::withMessages([
                    'media' => 'Dieses Medium wird noch verwendet. Bitte zuerst aus Titelbild, Galerien und Website-Medien lösen.',
                ]);
            }
        });
    }

    public function projectItems(): HasMany
    {
        return $this->hasMany(ProjectMedia::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_media')->withPivot(['role', 'position', 'side']);
    }

    public function coveredProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'cover_media_id');
    }

    public function siteSlots(): HasMany
    {
        return $this->hasMany(SiteMediaSlot::class);
    }

    public function publicPath(): string
    {
        if ($this->source_managed) {
            return $this->path;
        }

        return '/media/'.$this->id.'/'.basename($this->derivative_path ?: $this->path);
    }
}
