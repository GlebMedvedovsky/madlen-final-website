# Made by Madlen: перенос Laravel CMS на Netcup

Эта инструкция готовит изолированную установку. Она не меняет текущий public document root `madebymadlen.de/httpdocs`, DNS или работающий сайт до отдельного финального решения.

> Актуальный checkpoint 10.09.2026: CMS уже установлена в `/madebymadlen.de/app`, admin HTTPS/login работают, проверенная резервная копия восстановлена, все семь миграций применены, env-симлинк и FPM-пути проверены. Разделы первичной распаковки, создания env и переноса базы ниже остаются журналом воспроизводимой установки — **на действующем `/app` их не повторять**. Следующее обновление backend выполняется только малым проверяемым overlay из раздела 4.2; база, APP_KEY, пользователи и private media не заменяются.

## 1. Подтверждённое и пока предлагаемое

Подтверждено: Netcup Webhosting 2000 NUE, SSH `hosting208697.ae882.netcup.net:22`, PHP 8.4 `/usr/local/php84/bin/php`, MySQL 8.4.11 на `10.35.232.131:3306`, база `k248794_madlen_cms`, пользователь `k248794_madlen_app`, домен `madebymadlen.de`, HTTPS apex/`www`, CLI `proc_open`, WebP и необходимые PHP-модули. На хостинге отсутствуют `node`, `npm`, `awk`, `stty` и `install`; серверные update-команды ниже на них не рассчитывают. Node-сборку выполняет только будущий Ubuntu runner.

Предлагаемая карта каталогов внутри chrooted SSH:

| Назначение | Путь |
|---|---|
| Код приложения | `/madebymadlen.de/app` |
| Laravel public для будущего admin-домена | `/madebymadlen.de/app/backend/public` |
| Защищённая конфигурация | `/madebymadlen.de/private/config/backend.env` |
| Постоянные оригиналы и производные медиа | `/madebymadlen.de/private/storage/media` |
| Миграционные резервные копии | `/madebymadlen.de/private/backups` |
| Локальное runtime-состояние Laravel | `/madebymadlen.de/private/runtime/releases` |
| Production package/incoming | `/madebymadlen.de/private/packages/production`, `/madebymadlen.de/private/incoming/production` |
| Preview package/incoming/result | `/madebymadlen.de/private/packages/preview`, `/madebymadlen.de/private/incoming/preview`, `/madebymadlen.de/private/previews/results` |
| Версионные статические релизы | `/madebymadlen.de/releases/static` |
| Будущий public document root | `/madebymadlen.de/releases/static/current` |
| Приватные журналы задач | `/madebymadlen.de/private/logs` |

`/` в SSH является корнем jail, а не физическим корнем сервера. До фиксации путей нужно проверить из PHP-FPM будущего admin-домена `__DIR__`, `open_basedir`, запись в private-каталоги и чтение симлинков. CLI-тест симлинка сам по себе этого не доказывает.

## 2. Локальная резервная копия CMS

Выполняется в локальном PowerShell из корня репозитория. Команды затрагивают только Compose-проект `madlen`:

```powershell
docker compose --project-name madlen exec -T app php artisan madlen:backup
docker compose --project-name madlen --profile testing up -d db_test
docker compose --project-name madlen exec -T app php artisan madlen:backup:restore-test <BACKUP_UUID>
pwsh -File scripts/installation/export-local-migration-backup.ps1 -BackupUuid <BACKUP_UUID>
docker compose --project-name madlen --profile testing stop db_test
```

Экспорт помещается рядом с репозиторием в `_private-madlen-migration-backups`, то есть вне Git. Не открывать и не публиковать его содержимое. Вместе переносятся согласованный SQL-снимок, приватные медиа и внутренние checksums. Исходные публичные baseline-медиа входят отдельно в установочный архив.

## 3. Сборка и локальная проверка установочного архива

Из локального PowerShell:

```powershell
docker compose --project-name madlen exec -T --workdir /workspace app bash scripts/installation/build-netcup-package.sh /workspace/installation-artifacts
```

Команда использует `composer.lock`, выполняет `composer install --no-dev`, публикует Filament assets и создаёт:

- `madlen-netcup-install-….tar.gz`;
- внешний файл SHA-256;
- исходный manifest JSON с base commit и checksum локального diff;
- полный файловый SHA-256 manifest.

Затем проверить конкретный архив:

```powershell
docker compose --project-name madlen exec -T --workdir /workspace app bash scripts/installation/verify-netcup-package.sh /workspace/installation-artifacts/<ARCHIVE>.tar.gz
```

Проверка распаковывает архив в случайный `/tmp`, проверяет общий и пофайловый checksums, отсутствие `.env`/дампов/ключей/симлинков/dev dependencies, production `vendor`, Filament assets, PHP platform requirements, Laravel route `/api/contact` и все baseline-медиа.

Архив является **local candidate**, пока соответствующие изменения не закоммичены и не доступны внешнему runner. Поле `runnerSourcePin` намеренно `null`; старый `main` использовать для нового кода нельзя.

