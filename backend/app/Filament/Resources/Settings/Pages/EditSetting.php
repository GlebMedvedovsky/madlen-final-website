<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Resources\Pages\EditRecord;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $value = is_array($data['value'] ?? null) && array_key_exists('value', $data['value'])
            ? $data['value']['value']
            : ($data['value'] ?? '');

        if (($data['key'] ?? null) === 'primaryLocale') {
            $data['editor_locale'] = $value;
        } else {
            $data['editor_text'] = $value;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['value'] = [
            'value' => $this->getRecord()->key === 'primaryLocale'
                ? ($data['editor_locale'] ?? 'de')
                : ($data['editor_text'] ?? ''),
        ];

        unset($data['editor_locale'], $data['editor_text'], $data['key'], $data['label_de']);

        return $data;
    }
}
