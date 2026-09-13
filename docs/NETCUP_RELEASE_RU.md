# Madlen release and recovery

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Scope and responsibility

I am Gleb Medvedovskyy. I designed and manage the release architecture, configuration and approval process. Implementation, packaging and testing use AI-assisted workflows; I accept releases on recorded evidence rather than claiming every step was performed manually.

Madlen already runs on Netcup. This is an update/recovery procedure, not permission to reinstall the CMS or repeat an initial import. Server account details, private paths and credentials belong in the operator's private parameter sheet.

The consolidated [backend allowlist](../scripts/release/backend-files.json) contains **36 production files**: 35 PHP and one JSON. Relative to the preview-flow reference, six classes are new: ProductionReleaseManager, RetryProductionPublication, RequestPasswordReset, ResetPassword, AuthenticateAdminSession and AdminResetPassword.

One additional migration, `2026_09_12_000006_add_production_operation_identity`, adds request identity, project/operation, runner and previous lifecycle state. Password recovery uses the existing reset-token table with **no new migration**. Existing preview classes and the target-path/request-identity migration remain intact.

### 2. Package identity and parameters

I prepare a final kit from a clean checkout of the exact reviewed revision. Every payload file must match its Git blob; full source metadata, archive SHA-256 and internal SHA-256 must agree after extraction. Environment files, credentials, database content and media are not code payload.

The existing `build-kit.mjs` produces a **candidate** marked `committed: false`; running it does not automatically certify a final merged archive. Final acceptance requires explicit source/blob/metadata checks. I do not relabel a working-tree candidate as a future merge.

The installer's reference revision `88e1af1b55e56cdf47085bbba11d3c130831a1bc` defines comparison hashes, not today's installed state. `overlay.php inspect` obtains actual server hashes. Unexpected differences stop installation; Git hashes never replace observed server hashes.

Before commands, I privately obtain the verified existing `BACKEND`, extracted `KIT`, private backup parent `BACKUPS`, trusted `COMPOSER_PHAR` and independently verified `COMPOSER_SHA256`. Required unset/empty parameters cause the examples to fail. A freshly calculated hash of an unknown PHAR is not a source of trust.

### 3. Archive and read-only preflight

I compare the archive checksum with an independently received release record, inspect its contents and extract only into a new private staging directory—not over the application. Then I verify internal checksums and run:


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
cd "$KIT"
command -v sha256sum >/dev/null
sha256sum -c SHA256SUMS
"$PHP_BIN" preflight.php "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
"$PHP_BIN" overlay.php inspect "$BACKEND"
```


Preflight is read-only: no code apply, migrations or publication. It checks PHP 8.4/extensions/functions, the installed layout and database state using read-only queries. Its output may contain operational paths/settings; keep it private or redact it.

Netcup uses `/usr/local/php84/bin/php`, `/usr/bin/mysqldump` and `/usr/bin/mysql`. Node/npm, cmp, stat and Composer in PATH are not required. The trusted private Composer PHAR runs through PHP 8.4 with plugins/scripts disabled. Missing-tool errors name the actual tool.

Before mutation I confirm actual FastCGI permissions/limits, disk space, session/cache configuration, migration history, no in-flight jobs and a usable rollback target. CLI does not prove FastCGI behaviour. Installed dependencies must match the reviewed lockfile; this overlay does not update vendor.

### 4. Backup, apply and autoload

In an agreed maintenance window, I pause editing and new runner dispatch without taking down the static website. I preserve the configuration/application key, dependency lockfiles, old document-root setting and autoload state. Backup locations must be private with verified ownership, never world-writable.

After preflight/source comparison, I run the checked kit. The backup helper saves database, media and private environment; these files stay outside Git and the webroot.


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT" && test -n "$BACKUPS"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/preflight.php" --tools "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
test -d "$BACKUPS" && test ! -L "$BACKUPS"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
CODE_BACKUP="$BACKUPS/code-$STAMP"
DATA_BACKUP="$BACKUPS/data-$STAMP"
test ! -e "$CODE_BACKUP" && test ! -L "$CODE_BACKUP"
test ! -e "$DATA_BACKUP" && test ! -L "$DATA_BACKUP"
cd "$KIT"
(umask 077; set -C; "$PHP_BIN" overlay.php inspect "$BACKEND" > "installed-state-$STAMP.json")
"$PHP_BIN" overlay.php check "$BACKEND" "$CODE_BACKUP" "installed-state-$STAMP.json"
"$PHP_BIN" backup-installed.php "$BACKEND" "$DATA_BACKUP"
(cd "$DATA_BACKUP" && sha256sum -c backup.sha256)
"$PHP_BIN" overlay.php apply "$BACKEND" "$CODE_BACKUP" "installed-state-$STAMP.json"
cd "$BACKEND"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
"$PHP_BIN" -r 'require "vendor/autoload.php"; foreach ([
"App\\Services\\ProductionReleaseManager",
"App\\Console\\Commands\\RetryProductionPublication",
"App\\Filament\\Auth\\RequestPasswordReset",
"App\\Filament\\Auth\\ResetPassword",
"App\\Http\\Middleware\\AuthenticateAdminSession",
"App\\Notifications\\AdminResetPassword",
"App\\Data\\ProjectPreviewSnapshot",
"App\\Services\\ProjectPreviewSnapshotFactory",
"App\\Http\\Controllers\\AdminSessionController"
] as $class) { if (!class_exists($class)) {fwrite(STDERR, "Missing class: ".$class."\n"); exit(1);} } echo "Autoload OK\n";'
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan route:list --path=password-reset
"$PHP_BIN" artisan migrate:status --no-ansi
```


