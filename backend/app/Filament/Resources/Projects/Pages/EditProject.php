<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Services\PreviewBuilder;
use App\Services\ReleasePublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vorschau')
                ->icon('heroicon-o-eye')
                ->action(function () {
                    try {
                        $preview = app(PreviewBuilder::class)->build();

                        return redirect()->to(route('admin.preview', ['token' => $preview->token]));
                    } catch (\Throwable $error) {
                        $notConfigured = $error->getMessage() === PreviewBuilder::NOT_CONFIGURED_MESSAGE;
                        if (! $notConfigured) {
                            report($error);
                        }
                        Notification::make()
                            ->title($notConfigured ? PreviewBuilder::NOT_CONFIGURED_MESSAGE : 'Vorschau konnte nicht erstellt werden')
                            ->body($notConfigured
                                ? 'Auf diesem Server fehlt der lokale Build-Dienst. Die externe Vorschau kann später verbunden werden.'
                                : 'Bitte versuchen Sie es erneut. Der veröffentlichte Stand wurde nicht verändert.')
                            ->color($notConfigured ? 'warning' : 'danger')
                            ->persistent()
                            ->send();

                        return null;
                    }
                }),
            Action::make('publish')
                ->label('Veröffentlichen')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Die komplette Website wird aus einem unveränderlichen Inhaltsstand neu gebaut. Der bisherige Stand bleibt bei einem Fehler aktiv.')
                ->action(function (): void {
                    $record = $this->getRecord();
                    if (! $record->isTranslationReady()) {
                        Notification::make()->title('Veröffentlichung nicht möglich')->body('Titel, Beschreibung und Titelbild müssen auf Deutsch und Englisch vollständig sein.')->danger()->send();

                        return;
                    }
                    $previousStatus = $record->status;
                    $record->update(['status' => 'published']);
                    try {
                        $release = app(ReleasePublisher::class)->publish();
                        $record->update(['published_at' => now()]);
                        Notification::make()->title("Release {$release->version} veröffentlicht")->success()->send();
                    } catch (\Throwable $error) {
                        $record->update(['status' => $previousStatus]);
                        Notification::make()->title('Build fehlgeschlagen')->body(mb_substr($error->getMessage(), 0, 500))->danger()->persistent()->send();
                    }
                }),
            Action::make('unpublish')
                ->label('Nicht mehr veröffentlichen')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === 'published')
                ->action(fn () => $this->getRecord()->update(['status' => 'unpublished'])),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