## 4. Загрузка без затрагивания работающего сайта

Сначала создать отдельный входящий каталог из Netcup SSH:

```bash
set -euo pipefail
umask 027
mkdir -p /madebymadlen.de/private/incoming/install
chmod 0750 /madebymadlen.de/private/incoming/install
test -d /madebymadlen.de/private/incoming/install
test -w /madebymadlen.de/private/incoming/install
```

Затем через SFTP загрузить в `/madebymadlen.de/private/incoming/install/`:

1. установочный `.tar.gz`, его `.sha256`, `.source.json` и `.files.sha256`;
2. проверенный миграционный backup и его `.sha256`.

В Netcup SSH:

```bash
cd /madebymadlen.de/private/incoming/install
sha256sum -c madlen-netcup-install-….tar.gz.sha256
sha256sum -c madlen-<BACKUP_UUID>.tar.gz.sha256
test ! -e /madebymadlen.de/app
test ! -e /madebymadlen.de/app-candidate
mkdir /madebymadlen.de/app-candidate
chmod 0750 /madebymadlen.de/app-candidate
tar -xzf madlen-netcup-install-….tar.gz -C /madebymadlen.de/app-candidate --strip-components=1
cd /madebymadlen.de/app-candidate
sha256sum -c .madlen-install-files.sha256 >/dev/null
/usr/local/php84/bin/php backend/vendor/composer/platform_check.php
```

Если `/madebymadlen.de/app` уже существует, остановиться: нельзя распаковывать поверх него. Для будущего upgrade нужен отдельный versioned code candidate и отдельная процедура переключения.

### 4.1. Малый compatibility overlay для уже распакованного candidate

Архив `madlen-netcup-install-20260910T021858Z-ef2e3f4fe09c.tar.gz` уже был распакован в `/madebymadlen.de/app-candidate` до обнаружения отсутствующих `stty`/`install` и различия SSH/FPM-путей. Не распаковывать полный архив повторно. Загрузить рядом только новый `madlen-netcup-env-helper-overlay-….tar.gz` и его `.sha256`, затем:

```bash
set -euo pipefail
cd /madebymadlen.de/private/incoming/install
sha256sum -c madlen-netcup-env-helper-overlay-….tar.gz.sha256
overlay_stage="$(mktemp -d /madebymadlen.de/private/incoming/install/env-overlay-XXXXXX)"
tar -xzf madlen-netcup-env-helper-overlay-….tar.gz -C "$overlay_stage"
cd "$overlay_stage"/madlen-env-helper-overlay
sha256sum -c overlay-files.sha256 >/dev/null
bash apply-overlay.sh /madebymadlen.de/app-candidate
```

Apply-скрипт сначала сверяет исходные checksums файлов из полного архива и отказывается работать с симлинками или неожиданно изменённым candidate. Исходные заменяемые файлы сохраняются внутри candidate в `.madlen-overlays/<overlay-id>/originals`; повторный запуск распознаёт уже применённый overlay. При прерывании состояние и checksums остаются в `.madlen-overlays/<overlay-id>/` для диагностики и безопасного продолжения.

После overlay исходный `.madlen-install-files.sha256` намеренно остаётся неизменным и продолжает описывать оригинальный полный архив. Поэтому общая команда `sha256sum -c .madlen-install-files.sha256` ожидаемо покажет различия именно для заменённых файлов. Актуальное состояние подтверждается отдельными `overlay-files.sha256` и marker `APPLIED`; это не повод удалять исходный архив или его manifests.

### 4.2. Малый preview runtime overlay для уже работающего `/app`

Этот шаг относится к следующему отдельному серверному обновлению. Он сохраняет уже применённый `netcup-env-helper-fix-20260910033355-ef2e3f4fe09c-no-awk`, его authoritative Composer classmap, действующий env, базу, пользователей и private media. Overlay содержит 15 существующих PHP-файлов: preview/admin runtime, `AppServiceProvider.php` с обязательными per-IP contact limits и `backend/config/madlen.php` с исправленным build-relative путём `images/start_seite.jpeg`. Новых PHP-классов, миграций, `vendor` и frontend media в нём нет. Поэтому Composer autoload refresh для этого overlay **не требуется**. Полный установочный архив поверх `/app` не распаковывать.

Локальная сборка после итогового чистого commit:

```powershell
docker compose --project-name madlen exec -T --workdir /workspace app bash scripts/installation/build-netcup-preview-overlay.sh `
  /workspace/installation-artifacts `
  /workspace/installation-artifacts/madlen-netcup-install-20260910T021858Z-ef2e3f4fe09c.tar.gz `
  /workspace/installation-artifacts/madlen-netcup-env-helper-overlay-no-awk.tar.gz