I record `CODE_BACKUP`, `DATA_BACKUP` and the inspection report. The overlay does not replace vendor, environment, database or media. No Composer dependency update is needed, but new classes require autoload refresh.

Configuration is cleared so CLI/FastCGI resolve their own paths; route cache is cleared for changed endpoints and view cache for changed admin actions. **config:cache is prohibited.** Broad cache clearing may remove active locks/rate limits and is not part of this procedure. Stale FastCGI OPcache requires its approved application-specific restart, not a CLI-only reset.

### 5. Expected migration and recovery

Only when this reviewed migration is pending and prerequisites are present do I run:


```bash
set -eu
test -n "$BACKEND"
cd "$BACKEND"
/usr/local/php84/bin/php artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --pretend --force
/usr/local/php84/bin/php artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --force
/usr/local/php84/bin/php artisan migrate:status --no-ansi
```


Review the pretend output before applying. If already recorded, verify schema/indexes and skip reapplication. Missing older migrations require investigation, not import or a blanket migration run.

On error, compare columns, unique request index and migration ledger: MySQL DDL may be partially applied. Repair only the confirmed missing schema/ledger element through a reviewed procedure. A whole-database restore requires proven data damage, explicit acceptance of lost later edits and an isolated restore test. Destructive reset/reseed is not a migration-repair shortcut.

### 6. Storage markers and external services

I verify existing private package/incoming directories and the managed static root, including CLI/FastCGI ownership. I do not replace media/releases or create `current` manually. If initial marker creation is required, I use the existing strict function:


```bash
set -eu
test -n "$INCOMING" && test -n "$STATIC_ROOT"
test -d "$INCOMING" && test ! -L "$INCOMING"
test -d "$STATIC_ROOT" && test ! -L "$STATIC_ROOT"
ensure_marker() {
  if [ -L "$1" ]; then printf 'STOP: symlink marker %s\n' "$1" >&2; return 1; fi
  if [ -e "$1" ]; then
    /usr/local/php84/bin/php -r '
      $path = $argv[1];
      $expected = $argv[2]."\n";
      if (is_link($path) || !is_file($path) || !is_readable($path)) exit(1);
      $actual = @file_get_contents($path, false, null, 0, strlen($expected) + 1);
      if ($actual === false || $actual !== $expected) exit(1);
    ' "$1" "$2" || { printf 'STOP: unreadable, non-regular or unexpected marker %s\n' "$1" >&2; return 1; }
  else
    (set -C; printf '%s\n' "$2" > "$1")
  fi
}
ensure_marker "$INCOMING/.madlen-publisher-incoming" madlen-production-incoming-v1
ensure_marker "$STATIC_ROOT/.madlen-publisher-root" madlen-production-root-v1
```


