<?php

namespace App\Services;

use App\Models\ProductionPublication;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reconcile the static pointer and CMS lifecycle; the same activation may be retried. */
class ProductionReleaseManager
{
    public function __construct(private StaticReleaseActivator $activator) {}

    public function activate(string $id, int $sequence, string $archive, string $sha256, ?string $runner = null): string
    {
        return Cache::lock('madlen-production-publication', 180)->block(5, function () use ($id, $sequence, $archive, $sha256, $runner) {
            $publication = ProductionPublication::findOrFail($id);
            if ($publication->sequence != $sequence || $publication->runner_id !== $runner || ! in_array($publication->status, ['uploading', 'active'], true)) {
                throw new RuntimeException('Der Auftrag ist nicht zur Aktivierung freigegeben.');
            }
            $old = $this->activator->currentRelease();
            $path = rtrim((string) config('madlen.publisher.incoming_root'), '/').'/'.$archive;
            if (basename($archive) !== $archive || ! is_file($path) || ! hash_equals($sha256, hash_file('sha256', $path))) {
                throw new RuntimeException('Das Ergebnisarchiv ist nicht verifiziert.');
            }
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) throw new RuntimeException('Das Ergebnisarchiv ist nicht lesbar.');
            $metadata = json_decode($zip->getFromName('.madlen-release.json') ?: '{}', true);
            $zip->close();
            if (($metadata['sourceRevision'] ?? null) !== $publication->source_revision
                || ($metadata['contentChecksum'] ?? null) !== hash_file('sha256', $publication->manifest_path)) {
                throw new RuntimeException('Das Ergebnis gehört nicht zum angeforderten Inhaltsstand.');
            }
            $release = $this->activator->activate($id, $sequence, $archive, $sha256);
            try {
                DB::transaction(function () use ($publication, $release): void {
                    $publication = ProductionPublication::lockForUpdate()->findOrFail($publication->id);
                    if ($publication->status === 'active') return;
                    if ($project = Project::withTrashed()->find($publication->project_id)) {
                        // Never replace newer text/gallery edits with the packaged snapshot.
                        if ($publication->operation === 'publish') {
                            if ($project->trashed()) throw new RuntimeException('Das Projekt wurde inzwischen gelöscht.');
                            $project->update(['status' => 'published', 'published_at' => now()]);
                        } elseif ($publication->operation === 'unpublish') {
                            $project->update(['status' => 'unpublished']);
                        } elseif ($publication->operation === 'delete') {
                            $project->update(['status' => 'unpublished']);
                            $project->deleteQuietly();
                        }
                    }
                    ProductionPublication::where('status', 'active')->whereKeyNot($publication->id)->update(['status' => 'superseded']);
                    $publication->update([
                        'status' => 'active', 'target_release' => $release, 'activated_at' => now(),
                        'progress_message' => 'Der geprüfte öffentliche Stand ist aktiv.', 'error_message' => null,
                    ]);
                });
            } catch (\Throwable $error) {
                if ($old !== $release) $this->activator->restoreAfterFailedActivation($release, $old);
                throw $error;
            }
            return $release;
        });
    }

    public function rollback(string $release): string
    {
        return Cache::lock('madlen-production-publication', 180)->block(5, function () use ($release) {
            $target = ProductionPublication::where('target_release', $release)->firstOrFail();
            if (! in_array($target->status, ['active', 'superseded'], true)) {
                throw new RuntimeException('Nur ein früher aktiver Stand kann zurückgesetzt werden.');
            }
            if (ProductionPublication::whereIn('status', ['preparing', 'prepared', 'queued', 'dispatch_unknown', 'building', 'uploading'])->exists()) {
                throw new RuntimeException('Zuerst den laufenden Auftrag beenden; keine Rücksetzung während einer Veröffentlichung.');
            }
            $current = $this->activator->currentRelease();
            $this->activator->rollback($release);
            try {
                DB::transaction(function () use ($target): void {
                    $later = ProductionPublication::where('sequence', '>', $target->sequence)
                        ->whereIn('status', ['active', 'superseded'])->orderByDesc('sequence')->get();
                    foreach ($later as $publication) {
                        if ($publication->previous_project_state && ($project = Project::withTrashed()->find($publication->project_id))) {
                            $project->forceFill($publication->previous_project_state)->save();
                        }
                        $publication->update(['status' => 'rolled_back', 'progress_message' => 'Dieser Stand wurde zurückgesetzt.']);
                    }
                    $target->update(['status' => 'active', 'progress_message' => 'Dieser frühere Stand ist wieder aktiv.']);
                });
            } catch (\Throwable $error) {
                if ($current && $current !== $release) $this->activator->rollback($current);
                throw $error;
            }
            return $release;
        });
    }
}