docker compose --project-name madlen exec -T --workdir /workspace app bash scripts/installation/verify-netcup-preview-overlay.sh `
  /workspace/installation-artifacts/madlen-netcup-install-20260910T021858Z-ef2e3f4fe09c.tar.gz `
  /workspace/installation-artifacts/madlen-netcup-env-helper-overlay-no-awk.tar.gz `
  /workspace/installation-artifacts/<PREVIEW_OVERLAY>.tar.gz
```

Verifier сначала воспроизводит состояние `original install + compatibility overlay`, затем проверяет все 15 ожидаемых originals, отказ при чужом checksum и symlink, сохранение originals, первый/повторный apply, продолжение из `PREPARED`, неизменность исходного install manifest и PHP syntax. Отдельный `npm run test:build-filters` создаёт синтетические nested/legacy references и подтверждает удаление `images/start_seite.jpeg` без затрагивания соседнего публичного изображения.

После отдельного одобрения загрузить только `<PREVIEW_OVERLAY>.tar.gz` и `.sha256` в приватный install incoming. На Netcup:

```bash
set -euo pipefail
cd /madebymadlen.de/private/incoming/install
sha256sum -c <PREVIEW_OVERLAY>.tar.gz.sha256
overlay_stage="$(mktemp -d /madebymadlen.de/private/incoming/install/preview-overlay-XXXXXX)"
tar -xzf <PREVIEW_OVERLAY>.tar.gz -C "$overlay_stage"
cd "$overlay_stage/madlen-preview-runtime-overlay"
sha256sum -c overlay-files.sha256 >/dev/null
PHP_BIN=/usr/local/php84/bin/php bash apply-overlay.sh /madebymadlen.de/app
overlay_id="$(/usr/local/php84/bin/php -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);echo $m["overlayId"];' metadata.json)"
overlay_state="/madebymadlen.de/app/.madlen-overlays/$overlay_id/state"
test -f "$overlay_state" && test ! -L "$overlay_state" && test "$(<"$overlay_state")" = APPLIED
cd /madebymadlen.de/app/backend
/usr/local/php84/bin/php artisan config:clear
```

Apply отказывается при отсутствии прежнего compatibility marker, неожиданном checksum, target symlink или неизвестном состоянии. Originals сохраняются в `/madebymadlen.de/app/.madlen-overlays/<overlay-id>/originals`. `config:cache`, Composer, migration, import и восстановление базы здесь не выполняются. Если apply остановился в `PREPARED`, не удалять record и не копировать файлы вручную: устранить причину и повторить ту же команду — проверяемое продолжение предусмотрено.

### 4.3. Preview editor flow overlay с новыми классами и миграцией

Этот раздел описывает **будущее отдельное обновление после merge и review** ветки `fix/preview-editor-flow`. На Netcup команды из него в рамках подготовки PR не выполняются. Старый `madlen-netcup-preview-runtime-overlay-…` из раздела 4.2 использовать нельзя: он проверяет другой набор существующих файлов, не добавляет PHP-классы, не обновляет Composer autoload и не применяет миграции.

Новый checksummed backend-overlay должен быть собран из итогового merge commit и содержать ровно следующие десять production-файлов:

1. `backend/app/Data/ProjectPreviewSnapshot.php` — новый класс;
2. `backend/app/Filament/Resources/Projects/Pages/EditProject.php`;
3. `backend/app/Http/Controllers/PreviewController.php`;
4. `backend/app/Models/PreviewBuild.php`;
5. `backend/app/Services/ContentManifestService.php`;
6. `backend/app/Services/ExternalPreviewBuilder.php`;
7. `backend/app/Services/ExternalPreviewPackager.php`;
8. `backend/app/Services/PreviewBuilder.php`;
9. `backend/app/Services/ProjectPreviewSnapshotFactory.php` — новый класс;
10. `backend/database/migrations/2026_09_10_000005_add_target_path_to_preview_builds_table.php` — новая миграция.

Тестовые файлы `backend/tests/Feature/ProjectEditorPreviewTest.php` и `backend/tests/Feature/ExternalPreviewPreparationTest.php` входят в Git/PR, но не в production-overlay. `vendor`, `.env`, база, private media и frontend media в overlay не входят. Перед сборкой нового overlay нужно зафиксировать точный merge SHA и точные исходные checksums семи заменяемых файлов на установленной версии. Apply-скрипт должен отказаться при несовпадении исходных checksums, наличии симлинка вместо целевого файла, уже существующем неожиданном новом файле или неверной базовой ревизии; для семи заменяемых файлов он должен сохранить originals, а два новых класса и миграцию отметить как созданные им файлы для обратимого удаления. Архив, внутренний file manifest и внешний `.sha256` проверяются до распаковки в приложение.

#### Резервное копирование и окно обслуживания

1. Закрыть доступ к изменению CMS на короткое окно обслуживания, не запуская публикацию или preview. Зафиксировать текущий Git/source SHA, список применённых overlay и `migrate:status`.
2. Создать новый application backup штатной командой и записать выведенный UUID:

   ```bash
   cd /madebymadlen.de/app/backend
   /usr/local/php84/bin/php artisan madlen:backup
   ```