Comparison requires exact bytes and one trailing LF. Symlinks, unreadable/non-regular files, wrong text, absent LF, CRLF and extra bytes fail. Reads are bounded; noclobber prevents overwriting an unexpected marker. No cmp dependency is introduced.

Production uses `madlen-production-publisher.yml`; preview uses `madlen-external-preview.yml`. I privately configure the existing drivers, connection gates and full source revisions. GitHub variables identify the CMS origin, workflow gates, verified SSH parameters, incoming/backend directories and PHP. Dedicated dispatch/API/SSH credentials remain protected; host keys are independently verified.

Updating the source pin requires a new build. It does not modify an existing package or install PHP. HostingPathResolver resolves context-dependent paths; I do not force CLI paths into shared FastCGI configuration.

### 7. Acceptance and first cutover

Contact and reset can share `mail.mailers.smtp` in `backend/config/mail.php`: contact chooses its configured mailer, reset the Laravel default. I preserve the existing credentials and verify both selectors. Synchronous delivery needs no separate SMTP account, queue worker or Node runtime. Reset links must not be sent through a log mailer.

I verify login, draft save, media and protected preview after a backend update. Password recovery acceptance includes German mail, the HTTPS administrator origin, 60-minute expiry, strong confirmed password, invalid/expired/reused-link rejection and old-session revocation. SMTP/inbox delivery, HTTPS and FastCGI need server checks.

For a genuinely first managed release:

1. I approve DE/EN and legal content and exclude test/unrelated drafts.
2. Enable dispatch in the agreed window and request one CMS publication.
3. Track the existing job through build, delivery and activation; verify intended package contents.
4. Check the prepared static release while the old document root still serves the previous site.
5. Change the public document root only with separate approval, recording the old value and keeping admin separate.
6. Check DE/EN navigation, images, contact, six legal routes and absence of private files/excluded drafts.

An already managed site's normal publication does not change document root. Gates stay enabled for accepted everyday use unless deliberately paused. Creating a kit or merging documentation does not grant publication authority.

### 8. Retry and public rollback

I first inspect the existing job and actual `current`. A missing dispatch/SSH response does not prove failure. Only after confirming the old runner stopped, retry uses the existing job:


```bash
set -eu
test -n "$BACKEND" && test -n "$JOB_ID"
cd "$BACKEND"
/usr/local/php84/bin/php artisan madlen:production:retry "$JOB_ID" --runner-stopped
```


Job/package identity stays unchanged; new build metadata may produce a different output ZIP. Its checksum, source/content identity and runner remain validated.

A handled activation/CMS-transaction failure compensates to the previous pointer and records the failed result. Only the identified latest inactive compensated release can be replaced after fresh staging validation; the failed directory is retained. Active, old, rolled-back or unrecognised releases cannot bypass guards. Ambiguous state requires investigation, not manual checksum edits or directory removal.

For public rollback, stop new dispatch, confirm no jobs are in flight and select a verified prior managed release. The backend connection must remain available to the command:


```bash
set -eu
test -n "$BACKEND" && test -n "$PREVIOUS_RELEASE"
cd "$BACKEND"
/usr/local/php84/bin/php artisan madlen:production:rollback "$PREVIOUS_RELEASE"
```


Rollback reconciles lifecycle state without overwriting newer CMS text/media. I check DE/EN and project state afterwards. A first-ever cutover without a previous managed release uses the recorded old document root for recovery, not a database restore.

### 9. Code rollback and completion

