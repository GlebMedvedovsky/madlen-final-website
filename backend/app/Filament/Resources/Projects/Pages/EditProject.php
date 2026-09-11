<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\PreviewBuild;
use App\Models\ProductionPublication;
use App\Services\PreviewBuilder;
use App\Services\ProjectPreviewSnapshotFactory;
use App\Services\ProductionPublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    public string $previewRequestId = '';

    public int $previewRequestAttempt = 0;

    public array $productionRequestIds = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->resetProductionRequests();
    }

    protected function afterSave(): void
    {
        $this->resetProductionRequests();
    }

    private function resetProductionRequests(): void
    {
        foreach (['publish', 'unpublish', 'delete'] as $operation) {
            $this->productionRequestIds[$operation] = (string) Str::uuid();
        }
    }

    private function requestProduction(string $operation): void
    {
        $requestId = $this->productionRequestIds[$operation] ?? '';
        $record = $this->getRecord();
        try {
            // Lost Livewire replies reuse the identity from the original editor snapshot.
            if (! ProductionPublication::where('request_id', $requestId)->exists() && $operation === 'publish') {
                $this->form->validate();
                $this->save(false, false);
                $this->productionRequestIds[$operation] = $requestId;
            }
            $publication = app(ProductionPublisher::class)->publish($requestId, $record->refresh(), $operation);
            // Only the acknowledged Livewire response advances the editor's identity.
            // A lost response leaves the browser's original snapshot/request ID intact;
            // replaying it returns the existing publication without saving again.
            $this->productionRequestIds[$operation] = (string) Str::uuid();
            Notification::make()->title("Produktiv-Auftrag {$publication->sequence}")
                ->body($publication->progress_message)->persistent()
                ->color($publication->status === 'failed' ? 'danger' : 'info')->send();
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Notification::make()->title('Veröffentlichung nicht gestartet')
                ->body($error->getMessage())->danger()->persistent()->send();
        }
    }

    private const PREPARE_PREVIEW_TAB_JS = <<<'JS'
if (window.__madlenProjectPreviewPending) {
    $event.preventDefault();
    $event.stopImmediatePropagation();
    return;
}
const previousOperation = window.__madlenProjectPreviewOperation;
const isRetry = previousOperation?.retry === true && typeof previousOperation.requestId === 'string';
const requestId = isRetry
    ? previousOperation.requestId
    : (window.crypto?.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        return (character === 'x' ? random : ((random & 0x3) | 0x8)).toString(16);
    }));
const attempt = (window.__madlenProjectPreviewAttemptCounter ?? 0) + 1;
window.__madlenProjectPreviewAttemptCounter = attempt;
window.__madlenProjectPreviewPending = true;
let previewTab = isRetry ? previousOperation.tab : null;
if (! previewTab || previewTab.closed) {
    previewTab = window.open('', `madlen-project-preview-${Date.now()}`);
}
const operation = { requestId, attempt, tab: previewTab, retry: false, watchdog: null };
window.__madlenProjectPreviewOperation = operation;

const oldRecovery = document.getElementById('madlen-project-preview-recovery');
if (oldRecovery) oldRecovery.remove();

const renderPreviewTab = (title, text) => {
    if (! previewTab || previewTab.closed) return;
    previewTab.opener = null;
    const previewDocument = previewTab.document;
    previewDocument.documentElement.lang = 'de';
    previewDocument.title = `${title} · Madlen`;
    previewDocument.body.style.cssText = 'margin:0;min-height:100vh;display:grid;place-items:center;background:#fffaf5;color:#111;font:16px/1.55 Arial,sans-serif';
    previewDocument.body.replaceChildren();
    const placeholder = previewDocument.createElement('main');
    placeholder.style.cssText = 'width:min(34rem,calc(100% - 2rem));box-sizing:border-box;padding:2rem;border:1px solid #eadde0;background:#fff';
    const heading = previewDocument.createElement('h1');
    heading.style.cssText = 'margin:0 0 1rem;color:#0338da';
    heading.textContent = title;
    const message = previewDocument.createElement('p');
    message.textContent = text;
    placeholder.append(heading, message);
    previewDocument.body.append(placeholder);
};