3. Проверить статус `ready`, SHA-256 архива и его внутренних `database.sql`, `media.tar.gz`, `checksums.json`; скопировать архив в защищённое хранилище вне document root. Restore-test выполнять только на отдельной базе с суффиксом `_restore_test`, как описано в разделе 2. Никогда не проверять восстановление поверх рабочей базы.
4. Отдельно сохранить текущие семь заменяемых PHP-файлов, `backend/vendor/composer/`, `backend/composer.lock`, приватный `.env` и список/состояние прежних overlay. Не включать секреты в новый архив или лог. Убедиться, что резервная копия создана и проверена **до** изменения `/madebymadlen.de/app`.

#### Порядок установки после отдельного одобрения

В примере `<EDITOR_FLOW_OVERLAY>` — новый архив для итогового merge SHA, а `<NETCUP_COMPOSER_PHAR>` — заранее проверенный Composer PHAR в приватном каталоге вне document root. На хостинге Composer отсутствует в `PATH`, поэтому и Artisan, и Composer всегда запускаются именно PHP 8.4 Netcup:

```bash
set -euo pipefail
PHP_BIN=/usr/local/php84/bin/php
COMPOSER_PHAR=<NETCUP_COMPOSER_PHAR>
APP_ROOT=/madebymadlen.de/app
BACKEND_ROOT="$APP_ROOT/backend"

test -x "$PHP_BIN"
test -f "$COMPOSER_PHAR" && test ! -L "$COMPOSER_PHAR"
"$PHP_BIN" "$COMPOSER_PHAR" --version

cd /madebymadlen.de/private/incoming/install
sha256sum -c <EDITOR_FLOW_OVERLAY>.tar.gz.sha256
overlay_stage="$(mktemp -d /madebymadlen.de/private/incoming/install/editor-flow-overlay-XXXXXX)"
tar -xzf <EDITOR_FLOW_OVERLAY>.tar.gz -C "$overlay_stage"
cd "$overlay_stage"/madlen-preview-editor-flow-overlay
sha256sum -c overlay-files.sha256

# Apply только после успешного backup и проверки expected-base checksums.
PHP_BIN="$PHP_BIN" bash apply-overlay.sh "$APP_ROOT"

cd "$BACKEND_ROOT"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload \
  --no-dev --optimize --classmap-authoritative --no-interaction --no-scripts
"$PHP_BIN" -r 'require "vendor/autoload.php"; foreach (["App\\Data\\ProjectPreviewSnapshot", "App\\Services\\ProjectPreviewSnapshotFactory"] as $class) { if (!class_exists($class)) { fwrite(STDERR, "Autoload fehlt: {$class}\n"); exit(1); } } echo "Autoload OK\n";'

"$PHP_BIN" artisan migrate:status --no-ansi
"$PHP_BIN" artisan migrate --pretend --path=database/migrations/2026_09_10_000005_add_target_path_to_preview_builds_table.php
"$PHP_BIN" artisan migrate --force --path=database/migrations/2026_09_10_000005_add_target_path_to_preview_builds_table.php
"$PHP_BIN" artisan migrate:status --no-ansi

"$PHP_BIN" artisan config:clear
```

Перед командой `migrate` оператор вручную подтверждает, что единственная ожидаемая новая pending-миграция — `2026_09_10_000005_add_target_path_to_preview_builds_table`; при любой другой pending-миграции установка останавливается. `--path` ограничивает применение именно этим файлом. Миграция только добавляет nullable `preview_builds.target_path`; она не изменяет Projects, пользователей или media.

`dump-autoload` выполняется с `--no-scripts`, потому что зависимости и package discovery не меняются, а требуется только обновить authoritative classmap для двух новых классов. После него обязательна отдельная проверка `class_exists()` выше. Не запускать Composer напрямую (`composer ...`) или через системный `php`: shebang/default CLI выберет неверную версию PHP.

На установленном layout **не выполнять** `artisan config:cache`: `HostingPathResolver` намеренно вычисляет разные абсолютные пути для CLI и FastCGI, а CLI-generated config cache зафиксирует неверный FPM-путь. Выполняется только `/usr/local/php84/bin/php artisan config:clear`, чтобы FPM снова вычислил пути в своём контексте. `cache:clear`, `route:clear`, `route:cache`, `view:clear` и `optimize:*` для этого обновления не нужны: application cache, routes и Blade-файлы не меняются. Если после проверки FPM всё ещё исполняет старый opcode, использовать отдельный штатный restart PHP 8.4 в WCP; не подменять его Artisan cache-командами.

После команд проверить в таком порядке: `artisan about`, admin login, существующее сохранение черновика, открытие preview в новой вкладке, несохранённый текст в preview при неизменной записи БД, ожидание с переходом на выбранный проект, Renaissance DE → EN → DE с одним preview-префиксом, popup-blocked fallback link, двойное нажатие и owner/expiry isolation. Не запускать реальную публикацию. Только после успешного smoke-test снять окно обслуживания. Обновление `MADLEN_PREVIEW_SOURCE_REVISION` до итогового merge SHA потребуется отдельно для external runner, но оно **не заменяет** backend-overlay, Composer autoload refresh и миграцию.

