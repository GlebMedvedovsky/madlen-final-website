<?php

namespace App\Filament\Resources\ContentEntries\Pages;

use App\Filament\Resources\ContentEntries\ContentEntryResource;
use Filament\Resources\Pages\EditRecord;

class EditContentEntry extends EditRecord
{
    protected static string $resource = ContentEntryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['de', 'en'] as $locale) {
            $value = $data["value_{$locale}"] ?? '';
            if (($data['type'] ?? null) === 'json') {
                $data["structured_{$locale}"] = json_decode((string) $value, true) ?: [];
            } else {
                $data["plain_{$locale}"] = (string) $value;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        foreach (['de', 'en'] as $locale) {
            if ($record->type === 'json') {
                $original = json_decode((string) $record->{"value_{$locale}"}, true) ?: [];
                $edited = $data["structured_{$locale}"] ?? [];
                $merged = $this->mergePreservingUnknownFields($original, $edited);
                $data["value_{$locale}"] = $merged === $original
                    ? (string) $record->{"value_{$locale}"}
                    : json_encode(
                        $merged,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
                    );
            } else {
                $data["value_{$locale}"] = (string) ($data["plain_{$locale}"] ?? '');
            }

            unset($data["structured_{$locale}"], $data["plain_{$locale}"]);
        }

        unset($data['key'], $data['group_name'], $data['type'], $data['position']);

        return $data;
    }

    private function mergePreservingUnknownFields(array $original, array $edited): array
    {
        foreach ($edited as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $original[$key] = $value;
                continue;
            }

            if (is_array($value) && is_array($original[$key] ?? null)) {
                $original[$key] = $this->mergePreservingUnknownFields($original[$key], $value);
                continue;
            }

            $original[$key] = $value;
        }

        return $original;
    }
}
