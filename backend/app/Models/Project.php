<?php

namespace App\Models;

use App\Models\Concerns\RecordsRevisions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasUuids, RecordsRevisions, SoftDeletes;

    protected $fillable = [
        'source_key', 'slug', 'title_de', 'title_en', 'description_de', 'description_en',
        'category_id', 'cover_media_id', 'status', 'position', 'source_imported_at', 'published_at',
    ];

    protected function casts(): array
    {
        return ['source_imported_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            if ($project->status === 'published' || ProductionPublication::where('project_id', $project->id)
                ->whereIn('status', ['preparing', 'prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'project' => 'Dieses Projekt ist öffentlich oder wird gerade verarbeitet. Entfernen Sie es über den Editor nach erfolgreicher Veröffentlichung.',
                ]);
            }
        });
        static::restoring(function (Project $project): void {
            $project->status = 'draft';
            $project->published_at = null;
        });
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id')->withTrashed();
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(ProjectMedia::class)->orderBy('position');
    }

    public function isTranslationReady(): bool
    {
        return filled($this->title_de)
            && filled($this->title_en)
            && filled($this->description_de)
            && filled($this->description_en)
            && filled($this->cover_media_id);
    }
}