#### Восстановление при ошибке

- Если apply не начался или остановился до изменения файлов, оставить действующий `/app` без изменений, сохранить диагностику и удалить только созданный staging-каталог после проверки.
- Если файлы изменены, но миграция ещё не применена, восстановить семь originals из проверенного overlay-record, удалить только три созданных overlay-файла, восстановить сохранённый `vendor/composer/`, затем снова выполнить Composer `dump-autoload` через `/usr/local/php84/bin/php` и `artisan config:clear`. Не удалять `.env`, storage или media.
- Если миграция упала, не продолжать и не запускать общий `migrate:rollback`, `migrate:fresh`, `db:wipe`, import или seed. Сохранить ошибку и `migrate:status`; восстановить рабочую БД из проверенного предустановочного backup по отдельной утверждённой recovery-процедуре, затем восстановить код/autoload как в предыдущем пункте.
- Если миграция завершилась, а smoke-test кода не прошёл, сначала вернуть предыдущие файлы и autoload. Новая nullable-колонка обратно совместима со старым кодом и может временно остаться; для точного возврата pre-update состояния восстановить проверенный backup в том же окне обслуживания. Не откатывать весь migration batch.
- После любого восстановления проверить admin login, неизменность CMS-данных/media, `migrate:status`, preview isolation и логи. Не возобновлять доступ, пока эти проверки не завершены.

## 5. Приватные каталоги и marker-файлы

Netcup SSH, до импорта базы:

```bash
set -euo pipefail
umask 027
mkdir -p \
  /madebymadlen.de/private/config \
  /madebymadlen.de/private/storage/media \
  /madebymadlen.de/private/backups \
  /madebymadlen.de/private/runtime/releases \
  /madebymadlen.de/private/packages/production \
  /madebymadlen.de/private/packages/preview \
  /madebymadlen.de/private/incoming/production \
  /madebymadlen.de/private/incoming/preview \
  /madebymadlen.de/private/previews/results \
  /madebymadlen.de/private/logs \
  /madebymadlen.de/releases/static

chmod 0750 \
  /madebymadlen.de/private/config \
  /madebymadlen.de/private/storage \
  /madebymadlen.de/private/storage/media \
  /madebymadlen.de/private/backups \
  /madebymadlen.de/private/runtime \
  /madebymadlen.de/private/runtime/releases \
  /madebymadlen.de/private/packages \
  /madebymadlen.de/private/packages/production \
  /madebymadlen.de/private/packages/preview \
  /madebymadlen.de/private/incoming/production \
  /madebymadlen.de/private/incoming/preview \
  /madebymadlen.de/private/previews \
  /madebymadlen.de/private/previews/results \
  /madebymadlen.de/private/logs \
  /madebymadlen.de/releases \
  /madebymadlen.de/releases/static

ensure_marker() {
  marker_path="$1"
  marker_value="$2"
  if test -e "$marker_path" || test -L "$marker_path"; then
    test -f "$marker_path" && test ! -L "$marker_path" && test "$(<"$marker_path")" = "$marker_value" || {
      printf 'REFUSAL: unexpected marker state: %s\n' "$marker_path" >&2
      return 1
    }
    return 0
  fi
  (set -C; umask 027; printf '%s\n' "$marker_value" > "$marker_path") || {
    printf 'REFUSAL: marker appeared concurrently: %s\n' "$marker_path" >&2
    return 1
  }
  chmod 0640 "$marker_path"
}

ensure_marker /madebymadlen.de/private/incoming/production/.madlen-publisher-incoming madlen-production-incoming-v1
ensure_marker /madebymadlen.de/releases/static/.madlen-publisher-root madlen-production-root-v1
ensure_marker /madebymadlen.de/private/packages/preview/.madlen-preview-packages madlen-preview-packages-v1
ensure_marker /madebymadlen.de/private/incoming/preview/.madlen-preview-incoming madlen-preview-incoming-v1
ensure_marker /madebymadlen.de/private/previews/results/.madlen-preview-results madlen-preview-results-v1
unset -f ensure_marker
```

Не использовать `chmod 777`. До продолжения проверить, что PHP-FPM admin-домена может писать только в необходимые Madlen-каталоги, а web server не может отдавать `private`, `.env`, backup, package или repository root.

## 6. Фиксация code path и приватная конфигурация

После успешного overlay и до создания admin document root один раз зафиксировать окончательное имя каталога кода. Существующий public document root это не меняет:

```bash
set -euo pipefail
test ! -e /madebymadlen.de/app
test -d /madebymadlen.de/app-candidate/backend
mv /madebymadlen.de/app-candidate /madebymadlen.de/app
cd /madebymadlen.de/app
```

Скопировать существующий локальный `APP_KEY` через защищённый канал/буфер непосредственно в скрытый prompt. Не публиковать его в чате. Запускать helper только в интерактивной SSH-сессии, не через pipe, redirect или cron:

