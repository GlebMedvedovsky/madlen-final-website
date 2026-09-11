<?php

namespace App\Services;

use App\Models\ContentEntry;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RevisionRestorer
{
    private const ALLOWED_MODELS = [Project::class, ContentEntry::class, Service::class];

    public function restore(Revision $revision): Model
    {
        if (! in_array($revision->revisionable_type, self::ALLOWED_MODELS, true)) {
            throw new RuntimeException('Dieser Änderungstyp kann nicht wiederhergestellt werden.');
        }

        $class = $revision->revisionable_type;
        $model = $class::withTrashed()->find($revision->revisionable_id);
        if (! $model) {
            throw new RuntimeException('Der zugehörige Datensatz ist nicht mehr vorhanden.');
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }
        $payload = array_intersect_key($revision->payload, array_flip($model->getFillable()));
        if ($model instanceof Project) {
            // Restoring text must not silently publish, unpublish or change a stable route.
            $payload = array_diff_key($payload, array_flip(['status','published_at','slug','source_key','source_imported_at']));
        }
        $model->forceFill($payload)->save();

        return $model->refresh();
    }

    public function supports(Revision $revision): bool
    {
        return in_array($revision->revisionable_type, self::ALLOWED_MODELS, true)
            && $this->model($revision) !== null;
    }

    public function title(Revision $revision): string
    {
        $model = $this->model($revision);
        return (string) ($model?->title_de ?? $model?->key ?? $revision->revisionable_id);
    }

    private function model(Revision $revision): ?Model
    {
        if (! in_array($revision->revisionable_type, self::ALLOWED_MODELS, true)) {
            return null;
        }

        $class = $revision->revisionable_type;
        return $class::withTrashed()->find($revision->revisionable_id);
    }
}
