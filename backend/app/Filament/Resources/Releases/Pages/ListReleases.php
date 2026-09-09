<?php

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Services\BackupService;
use App\Services\PreviewBuilder;
use App\Services\ProductionPublisher;
use App\Services\ReleasePublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReleases extends ListRecords
{
    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Gesamte Vorschau')
                ->icon('heroicon-o-eye')
                ->action(function () {
                    try {
                        $preview = app(PreviewBuilder::class)->build();

                        return redirect()->to(route('admin.preview', ['token' => $preview->token]));
                    } catch (\Throwable $error) {
                        report($error);
                        Notification::make()
                            ->title('Vorschau konnte nicht erstellt werden')
                            ->body('Bitte versuchen Sie es erneut. Der veröffentlichte Stand wurde nicht verändert.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return null;
                    }
                }),
            Action::make('publish')
                ->label('Website lokal veröffentlichen')
                ->icon('heroicon-o-cloud-arrow-up')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $release = app(ReleasePublisher::class)->publish();
                        Notification::make()->title("Release {$release->version} veröffentlicht")->body(config('madlen.public_url'))->success()->send();
                    } catch (\Throwable $error) {
                        Notification::make()->title('Veröffentlichung fehlgeschlagen')->body(mb_substr($error->getMessage(), 0, 500))->danger()->persistent()->send();
                    }
                }),
            Action::make('publishProduction')
                ->label('Website produktiv veröffentlichen')
                ->icon('heroicon-o-globe-alt')
                ->visible(fn (): bool => config('madlen.production_connected') && config('madlen.production_publisher') === 'github-actions')
                ->requiresConfirmation()
                ->modalDescription('Ein unveränderlicher Inhaltsstand wird an den externen Build übergeben. Der bisherige öffentliche Stand bleibt bis zur vollständigen Prüfung aktiv.')
                ->action(function (): void {
                    try {
                        $publication = app(ProductionPublisher::class)->publish();
                        Notification::make()
                            ->title("Produktiv-Auftrag {$publication->sequence} übergeben")
                            ->body('Der externe Build wurde gestartet. Dies ist noch keine Bestätigung der öffentlichen Aktivierung.')
                            ->success()
                            ->send();
                    } catch (\Throwable $error) {
                        Notification::make()
                            ->title('Produktiv-Veröffentlichung nicht gestartet')
                            ->body(mb_substr($error->getMessage(), 0, 500))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('openSite')
                ->label('Lokale Website öffnen')
                ->url(config('madlen.public_url'))
                ->openUrlInNewTab(),
            Action::make('backup')
                ->label('Backup erstellen')
                ->icon('heroicon-o-archive-box')
                ->action(function (): void {
                    $backup = app(BackupService::class)->create();
                    Notification::make()->title("Backup {$backup->id} erstellt")->success()->send();
                }),
        ];
    }
}
