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
        'requested_by', 'activated_at', 'request_id', 'project_id', 'operation', 'runner_id', 'previous_project_state',
    ];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'previous_project_state' => 'array'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function getManifestPathAttribute(?string $value): ?string
    {
        return $this->resolvePackagePath($value, 'requests/'.$this->id.'/payload/content-manifest.json');
    }

    public function getPackagePathAttribute(?string $value): ?string
    {
        return $this->resolvePackagePath($value, 'packages/madlen-publication-'.$this->sequence.'-'.$this->id.'.zip');
    }

    private function resolvePackagePath(?string $value, string $relative): ?string
    {
        if ($value === null || $value === '') return $value;
        $root = rtrim((string) config('madlen.publisher.package_root'), '/\\');
        if (! str_starts_with($root, '/') && ! preg_match('#\A[A-Za-z]:[/\\\\]#', $root)) {
            throw new \RuntimeException('Der private Paketspeicher ist nicht absolut konfiguriert.');
        }
        $expected = $root.'/'.$relative;
        // Legacy local records are accepted only under this job's exact configured path.
        if ($value !== 'madlen-production-storage-v1://'.$relative && $value !== $expected) {
            throw new \RuntimeException('Der gespeicherte Paketpfad gehört nicht zu diesem Auftrag und Speicherbereich.');
        }
        return $expected;
    }
}