```bash
/usr/local/php84/bin/php scripts/installation/write-private-env.php /madebymadlen.de/private/config/backend.env
ln -s ../../private/config/backend.env backend/.env
test -L backend/.env
test -r backend/.env
test "$(stat -c '%a' /madebymadlen.de/private/config/backend.env)" = 600
```

Helper использует встроенный Bash `IFS= read -r -s`, а не отсутствующий `stty`; видимого fallback нет. Он сохраняет пробелы, `$`, кавычки, backslash и Unicode, строго проверяет Laravel `APP_KEY`, создаёт временный файл сразу с `umask 077`/mode `0600` и публикует через атомарное no-overwrite hard link. Скрипт отказывается перезаписывать обычный файл или симлинк.

В env сохраняется только маркер раскладки `MADLEN_INSTALL_LAYOUT=netcup-sibling-private-v1`, без угаданных абсолютных `/madebymadlen.de/...` путей. Laravel вычисляет repository/private/release/package paths относительно фактического `base_path()` независимо в CLI и PHP-FPM. Это позволяет одному env работать и с SSH jail path, и с физическим префиксом FPM.

Helper сохраняет подтверждённые настройки базы `10.35.232.131:3306`, `k248794_madlen_cms`, `k248794_madlen_app`, а также SMTP `smtps`, `mxe884.netcup.net:465`, From `hi@madebymadlen.de`, To `contact@madlenmedvedovskyy.de`. Contact и оба runner остаются выключенными.

### 6.1. Одноразовая проверка PHP-FPM-путей

В WCP сначала создать password-protected `admin.madebymadlen.de` с HTTPS и document root строго `/madebymadlen.de/app/backend/public`. До снятия password protection скопировать probe под случайным именем, открыть этот точный HTTPS URL один раз и сразу удалить:

```bash
set -euo pipefail
probe_name=".madlen-fpm-path-$(/usr/local/php84/bin/php -r 'echo bin2hex(random_bytes(12));').php"
cp /madebymadlen.de/app/scripts/installation/netcup-fpm-path-probe.php "/madebymadlen.de/app/backend/public/$probe_name"
chmod 0640 "/madebymadlen.de/app/backend/public/$probe_name"
printf 'PROBE_URL=https://admin.madebymadlen.de/%s\n' "$probe_name"
```

Ожидается JSON с `ok: true`, FPM SAPI, реальным `backend_base_path`, совпадающими repository/private paths, читаемым env/baseline и `private_runtime_write_delete: true`. Сравнить путь с CLI-командой `/usr/local/php84/bin/php -r 'echo realpath("/madebymadlen.de/app/backend"), PHP_EOL;'`. Независимо от результата удалить только выведенный файл и проверить удаление:

```bash
rm -- "/madebymadlen.de/app/backend/public/$probe_name"
test ! -e "/madebymadlen.de/app/backend/public/$probe_name"
unset probe_name
```

До этого шага probe не копировать в public. Если `ok` не `true`, не импортировать базу и не включать admin/contact.

## 7. Первичный перенос базы и медиа

Эта часть допустима только для подтверждённо пустой целевой базы. Пароль вводится интерактивно:

```bash
mysql -h 10.35.232.131 -P 3306 -u k248794_madlen_app -p k248794_madlen_cms \
  -e "SELECT COUNT(*) AS existing_tables FROM information_schema.tables WHERE table_schema=DATABASE();"
```

Если результат не `0`, остановиться и сначала выяснить происхождение данных. Не импортировать baseline и не заменять существующую базу.

Для пустой базы:

```bash
backup_stage="$(mktemp -d /madebymadlen.de/private/backups/restore-XXXXXX)"
tar -xzf /madebymadlen.de/private/incoming/install/madlen-<BACKUP_UUID>.tar.gz -C "$backup_stage"
cd "$backup_stage"
/usr/local/php84/bin/php -r '$c=json_decode(file_get_contents("checksums.json"),true,512,JSON_THROW_ON_ERROR); foreach($c as $f=>$h){if(!hash_equals($h,hash_file("sha256",$f))){fwrite(STDERR,"CHECKSUM FAILED\n");exit(1);}} echo "INNER CHECKSUMS: OK\n";'
mysql -h 10.35.232.131 -P 3306 -u k248794_madlen_app -p k248794_madlen_cms < database.sql
test -z "$(find /madebymadlen.de/private/storage/media -mindepth 1 -print -quit)"
tar -xzf media.tar.gz -C /madebymadlen.de/private/storage
```

После проверки перенести исходный backup в `/madebymadlen.de/private/backups/` и удалить только созданный `restore-*` каталог. На будущих обновлениях эти команды импорта/распаковки **не повторяются**: база и media сохраняются как persistent state.

## 8. Проверка backend и миграции

Продолжать только после успешного FPM probe и проверенного backup. Netcup SSH:

