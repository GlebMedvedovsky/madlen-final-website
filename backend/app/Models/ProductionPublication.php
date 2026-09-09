<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPublication extends Model
{
    use HasUuids;

    protected $fillable = [
        'sequence', 'status', 'source_revision', 'manifest_path', 'package_path',
        'package_checksum', 'target_release', 'progress_message', 'error_message',
        'requested_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