const triggerButton = $event.currentTarget;
const csrfRefreshUrl = triggerButton.dataset.previewCsrfUrl;
const loginUrl = triggerButton.dataset.previewLoginUrl;
const refreshCsrfToken = async () => {
    if (! csrfRefreshUrl) throw new Error('Die CSRF-Aktualisierungsadresse fehlt.');
    const response = await window.fetch(csrfRefreshUrl, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (! response.ok) throw new Error(`CSRF-Aktualisierung fehlgeschlagen (${response.status}).`);
    const payload = await response.json();
    const token = typeof payload?.csrfToken === 'string' ? payload.csrfToken.trim() : '';
    if (token.length < 20) throw new Error('Die CSRF-Antwort ist ungültig.');

    let updated = false;
    document.querySelectorAll('meta[name=csrf-token]').forEach((element) => {
        element.setAttribute('content', token);
        updated = true;
    });
    document.querySelectorAll('[data-csrf]').forEach((element) => {
        element.setAttribute('data-csrf', token);
        updated = true;
    });
    if (window.livewireScriptConfig && typeof window.livewireScriptConfig === 'object') {
        window.livewireScriptConfig.csrf = token;
        updated = true;
    }
    if (! updated) throw new Error('Livewire-CSRF-Quelle wurde nicht gefunden.');
};
window.__madlenProjectPreviewRecover = (failedRequestId, failedAttempt, status = 503) => {
    const current = window.__madlenProjectPreviewOperation;
    if (! current || current.requestId !== failedRequestId || current.attempt !== failedAttempt) return;
    if (current.watchdog) window.clearTimeout(current.watchdog);
    current.watchdog = null;
    current.retry = true;
    window.__madlenProjectPreviewPending = false;

    const statusCode = Number(status || 503);
    const sessionExpired = statusCode === 419;
    const serverFailed = statusCode >= 500 && statusCode !== 503;
    const title = sessionExpired
        ? 'Sitzung abgelaufen'
        : (serverFailed ? 'Serverantwort fehlgeschlagen' : 'Verbindung unterbrochen');
    const text = sessionExpired
        ? 'Ihre Eingaben bleiben in diesem Editor-Tab sichtbar. Öffnen Sie die Anmeldung in einem neuen Tab und versuchen Sie danach denselben Vorgang erneut.'
        : 'Die Antwort der Vorschau ist verloren gegangen. Ihre Eingaben bleiben erhalten; eine Wiederholung fragt denselben Vorgang ab und startet keine zweite Vorschau.';
    renderPreviewTab(title, `${text} Kehren Sie zum Editor zurück.`);

    const existing = document.getElementById('madlen-project-preview-recovery');
    if (existing) existing.remove();
    const panel = document.createElement('section');
    panel.id = 'madlen-project-preview-recovery';
    panel.setAttribute('role', 'alert');
    panel.setAttribute('aria-live', 'assertive');
    panel.style.cssText = 'position:fixed;z-index:60;right:1rem;top:5rem;width:min(30rem,calc(100vw - 2rem));box-sizing:border-box;padding:1rem 1.1rem;border:1px solid #e8c9cf;border-radius:.75rem;background:#fffaf5;color:#18181b;box-shadow:0 12px 35px rgba(24,24,27,.18);font:14px/1.5 Arial,sans-serif';
    const panelTitle = document.createElement('strong');
    panelTitle.style.cssText = 'display:block;margin-bottom:.35rem;color:#0338da;font-size:16px';
    panelTitle.textContent = title;
    const panelText = document.createElement('p');
    panelText.style.cssText = 'margin:0 0 .75rem';
    panelText.textContent = text;
    const retryButton = document.createElement('button');
    retryButton.type = 'button';
    retryButton.style.cssText = 'border:0;border-radius:999px;padding:.65rem 1rem;background:#0338da;color:#fff;font-weight:700;cursor:pointer';
    retryButton.textContent = 'Erneut versuchen';
    retryButton.addEventListener('click', async () => {
        const pending = window.__madlenProjectPreviewOperation;
        if (! pending || pending.requestId !== failedRequestId || pending.attempt !== failedAttempt || ! pending.retry) return;
        retryButton.disabled = true;
        retryButton.textContent = sessionExpired ? 'Sitzung wird geprüft …' : 'Vorschau wird erneut angefragt …';
        if (sessionExpired) {
            try {
                await refreshCsrfToken();
            } catch {
                const stillPending = window.__madlenProjectPreviewOperation;
                if (! stillPending || stillPending.requestId !== failedRequestId || stillPending.attempt !== failedAttempt) return;
                panelText.textContent = 'Die Anmeldung ist noch nicht wiederhergestellt. Öffnen Sie die Anmeldung in einem neuen Tab, melden Sie sich an und versuchen Sie danach erneut. Ihre Eingaben bleiben erhalten.';
                retryButton.disabled = false;
                retryButton.textContent = 'Erneut versuchen';

                return;
            }
        }
        const stillPending = window.__madlenProjectPreviewOperation;
        if (! stillPending || stillPending.requestId !== failedRequestId || stillPending.attempt !== failedAttempt || ! stillPending.retry) return;
        panel.remove();
        triggerButton.click();
    });
    panel.append(panelTitle, panelText, retryButton);
    if (sessionExpired) {
        const loginLink = document.createElement('a');
        loginLink.href = loginUrl;
        loginLink.target = '_blank';
        loginLink.rel = 'noopener noreferrer';
        loginLink.style.cssText = 'display:inline-block;margin-left:.75rem;color:#0338da;text-decoration:underline';
        loginLink.textContent = 'Anmeldung öffnen';
        panel.append(loginLink);
    }
    document.body.append(panel);
};

renderPreviewTab('Vorschau wird vorbereitet', 'Der aktuelle Formularstand wird unveränderlich übernommen.');
$wire.$set('previewRequestId', requestId, false);
$wire.$set('previewRequestAttempt', attempt, false);

const componentId = $wire.$id;
const cleanupRequestHook = window.Livewire.hook('request', ({ options, fail }) => {
    let payload;
    try {
        payload = typeof options.body === 'string' ? JSON.parse(options.body) : options.body;
    } catch {
        return;
    }
    const isThisPreviewRequest = payload?.components?.some((componentPayload) => {
        try {
            const snapshot = JSON.parse(componentPayload.snapshot);
            return snapshot.memo?.id === componentId
                && componentPayload.calls?.some((call) => call.method === 'mountAction' && call.params?.[0] === 'preview');
        } catch {
            return false;
        }
    });
    if (! isThisPreviewRequest) return;
    cleanupRequestHook();
    fail(({ status, preventDefault }) => {
        preventDefault?.();
        window.__madlenProjectPreviewRecover?.(requestId, attempt, status);
    });
});

operation.watchdog = window.setTimeout(() => {
    window.__madlenProjectPreviewRecover?.(requestId, attempt, 503);
}, 90000);
JS;

    private const OPEN_PREVIEW_TAB_JS = <<<'JS'
(url, requestId, attempt) => {
    const operation = window.__madlenProjectPreviewOperation;
    if (! operation || operation.requestId !== requestId || operation.attempt !== attempt) return;
    if (operation.watchdog) window.clearTimeout(operation.watchdog);
    const previewTab = operation.tab;
    if (previewTab && ! previewTab.closed) {
        previewTab.location.assign(url);
        previewTab.focus();
    }
    document.getElementById('madlen-project-preview-recovery')?.remove();
    window.__madlenProjectPreviewOperation = null;
    window.__madlenProjectPreviewRecover = null;
    window.__madlenProjectPreviewPending = false;
}
JS;

    private const CLOSE_PREVIEW_TAB_JS = <<<'JS'
(requestId, attempt) => {
    const operation = window.__madlenProjectPreviewOperation;
    if (! operation || operation.requestId !== requestId || operation.attempt !== attempt) return;
    if (operation.watchdog) window.clearTimeout(operation.watchdog);
    const previewTab = operation.tab;
    if (previewTab && ! previewTab.closed) previewTab.close();
    document.getElementById('madlen-project-preview-recovery')?.remove();
    window.__madlenProjectPreviewOperation = null;
    window.__madlenProjectPreviewRecover = null;
    window.__madlenProjectPreviewPending = false;
}
JS;

    private const RETRY_PREVIEW_TAB_JS = <<<'JS'
(requestId, attempt, status) => {
    window.__madlenProjectPreviewRecover?.(requestId, attempt, status);
}
JS;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Vorschau')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'x-on:click.capture' => self::PREPARE_PREVIEW_TAB_JS,
                    'data-preview-csrf-url' => route('admin.session.csrf', absolute: false),
                    'data-preview-login-url' => route('filament.admin.auth.login', absolute: false),
                ])
                ->action(function (): void {
                    $requestId = (string) $this->previewRequestId;
                    $attempt = (int) $this->previewRequestAttempt;
                    if (! Str::isUuid($requestId) || $attempt < 1) {
                        $this->closePendingPreviewTab($requestId, $attempt);
                        Notification::make()
                            ->title('Vorschau konnte nicht sicher gestartet werden')
                            ->body('Ihre Eingaben bleiben erhalten. Bitte versuchen Sie es erneut.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    if ($existing = $this->existingPreview($requestId)) {
                        $this->openPreviewTab($existing, $requestId, $attempt, reused: true);

                        return;
                    }

                    try {
                        // Unlike Schema::getState(), validate() does not persist relationship repeaters.
                        $this->form->validate();
                        $snapshot = app(ProjectPreviewSnapshotFactory::class)->make(
                            $this->getRecord(),
                            $this->data ?? [],
                        );
                    } catch (ValidationException $error) {
                        $this->closePendingPreviewTab($requestId, $attempt);
                        Notification::make()
                            ->title('Vorschau kann noch nicht erstellt werden')
                            ->body('Bitte korrigieren Sie die markierten Felder. Ihre Eingaben bleiben im Editor erhalten.')
                            ->warning()
                            ->persistent()
                            ->send();
                        throw $error;
                    } catch (\Throwable $error) {
                        $this->closePendingPreviewTab($requestId, $attempt);
                        report($error);
                        Notification::make()
                            ->title('Vorschau konnte nicht vorbereitet werden')
                            ->body('Ihre Eingaben bleiben im Editor erhalten. Bitte versuchen Sie es erneut.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $lock = Cache::lock($this->previewLockKey($requestId), 180);
                    if (! $lock->get()) {
                        if ($existing = $this->existingPreview($requestId)) {
                            $this->openPreviewTab($existing, $requestId, $attempt, reused: true);

                            return;
                        }

                        $this->js(self::RETRY_PREVIEW_TAB_JS, $requestId, $attempt, 409);
                        Notification::make()
                            ->title('Vorschau wird bereits vorbereitet')
                            ->body('Ihre Eingaben bleiben erhalten. Versuchen Sie denselben Vorgang gleich erneut; es wird keine zweite Vorschau gestartet.')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        if ($existing = $this->existingPreview($requestId)) {
                            $this->openPreviewTab($existing, $requestId, $attempt, reused: true);

                            return;
                        }

                        $preview = app(PreviewBuilder::class)->build($snapshot, $requestId);
                        $this->openPreviewTab($preview, $requestId, $attempt);
                    } catch (\Throwable $error) {
                        if (($existing = $this->existingPreview($requestId)) && $existing->status !== 'failed') {
                            $this->openPreviewTab($existing, $requestId, $attempt, reused: true);

                            return;
                        }

                        $this->closePendingPreviewTab($requestId, $attempt);
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
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->modalDescription('Die aktuellen Eingaben werden gespeichert und veröffentlicht. Der öffentliche Stand wird erst nach erfolgreicher Prüfung ersetzt.')
                ->action(function (): void {
                    $this->requestProduction('publish');
                }),
            Action::make('unpublish')
                ->label('Nicht mehr veröffentlichen')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === 'published')
                ->modalDescription('Das Projekt wird nach erfolgreichem Website-Build aus der öffentlichen Website entfernt.')
                ->action(fn () => $this->requestProduction('unpublish')),
            Action::make('delete')->label('Löschen')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->modalDescription('Ein öffentliches Projekt wird zuerst aus der Website entfernt. Medien bleiben erhalten.')
                ->action(function (): void {
                    if ($this->getRecord()->status === 'published') {
                        $this->requestProduction('delete');
                    } else {
                        $this->getRecord()->delete();
                        $this->redirect(ProjectResource::getUrl());
                    }
                }),
            RestoreAction::make()->after(function (): void {
                $this->getRecord()->update(['status' => 'draft', 'published_at' => null]);
            }),
        ];
    }

    private function previewLockKey(string $requestId): string
    {
        return 'madlen-project-preview:'.auth()->id().':'.$this->getRecord()->getKey().':'.$requestId;
    }

    private function existingPreview(string $requestId): ?PreviewBuild
    {
        return PreviewBuild::query()
            ->where('request_id', $requestId)
            ->where('user_id', auth()->id())
            ->where('target_path', 'portfolio/'.$this->getRecord()->slug)
            ->first();
    }

    private function openPreviewTab(PreviewBuild $preview, string $requestId, int $attempt, bool $reused = false): void
    {
        $previewUrl = route('admin.preview', [
            'token' => $preview->token,
            'path' => $preview->target_path,
        ], absolute: false);
        $this->js(self::OPEN_PREVIEW_TAB_JS, $previewUrl, $requestId, $attempt);

        Notification::make()
            ->title($reused ? 'Bereits gestartete Projektvorschau gefunden' : 'Projektvorschau wurde gestartet')
            ->body($reused
                ? 'Der frühere Vorgang wird weiterverwendet; es wurde keine zweite Vorschau gestartet.'
                : 'Die Vorschau öffnet sich in einem neuen Tab. Falls Ihr Browser den Tab blockiert hat, verwenden Sie den folgenden Link.')
            ->success()
            ->actions([
                Action::make('openProjectPreview')
                    ->label('Vorschau öffnen')
                    ->url($previewUrl, shouldOpenInNewTab: true),
            ])
            ->persistent()
            ->send();
    }

    private function closePendingPreviewTab(string $requestId, int $attempt): void
    {
        $this->js(self::CLOSE_PREVIEW_TAB_JS, $requestId, $attempt);
    }
}
