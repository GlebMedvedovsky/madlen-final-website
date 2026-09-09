<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMedia extends Model
{
    protected $table = 'project_media';

    protected $fillable = ['project_id', 'media_asset_id', 'role', 'position', 'side'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class)->withTrashed();
    }
}
