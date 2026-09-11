<?php

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Services\BackupService;
use App\Services\PreviewBuilder;
use App\Services\ProductionPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListReleases extends ListRecords
{
    protected static string $resource = ReleaseResource::class;

    public string $productionRequestId = '';

    public function mount(): void
    {
        parent::mount();
        $this->productionRequestId = (string) Str::uuid();
    }

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
            Action::make('publishProduction')
                ->label('Website produktiv veröffentlichen')
                ->icon('heroicon-o-globe-alt')
                ->visible(fn (): bool => config('madlen.production_connected') && config('madlen.production_publisher') === 'github-actions')
                ->requiresConfirmation()
                ->modalDescription('Ein unveränderlicher Inhaltsstand wird an den externen Build übergeben. Der bisherige öffentliche Stand bleibt bis zur vollständigen Prüfung aktiv.')
                ->action(function (): void {
                    try {
                        $publication = app(ProductionPublisher::class)->publish($this->productionRequestId);
                        Notification::make()
                            ->title("Produktiv-Auftrag {$publication->sequence}")
                            ->body($publication->progress_message)
                            ->color($publication->status === 'failed' ? 'danger' : 'info')
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
                ->label('Öffentliche Website öffnen')
                ->url(config('madlen.public_site_url'))
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