```bash
cd /madebymadlen.de/app/backend
/usr/local/php84/bin/php artisan about
/usr/local/php84/bin/php artisan route:list --path=api/contact --except-vendor
/usr/local/php84/bin/php artisan migrate:status
/usr/local/php84/bin/php artisan migrate --force
/usr/local/php84/bin/php artisan config:clear
/usr/local/php84/bin/php artisan route:cache
```

Первичная миграция завершена: все семь миграций, включая `2026_09_09_000003` и `2026_09_09_000004`, уже применены. На действующем `/app` сейчас не повторять перенос базы, baseline import или восстановление backup. Перед любой будущей новой миграцией обязателен свежий проверенный backup; запрещены `migrate:fresh`, `db:wipe`, reseed и `madlen:import` поверх перенесённой CMS.

Во время первого rehearsal конфигурацию оставить **uncached** и в CLI, и в FPM. `config:cache` сейчас нельзя выполнять: кеш зафиксирует абсолютные пути того контекста, в котором была запущена команда, до доказательства совпадения CLI/FPM. После миграций проверить login, authenticated media, DE/EN draft preview isolation, secure/HttpOnly cookie, HTTPS redirect и отсутствие web-доступа к `.env`, `/private`, backup и repository root.

## 9. Scheduler

После фиксации пути backend создать в WCP одну задачу типа **Befehl ausführen**, Cron-Stil `* * * * *`:

```bash
cd /madebymadlen.de/app/backend && /usr/local/php84/bin/php artisan schedule:run >> /madebymadlen.de/private/logs/scheduler.log 2>&1
```

Проверить как минимум несколько автоматических запусков и регистрацию задач. Кнопка “Jetzt ausführen” с `php -v` не является проверкой recurring lifecycle.

## 10. Contact и runner: отдельные последующие включения

Contact endpoint: `https://admin.madebymadlen.de/api/contact`. Фронтенд посылает только form-поля, без cookies. CORS разрешён лишь для apex, `www` и admin preview origin. From/To задаёт сервер; проверенный visitor email становится Reply-To. При SMTP-ошибке посетитель видит локализованную ошибку и прямую email-ссылку, а не ложный успех. Minute/hour buckets вычисляются только по IP и хранятся в persistent Laravel cache (`CACHE_STORE=database` на Netcup), поэтому смена visitor email не сбрасывает ограничения. Значение `array` допустимо только в изолированных тестах.

После готовности admin HTTPS и SMTP на непубличном rehearsal изменить только `MADLEN_CONTACT_ENABLED=true`, выполнить `config:clear`, отправить по одному DE/EN тесту и проверить реальное получение и Reply-To. До отдельной доказанной проверки CLI/FPM путей `config:cache` не выполнять. Принятие письма SMTP не гарантирует inbox delivery; проверить входящие и spam.

Runner-подключения не включать одновременно с contact. Подготовленный preview workflow находится в `.github/workflows/madlen-external-preview.yml`, имеет только ручной `workflow_dispatch` и дополнительно закрыт переменной-gate. GitHub регистрирует ручной workflow только после появления файла в default branch `main`: сначала push feature-ветки, review/PR и merge, затем credentials и rehearsal. SHA текущего feature-коммита нельзя заранее подменять старым `ef2e3f4…`; после commit/merge взять полный совместимый 40-символьный SHA командой `git rev-parse <reviewed-commit>` и только затем записать его в Netcup env как `MADLEN_PREVIEW_SOURCE_REVISION`. Workflow не создаёт SSH private key/known_hosts до окончания `npm ci`, проверенного DE/EN build, создания результата и повторной проверки expiry. Поэтому gate-, npm- или build-failure завершаются до появления SSH-файлов; после SSH-этапов каталог ключа удаляет обязательный `if: always()` cleanup. Это сокращает время доступности ключа, но не является границей от полностью скомпрометированного runner — для такой изоляции нужен отдельный job.

Точные GitHub repository **secrets** для preview:

- `MADLEN_PREVIEW_API_TOKEN` — отдельная случайная строка, равная серверному `MADLEN_PREVIEW_API_TOKEN`;
- `NETCUP_PREVIEW_SSH_PRIVATE_KEY` — отдельный приватный ключ runner, не пароль WCP и не основной пользовательский ключ;
- `NETCUP_SSH_KNOWN_HOSTS` — независимо проверенная полная строка `known_hosts` для `hosting208697.ae882.netcup.net:22`; результат непроверенного `ssh-keyscan` сам по себе не является доверием.

Точные GitHub repository **variables**:

- `MADLEN_EXTERNAL_PREVIEW_WORKFLOW_ENABLED=false` — оставить выключенным до всех проверок ниже, для согласованного rehearsal временно установить строго `true`;
- `MADLEN_CMS_URL=https://admin.madebymadlen.de`;
- `NETCUP_SSH_HOST=hosting208697.ae882.netcup.net`;
- `NETCUP_SSH_USER=hosting208697`;
- `NETCUP_SSH_PORT=22`;
- `NETCUP_MADLEN_PREVIEW_INCOMING_ROOT=/madebymadlen.de/private/incoming/preview`.