Code rollback uses the same checked kit and recorded backup in a controlled maintenance window:


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT" && test -n "$CODE_BACKUP"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/preflight.php" --tools "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/overlay.php" rollback "$BACKEND" "$CODE_BACKUP"
cd "$BACKEND"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
```


The installer checks backup/current-file integrity, restores replaced files and removes added files except the additive migration source/columns/ledger. Code rollback is not password rollback: new passwords and revoked credentials remain in effect. Returning to the older local-publisher implementation requires leaving Netcup publication disabled.

I retain previous releases, failed results and backups until acceptance. Deployed revision, checksums, migration state, autoload and public/preview/mail results are recorded privately with any limitations. The [verification record](RELEASE_VERIFICATION_RU.md) distinguishes local results from live acceptance; the [CMS guide](../ADMIN_CMS.md) covers everyday editing.

---

<a id="russian"></a>
## Русский

### 1. Объём и ответственность

Я — Gleb Medvedovskyy. Я спроектировал и управляю архитектурой выпуска, конфигурацией и процессом одобрения. Реализация, упаковка и тестирование используют AI-assisted процессы; я принимаю релизы по зафиксированным доказательствам, не заявляя ручное выполнение каждого шага.

Madlen уже работает на Netcup. Это процедура обновления/восстановления, а не разрешение переустановить CMS или повторить первоначальный импорт. Данные серверных аккаунтов, приватные пути и credentials относятся к приватному списку параметров оператора.

Единый [allowlist backend](../scripts/release/backend-files.json) содержит **36 production-файлов**: 35 PHP и один JSON. Относительно базового preview flow добавляются шесть классов: ProductionReleaseManager, RetryProductionPublication, RequestPasswordReset, ResetPassword, AuthenticateAdminSession и AdminResetPassword.

Одна дополнительная миграция `2026_09_12_000006_add_production_operation_identity` добавляет идентичность запроса, проект/операцию, runner и прежнее состояние проекта. Восстановление пароля использует существующую таблицу reset-токенов **без новой миграции**. Существующие preview-классы и миграция target path/request identity сохраняются.

### 2. Идентичность пакета и параметры

Я подготавливаю финальный комплект из чистого checkout точной проверенной ревизии. Каждый payload-файл должен совпадать с Git blob; полная исходная metadata, SHA-256 архива и внутренние SHA-256 должны сходиться после распаковки. Файлы окружения, credentials, база и медиа не являются payload кода.

Существующий `build-kit.mjs` создаёт **кандидат** с отметкой `committed: false`; запуск не удостоверяет автоматически финальный архив после merge. Для приёмки нужны явные проверки source/blob/metadata. Я не переименовываю кандидат рабочей копии в будущий merge.

Базовая ревизия установщика `88e1af1b55e56cdf47085bbba11d3c130831a1bc` задаёт hashes сравнения, а не сегодняшнее установленное состояние. `overlay.php inspect` получает фактические серверные hashes. Неожиданное отличие останавливает установку; Git hashes не заменяют наблюдаемые серверные hashes.

Перед командами я приватно получаю проверенные существующий `BACKEND`, распакованный `KIT`, приватный родительский каталог копий `BACKUPS`, доверенный `COMPOSER_PHAR` и независимо проверенный `COMPOSER_SHA256`. Примеры отказываются работать при незаданных/пустых обязательных параметрах. Свежий hash неизвестного PHAR не является источником доверия.

### 3. Архив и read-only preflight

Я сверяю checksum архива с независимо полученной записью выпуска, проверяю состав и распаковываю только в новый приватный staging-каталог, не поверх приложения. Затем проверяю внутренние checksums и выполняю:


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
cd "$KIT"
command -v sha256sum >/dev/null
sha256sum -c SHA256SUMS
"$PHP_BIN" preflight.php "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
"$PHP_BIN" overlay.php inspect "$BACKEND"
```


