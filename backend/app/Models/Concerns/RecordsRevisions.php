<?php

namespace App\Models\Concerns;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait RecordsRevisions
{
    public static function bootRecordsRevisions(): void
    {
        foreach (['updating', 'deleting', 'restoring'] as $event) {
            static::{$event}(function (Model $model) use ($event): void {
                if (! $model->exists) {
                    return;
                }

                Revision::query()->create([
                    'revisionable_type' => $model->getMorphClass(),
                    'revisionable_id' => (string) $model->getKey(),
                    'payload' => $model->getOriginal(),
                    'event' => $event,
                    'user_id' => Auth::id(),
                ]);
            });
        }
    }

    public function revisions()
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest();
    }
}
