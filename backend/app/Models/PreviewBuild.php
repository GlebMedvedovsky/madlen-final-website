<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreviewBuild extends Model
{
    use HasUuids;

    protected $fillable = [
        'token', 'execution_mode', 'status', 'source_revision', 'manifest_path', 'build_path',
        'target_path', 'package_path', 'package_checksum', 'result_checksum', 'progress_message',
        'error_message', 'user_id', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