Preflight работает только на чтение: без применения кода, миграций или публикации. Он проверяет PHP 8.4, расширения/функции, установленную конфигурацию каталогов и состояние базы запросами только для чтения. Вывод может содержать эксплуатационные пути/настройки; храните его приватно или обезличьте.

Netcup использует `/usr/local/php84/bin/php`, `/usr/bin/mysqldump` и `/usr/bin/mysql`. Node/npm, cmp, stat и Composer в PATH не требуются. Доверенный приватный Composer PHAR запускается PHP 8.4 с отключёнными plugins/scripts. Ошибка отсутствующего инструмента называет именно его.

Перед изменениями я подтверждаю реальные права/лимиты FastCGI, место на диске, сессии/кеш, историю миграций, отсутствие текущих заданий и пригодную цель отката. CLI не доказывает поведение FastCGI. Установленные зависимости должны соответствовать проверенному lockfile; overlay не обновляет vendor.

### 4. Резервная копия, применение и autoload

В согласованное окно я приостанавливаю редактирование и новые runner-запросы, не отключая статический сайт. Сохраняю конфигурацию/ключ приложения, lockfiles зависимостей, прежний document root и состояние autoload. Каталоги копий должны быть приватными с проверенным владельцем, без общедоступной записи.

После preflight и сравнения исходников я запускаю проверенный комплект. Backup-helper сохраняет базу, медиа и приватное окружение; эти файлы остаются вне Git и webroot.


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT" && test -n "$BACKUPS"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/preflight.php" --tools "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
test -d "$BACKUPS" && test ! -L "$BACKUPS"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
CODE_BACKUP="$BACKUPS/code-$STAMP"
DATA_BACKUP="$BACKUPS/data-$STAMP"
test ! -e "$CODE_BACKUP" && test ! -L "$CODE_BACKUP"
test ! -e "$DATA_BACKUP" && test ! -L "$DATA_BACKUP"
cd "$KIT"
(umask 077; set -C; "$PHP_BIN" overlay.php inspect "$BACKEND" > "installed-state-$STAMP.json")
"$PHP_BIN" overlay.php check "$BACKEND" "$CODE_BACKUP" "installed-state-$STAMP.json"
"$PHP_BIN" backup-installed.php "$BACKEND" "$DATA_BACKUP"
(cd "$DATA_BACKUP" && sha256sum -c backup.sha256)
"$PHP_BIN" overlay.php apply "$BACKEND" "$CODE_BACKUP" "installed-state-$STAMP.json"
cd "$BACKEND"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
"$PHP_BIN" -r 'require "vendor/autoload.php"; foreach ([
"App\\Services\\ProductionReleaseManager",
"App\\Console\\Commands\\RetryProductionPublication",
"App\\Filament\\Auth\\RequestPasswordReset",
"App\\Filament\\Auth\\ResetPassword",
"App\\Http\\Middleware\\AuthenticateAdminSession",
"App\\Notifications\\AdminResetPassword",
"App\\Data\\ProjectPreviewSnapshot",
"App\\Services\\ProjectPreviewSnapshotFactory",
"App\\Http\\Controllers\\AdminSessionController"
] as $class) { if (!class_exists($class)) {fwrite(STDERR, "Missing class: ".$class."\n"); exit(1);} } echo "Autoload OK\n";'
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan route:list --path=password-reset
"$PHP_BIN" artisan migrate:status --no-ansi
```


Я фиксирую `CODE_BACKUP`, `DATA_BACKUP` и отчёт inspect. Overlay не заменяет vendor, окружение, базу или медиа. Обновление зависимостей Composer не требуется, но новым классам нужно обновление autoload.

Config очищается, чтобы CLI/FastCGI определяли собственные пути; route cache — для изменённых endpoints, view cache — для действий админки. **config:cache запрещён.** Общая очистка кешей может удалить locks/лимиты частоты и не входит в процедуру. Старый OPcache FastCGI требует согласованного перезапуска именно приложения, а не только CLI-reset.

### 5. Ожидаемая миграция и восстановление

Только если проверенная миграция ожидает применения и предпосылки присутствуют, я выполняю:


```bash
set -eu
test -n "$BACKEND"
cd "$BACKEND"
/usr/local/php84/bin/php artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --pretend --force
/usr/local/php84/bin/php artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --force
/usr/local/php84/bin/php artisan migrate:status --no-ansi
```


Перед применением проверьте вывод pretend. Если миграция уже записана, проверьте схему/индексы и пропустите повтор. Отсутствующие старые миграции требуют расследования, а не импорта или общего запуска миграций.

При ошибке сравните колонки, уникальный индекс запроса и историю миграций: MySQL DDL может примениться частично. Исправляйте только подтверждённо отсутствующий элемент схемы/истории проверенной процедурой. Полное восстановление базы требует доказанного повреждения, явного принятия потери последующих изменений и изолированного теста восстановления. Разрушительный reset/reseed не является сокращённым способом исправить миграцию.

### 6. Маркеры хранилища и внешние сервисы

Я проверяю существующие приватные package/incoming-каталоги и управляемый корень статики, включая владельцев CLI/FastCGI. Не заменяю медиа/релизы и не создаю `current` вручную. Если требуется первоначальное создание маркеров, использую существующую строгую функцию:


```bash
set -eu
test -n "$INCOMING" && test -n "$STATIC_ROOT"
test -d "$INCOMING" && test ! -L "$INCOMING"
test -d "$STATIC_ROOT" && test ! -L "$STATIC_ROOT"
ensure_marker() {
  if [ -L "$1" ]; then printf 'STOP: symlink marker %s\n' "$1" >&2; return 1; fi
  if [ -e "$1" ]; then
    /usr/local/php84/bin/php -r '
      $path = $argv[1];
      $expected = $argv[2]."\n";
      if (is_link($path) || !is_file($path) || !is_readable($path)) exit(1);
      $actual = @file_get_contents($path, false, null, 0, strlen($expected) + 1);
      if ($actual === false || $actual !== $expected) exit(1);
    ' "$1" "$2" || { printf 'STOP: unreadable, non-regular or unexpected marker %s\n' "$1" >&2; return 1; }
  else
    (set -C; printf '%s\n' "$2" > "$1")
  fi
}
ensure_marker "$INCOMING/.madlen-publisher-incoming" madlen-production-incoming-v1
ensure_marker "$STATIC_ROOT/.madlen-publisher-root" madlen-production-root-v1
```


Сравнение требует точных байтов и одного завершающего LF. Symlink, нечитаемые/необычные файлы, неверный текст, отсутствие LF, CRLF и лишние байты вызывают отказ. Чтение ограничено; noclobber не позволяет перезаписать неожиданный маркер. Зависимость от cmp не добавляется.

Production использует `madlen-production-publisher.yml`; preview — `madlen-external-preview.yml`. Существующие drivers, connection gates и полные source SHA я настраиваю приватно. Переменные GitHub задают origin CMS, gates workflow, проверенные SSH-параметры, incoming/backend-каталоги и PHP. Отдельные dispatch/API/SSH credentials защищены; ключи хоста проверяются независимо.

Обновление source pin требует новой сборки. Оно не меняет существующий пакет и не устанавливает PHP. HostingPathResolver определяет пути по контексту; я не записываю принудительно CLI-пути в общую конфигурацию FastCGI.

### 7. Приёмка и первое переключение

Контакт и reset могут совместно использовать `mail.mailers.smtp` из `backend/config/mail.php`: контакт выбирает настроенный mailer, reset — mailer Laravel по умолчанию. Я сохраняю credentials и проверяю оба переключателя. Синхронная доставка не требует отдельного SMTP-аккаунта, queue worker или Node. Ссылки reset нельзя отправлять через log mailer.

После обновления backend я проверяю вход, сохранение черновика, медиа и защищённый preview. Приёмка восстановления включает немецкое письмо, HTTPS origin администратора, срок 60 минут, надёжный подтверждённый пароль, отказ неверной/просроченной/использованной ссылке и отзыв старых сессий. SMTP/входящие, HTTPS и FastCGI проверяются на сервере.

Для действительно первого управляемого выпуска:

1. Я одобряю DE/EN и юридический контент, исключаю тестовые/посторонние черновики.
2. Включаю отправку в согласованное окно и запрашиваю одну публикацию CMS.
3. Отслеживаю существующее задание через сборку, доставку и активацию; проверяю нужный состав пакета.
4. Проверяю подготовленный статический релиз, пока прежний document root обслуживает старый сайт.
5. Меняю публичный document root только с отдельным одобрением, записывая прежнее значение и сохраняя отдельную админку.
6. Проверяю DE/EN-навигацию, изображения, контакт, шесть юридических маршрутов и отсутствие приватных файлов/исключённых черновиков.

Обычная публикация уже управляемого сайта не меняет document root. После приёмки gates остаются включёнными для повседневной работы, если их намеренно не приостановили. Создание комплекта или merge документации не разрешает публикацию.

### 8. Повтор и откат публичного сайта

Сначала я проверяю существующее задание и фактический `current`. Отсутствие dispatch/SSH-ответа не доказывает ошибку. Только после подтверждения остановки прежнего runner retry использует существующее задание:


```bash
set -eu
test -n "$BACKEND" && test -n "$JOB_ID"
cd "$BACKEND"
/usr/local/php84/bin/php artisan madlen:production:retry "$JOB_ID" --runner-stopped
```


Идентичность задания/пакета сохраняется; новые метаданные сборки могут дать другой итоговый ZIP. Его checksum, идентичность исходников/контента и runner продолжают проверяться.

Обработанная ошибка активации/транзакции CMS возвращает предыдущий указатель и записывает ошибочный результат. Заменить после проверки нового staging можно только установленный последний неактивный компенсированный релиз; ошибочный каталог сохраняется. Активные, старые, отменённые откатом и нераспознанные релизы не обходят защиту. Неопределённое состояние требует расследования, а не ручного изменения checksums или удаления каталогов.

Для публичного отката остановите новые отправки, подтвердите отсутствие текущих заданий и выберите проверенный предыдущий управляемый релиз. Backend-подключение должно оставаться доступным команде:


```bash
set -eu
test -n "$BACKEND" && test -n "$PREVIOUS_RELEASE"
cd "$BACKEND"
/usr/local/php84/bin/php artisan madlen:production:rollback "$PREVIOUS_RELEASE"
```


Откат согласует статусы без перезаписи новых текстов/медиа CMS. После него я проверяю DE/EN и состояние проекта. При самом первом переключении без предыдущего управляемого релиза восстановление использует записанный старый document root, а не восстановление базы.

### 9. Откат кода и завершение

Откат кода выполняется тем же проверенным комплектом и записанной копией в контролируемое окно:


```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
test -n "$BACKEND" && test -n "$KIT" && test -n "$CODE_BACKUP"
test -n "$COMPOSER_PHAR" && test -n "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/preflight.php" --tools "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
"$PHP_BIN" "$KIT/overlay.php" rollback "$BACKEND" "$CODE_BACKUP"
cd "$BACKEND"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
```


Установщик проверяет целостность копии/текущих файлов, возвращает заменённые и удаляет добавленные файлы, кроме исходника аддитивной миграции, колонок и записи её применения. Откат кода не является откатом пароля: новые пароли и отзыв старых credentials сохраняются. Возврат к старой реализации локального publisher требует оставить публикацию Netcup отключённой.

До приёмки я сохраняю прежние релизы, ошибочные результаты и резервные копии. Установленная ревизия, checksums, миграции, autoload и результаты публичного сайта/preview/почты фиксируются приватно вместе с ограничениями. [Отчёт о проверках](RELEASE_VERIFICATION_RU.md) разделяет локальные результаты и live-приёмку; [руководство CMS](../ADMIN_CMS.md) описывает обычную работу.