Точные Netcup env-параметры (секретные значения не копировать в Git):

```dotenv
MADLEN_PREVIEW_TTL=120
MADLEN_PREVIEW_EXECUTION=local
MADLEN_EXTERNAL_PREVIEW_CONNECTED=false
MADLEN_EXTERNAL_PREVIEW_DRIVER=unconfigured
MADLEN_PREVIEW_SOURCE_REVISION=<REVIEWED_FULL_40_CHARACTER_SHA>
MADLEN_PREVIEW_API_TOKEN=<SEPARATE_RANDOM_RUNNER_TOKEN>
MADLEN_PREVIEW_GITHUB_REPOSITORY=GlebMedvedovsky/madlen-final-website
MADLEN_PREVIEW_GITHUB_WORKFLOW=madlen-external-preview.yml
MADLEN_PREVIEW_GITHUB_REF=main
MADLEN_PREVIEW_GITHUB_TOKEN=<FINE_GRAINED_DISPATCH_TOKEN>
```

`MADLEN_PREVIEW_GITHUB_TOKEN` — fine-grained PAT только для репозитория `GlebMedvedovsky/madlen-final-website` с repository permission **Actions: Read and write**, необходимым endpoint `POST /actions/workflows/{workflow}/dispatches`; иных repository/org прав ему не добавлять. Сам workflow имеет только `contents: read`. Секрет callback и dispatch PAT — разные значения.

Порядок отдельного rehearsal:

1. Убедиться, что три preview-каталога взаимно разделены, приватны, содержат точные marker-файлы и PHP-FPM может писать package/result, а SSH-key — только incoming. Проверить вход именно ключом в `BatchMode`, не удаляя password login до подтверждения.
2. Пока временная HTTP Basic Auth закрывает весь admin root, Bearer runner API несовместим с ней: оба механизма используют единственный заголовок `Authorization`. Сначала через тесты/браузер подтвердить CMS login, 404 без сессии для preview HTML/media, wrong-owner отказ, Bearer-auth и изоляцию `/api/preview-runner/v1/*`; затем на отдельном серверном этапе снять только временную Basic Auth. Не объединять Basic и Bearer и не создавать публичный probe.
3. Отредактировать приватный `backend.env` атомарно, оставив `MADLEN_PREVIEW_EXECUTION=local` и connection false; выполнить `/usr/local/php84/bin/php artisan config:clear`. `config:cache` не выполнять из-за разных CLI/FPM-префиксов. Проверить admin и статус «Vorschau ist noch nicht eingerichtet» при отсутствующем npm.
4. После появления workflow в `main`, настройки SHA, secrets/vars и проверки API переключить env на `MADLEN_PREVIEW_EXECUTION=external`, `MADLEN_EXTERNAL_PREVIEW_DRIVER=github-actions`, `MADLEN_EXTERNAL_PREVIEW_CONNECTED=true`, снова выполнить `config:clear`; gate включить последним.
5. Создать один непубличный draft preview. Проверить immutable checksum, DE/EN и media только с CMS-сессией, 401 runner API без Bearer, 404 preview без сессии/для другого пользователя, expiry и повторный callback с тем же checksum. Убедиться, что public release/current не изменился.
6. Запустить `/usr/local/php84/bin/php artisan madlen:previews:cleanup`, проверить удаление только истёкшего package/result/build и сохранность другого preview. После rehearsal вернуть gate `false`; при дефекте также вернуть env в local/unconfigured/false и выполнить `config:clear`.

GitHub Actions не публикует draft как artifact, Pages или cache и не печатает сырой build log. Архив передаётся под уникальным временным именем, существующий результат не перезаписывается, а callback повторяем с тем же checksum. Реальную работоспособность Netcup preview можно утверждать только после этого end-to-end rehearsal.

Для external preview поля `manifest_path`, `package_path` и `build_path` в новых `preview_builds` содержат строгие логические locators `madlen-preview-storage-v1://…`, а не SSH/FPM-абсолютные пути. Каждый web/CLI-контекст разрешает locator только в ожидаемый marker-protected root и только для конкретных preview id/token. Это устраняет различие `/madebymadlen.de/app/backend` и физического FPM-префикса без ослабления path checks. Существующие local preview-записи продолжают использовать локальные абсолютные пути; миграция БД для изменения формата не нужна, поскольку внешний runner на сервере ещё не включался.

Только после успешного rehearsal можно отдельно обсуждать изменение public document root с `madebymadlen.de/httpdocs` на `/madebymadlen.de/releases/static/current`.

## Инвариант обновлений

Git хранит код. CMS database хранит редакционные DE/EN данные и drafts. `/madebymadlen.de/private/storage` хранит persistent media. Обновление кода не импортирует старый baseline, не заменяет базу локальным dump, не удаляет media, не публикует посторонние drafts и не сбрасывает env. Откат статического релиза меняет только `current`, но не откатывает базу или private media.
