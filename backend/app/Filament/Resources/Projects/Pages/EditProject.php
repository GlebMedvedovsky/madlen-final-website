<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Services\PreviewBuilder;
use App\Services\ProjectPreviewSnapshotFactory;
use App\Services\ReleasePublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    private const PREPARE_PREVIEW_TAB_JS = <<<'JS'
if (window.__madlenProjectPreviewPending) {
    $event.preventDefault();
    $event.stopImmediatePropagation();
    return;
}
window.__madlenProjectPreviewPending = true;
const previewTab = window.open('', `madlen-project-preview-${Date.now()}`);
window.__madlenProjectPreviewTab = previewTab;
if (previewTab) {
    previewTab.opener = null;
    const previewDocument = previewTab.document;
    previewDocument.documentElement.lang = 'de';
    previewDocument.title = 'Vorschau wird vorbereitet · Madlen';
    previewDocument.body.style.cssText = 'margin:0;min-height:100vh;display:grid;place-items:center;background:#fffaf5;color:#111;font:16px/1.55 Arial,sans-serif';
    previewDocument.body.replaceChildren();
    const placeholder = previewDocument.createElement('main');
    placeholder.style.cssText = 'width:min(34rem,calc(100% - 2rem));box-sizing:border-box;padding:2rem;border:1px solid #eadde0;background:#fff';
    const heading = previewDocument.createElement('h1');
    heading.style.cssText = 'margin:0 0 1rem;color:#0338da';
    heading.textContent = 'Vorschau wird vorbereitet';
    const message = previewDocument.createElement('p');
    message.textContent = 'Der aktuelle Formularstand wird unveränderlich übernommen.';
    placeholder.append(heading, message);
    previewDocument.body.append(placeholder);
}
JS;

    private const OPEN_PREVIEW_TAB_JS = <<<'JS'
(url) => {
    const previewTab = window.__madlenProjectPreviewTab;
    if (previewTab && ! previewTab.closed) {
        previewTab.location.assign(url);
        previewTab.focus();
    }
    window.__madlenProjectPreviewTab = null;
    window.__madlenProjectPreviewPending = false;
}
JS;

    private const CLOSE_PREVIEW_TAB_JS = <<<'JS'
() => {
    const previewTab = window.__madlenProjectPreviewTab;
    if (previewTab && ! previewTab.closed) previewTab.close();
    window.__madlenProjectPreviewTab = null;
    window.__madlenProjectPreviewPending = false;
}
JS;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vorschau')
                ->icon('heroicon-o-eye')
                ->extraAttributes(['x-on:click.capture' => self::PREPARE_PREVIEW_TAB_JS])
                ->action(function (): void {
                    try {
                        // Unlike Schema::getState(), validate() does not persist relationship repeaters.
                        $this->form->validate();
                        $snapshot = app(ProjectPreviewSnapshotFactory::class)->make(
                            $this->getRecord(),
                            $this->data ?? [],
                        );
                    } catch (ValidationException $error) {
                        $this->closePendingPreviewTab();
                        Notification::make()
                            ->title('Vorschau kann noch nicht erstellt werden')
                            ->body('Bitte korrigieren Sie die markierten Felder. Ihre Eingaben bleiben im Editor erhalten.')
                            ->warning()
                            ->persistent()
                            ->send();
                        throw $error;
                    } catch (\Throwable $error) {
                        $this->closePendingPreviewTab();
                        report($error);
                        Notification::make()
                            ->title('Vorschau konnte nicht vorbereitet werden')
                            ->body('Ihre Eingaben bleiben im Editor erhalten. Bitte versuchen Sie es erneut.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $lock = Cache::lock($this->previewLockKey(), 180);
                    if (! $lock->get()) {
                        $this->closePendingPreviewTab();
                        Notification::make()
                            ->title('Vorschau wird bereits vorbereitet')
                            ->body('Bitte warten Sie, bis der bereits gestartete Vorgang abgeschlossen ist. Ihre Eingaben bleiben erhalten.')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        $preview = app(PreviewBuilder::class)->build($snapshot);
                        $previewUrl = route('admin.preview', [
                            'token' => $preview->token,
                            'path' => $snapshot->targetPath(),
                        ], absolute: false);
                        $this->js(self::OPEN_PREVIEW_TAB_JS, $previewUrl);

                        Notification::make()
                            ->title('Projektvorschau wurde gestartet')
                            ->body('Die Vorschau öffnet sich in einem neuen Tab. Falls Ihr Browser den Tab blockiert hat, verwenden Sie den folgenden Link.')
                            ->success()
                            ->actions([
                                Action::make('openProjectPreview')
                                    ->label('Vorschau öffnen')
                                    ->url($previewUrl, shouldOpenInNewTab: true),
                            ])
                            ->persistent()
                            ->send();
                    } catch (\Throwable $error) {
                        $this->closePendingPreviewTab();
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
                    } finally {
                        $lock->release();
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

    private function previewLockKey(): string
    {
        return 'madlen-project-preview:'.auth()->id().':'.$this->getRecord()->getKey();
    }

    private function closePendingPreviewTab(): void
    {
        $this->js(self::CLOSE_PREVIEW_TAB_JS);
    }
}
