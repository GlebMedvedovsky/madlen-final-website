# Madlen: единый выпуск на Netcup

Это единственная актуальная инструкция для данного release kit. Старые инструкции для preview-overlay и промежуточного production-overlay из 6 файлов не применяются к этому пакету. Команды ниже предназначены для отдельного согласованного окна установки; подготовка пакета не означает разрешение публикации.

## 1. Состав и границы

Пакет `madlen-release-kit-<UTC>.tar.gz` содержит:

- `payload/backend`: **36 production-файлов** (35 PHP и один JSON) по `manifest.json`; полный список — `scripts/release/backend-files.json`.
- Шесть новых классов относительно установленного preview flow: `App\Services\ProductionReleaseManager`, `App\Console\Commands\RetryProductionPublication`, `App\Filament\Auth\RequestPasswordReset`, `App\Filament\Auth\ResetPassword`, `App\Http\Middleware\AuthenticateAdminSession`, `App\Notifications\AdminResetPassword`.
- Одну новую аддитивную миграцию `2026_09_12_000006_add_production_operation_identity`: `request_id` с unique index, `project_id`, `operation`, `runner_id`, `previous_project_state` в `production_publications`.
- `overlay.php`: проверка исходников, применение, code rollback; `.env`, vendor, БД, uploads и public не заменяет.
- `preflight.php`, `backup-installed.php`, `SHA256SUMS`, эта инструкция; workflow и безопасный распаковщик runner в `source/` для сверки с Git, НЕ для копирования в document root.

Новый Composer-пакет не нужен, но обновление autoload обязательно (в том числе при classmap-authoritative). Уже установленные три класса preview (`ProjectPreviewSnapshot`, `ProjectPreviewSnapshotFactory`, `AdminSessionController`) и миграция `target_path/request_id` должны остаться. Они не заменяются/не запускаются повторно этим комплектом.

`manifest.json.referenceRevision=88e1af1b55e56cdf47085bbba11d3c130831a1bc` — база для сравнения кода, НЕ заявление о фактических checksums Netcup. `before` получены из Git; `inspect` получает реальные хэши сервера. Несовпадение требует просмотра различий, а не подстановки хэшей main вместо серверных. Кандидат создан из незакоммиченной рабочей копии и сам не является commit.

После будущего commit/review/merge исходников нужно заново собрать kit (`node scripts/release/build-kit.mjs`) и привязать обе source revision к проверенному merge SHA. Не указывать `88e1af1`, HEAD старой ветки или SHA несуществующего commit вместо нового кода. Сам kit не изменяет Git.

PR #4 объединён в `88a45f3ef04d4beb41a6a682d9cc09e5b94073b0`. Архив `madlen-release-kit-88a45f3ef04d-20260911T084707Z.tar.gz` и более ранний кандидат `madlen-release-kit-20260911T010215Z.tar.gz` сохраняются без изменений, но **не включают восстановление пароля**. До установки нужен новый review/merge и единый финальный архив строго из нового merge SHA в чистом checkout. Не выдавать текущую незакоммиченную подготовку за будущую ревизию.

К 26 production-файлам PR #4 добавляются ровно десять:

1. `app/Filament/Auth/RequestPasswordReset.php`
2. `app/Filament/Auth/ResetPassword.php`
3. `app/Http/Middleware/AuthenticateAdminSession.php`
4. `app/Notifications/AdminResetPassword.php`
5. `app/Providers/AppServiceProvider.php`
6. `app/Providers/Filament/AdminPanelProvider.php`
7. `bootstrap/app.php`
8. `lang/de/passwords.php`
9. `lang/de/validation.php`
10. `lang/de.json`

Для восстановления используется существующая таблица `password_reset_tokens` из штатной миграции users. **Новой миграции для сброса пароля нет**; единственная новая миграция комплекта остаётся production operation identity из PR #4. Если таблицы reset нет, остановиться и исследовать исходную установку, не запускать повторно create_users.

Для локальной проверки без финального архива: `node scripts/release/build-kit.mjs --unpacked`, затем `php scripts/release/test-kit.php <каталог>` в изолированной среде. После merge финальная сборка должна дополнительно сверять каждый payload с Git blob указанной ревизии; metadata должна явно содержать фактический source SHA, а не только старый reference SHA.

## 2. Как устроен выпуск

Сохранение оставляет черновик черновиком, а сохранение уже опубликованного проекта не меняет текущие статические файлы. Preview берёт валидированный несохранённый снимок и открывается отдельно. Явное «Veröffentlichen» сохраняет текущую форму, включает выбранный проект в production package и запускает внешний workflow. Остальные **черновики** не включаются. Остальные уже опубликованные проекты и общие разделы берутся из сохранённой CMS: их сохранённые правки также попадут в следующую сборку сайта. Не смешивайте неподтверждённые общие правки с окном выпуска.

Каждая операция имеет постоянный UUID. Повтор потерянного запроса из исходного Livewire-снимка возвращает тот же package и не сохраняет форму повторно. Успешно полученный ответ передаёт редактору новый UUID для следующего намеренного нажатия: после публикации можно изменить текст в той же вкладке и выпустить новый снимок без перезагрузки. Другой запрос при незавершённой публикации отклоняется, а не подменяется чужим результатом. Runner получает claim; статусы и SSH-активация проверяют идентичность runner. Потеря ответа dispatch означает «Übergabe unbestätigt», не автоматическую повторную публикацию.

Статусы publish/unpublish/delete меняются только после проверки и переключения `current`. Удаление публичного проекта выполняется через редактор и сначала собирает сайт без него. Групповое удаление публичных/обрабатываемых проектов отклоняется целиком. Восстановление возвращает проект в черновики; используемые, в том числе удалёнными восстанавливаемыми проектами, медиа защищены.

Rollback переключает статический релиз и возвращает связанные статусы publish/unpublish/delete. Более новые тексты и фотографии в CMS не перезаписывает. Уже откатанный либо запоздалый job не может снова активироваться.

Node/npm остаются **только на GitHub runner**. На Netcup используются PHP, ZIP, MySQL-клиент, SSH/SFTP и файловая система. CMS/installer/workflow не меняют DNS или document root.

Production manifest/package записываются в БД как `madlen-production-storage-v1://…` и разрешаются в текущем окружении, поэтому физические пути FastCGI не передаются в SSH. Существующие абсолютные legacy-пути допускаются только при точном соответствии ожидаемому пути данного job. Это не требует изменения уже установленной preview migration.

## 3. Проверка архива и один read-only preflight

Загрузить архив и соседний `.sha256` в частный каталог `/madebymadlen.de/private/updates`, не в public. Сверить SHA-256 с полученным отчётом; затем распаковать только в НОВЫЙ каталог. Не накладывать tar напрямую поверх backend. В примерах `KIT` — абсолютный путь распакованного `madlen-release-kit-<UTC>`.

```bash
set -eu
for tool in sha256sum tar gzip; do command -v "$tool" >/dev/null || { printf 'STOP: missing %s\n' "$tool" >&2; exit 1; }; done
cd /madebymadlen.de/private/updates
ARCHIVE=madlen-release-kit-REPLACE_WITH_ACTUAL_NAME.tar.gz
sha256sum -c "$ARCHIVE.sha256"
# Выведенный/сохранённый hash должен также совпадать с независимым отчётом.
sha256sum "$ARCHIVE"
test ! -e "${ARCHIVE%.tar.gz}"
tar -xzf "$ARCHIVE"
```

Ниже один диагностический блок, запускаемый после распаковки. Он не применяет обновление, не пишет в CMS, не запускает Artisan/Tinker, не выводит значения секретов; запросы к БД — только SELECT/SHOW. Вывод можно сохранить на локальном компьютере. По уже предоставленному preflight: `stat` отсутствует, Composer отсутствует в PATH; PHP — `/usr/local/php84/bin/php`, MySQL dump — `/usr/bin/mysqldump`. Не искать системный Composer и не устанавливать новые инструменты в процессе применения overlay.

В `COMPOSER_PHAR` подставить **уже проверенный приватный** PHAR вне document root, в `COMPOSER_SHA256` — его ранее проверенную SHA-256 из доверенной записи. Точный путь и хэш здесь намеренно не выдуманы. Не считать только что вычисленный хэш неизвестного файла доказательством его доверенности. Проверка требует обычный файл внутри `/madebymadlen.de/private`, без symlink и записи для group/others; запускает его только через PHP84 с отключёнными plugins/scripts. Значения этих двух переменных использовать также в установке и откате.

```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
COMPOSER_PHAR=/madebymadlen.de/private/REPLACE_WITH_VERIFIED_PATH/composer.phar
COMPOSER_SHA256=REPLACE_WITH_PREVIOUSLY_VERIFIED_SHA256
BACKEND=/madebymadlen.de/app/backend
KIT=/madebymadlen.de/private/updates/madlen-release-kit-REPLACE_WITH_ACTUAL_NAME
cd "$KIT"
command -v sha256sum >/dev/null
sha256sum -c SHA256SUMS
"$PHP_BIN" preflight.php "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
"$PHP_BIN" overlay.php inspect "$BACKEND"
id
for item in /madebymadlen.de/private /madebymadlen.de/private/packages/production /madebymadlen.de/private/incoming/production /madebymadlen.de/releases/static; do
  if [ -e "$item" ]; then ls -ld "$item"; else printf 'MISSING: %s\n' "$item"; fi
done
```

Дополнительно только посмотреть в WCP: текущий document root обоих публичных имён, HTTP→HTTPS, обработчик PHP/FastCGI для admin, пользователь файлов и возможность обслуживать symlink. CLI не доказывает эти свойства. Не создавать общедоступный phpinfo/diagnostic endpoint.

Preflight прекращает работу при отсутствии PHP 8.4/нужных расширений, `proc_open`, symlink, `tar`, `gzip`, `sha256sum`, `date`, `mkdir`, `chmod`, `cmp`, `id`, `ls`, `/usr/bin/mysqldump`, `/usr/bin/mysql` или проверенного Composer 2. Права каталогов выводятся через PHP `fileperms`/UID/GID и `ls -ld`, не через `stat`. Перед apply и code rollback тот же gate `--tools` выполняется повторно, до изменения файлов. Это проверка наличия и запуска инструментов, не доказательство прав ALTER/dump, FastCGI или доверенности неизвестного PHAR.

Также сверить лимиты загрузки/времени/памяти именно FastCGI в WCP: значения CLI из preflight могут отличаться. Свободного места должно хватать одновременно на исходные media, private package, incoming ZIP, новый и прежний static releases и backup. Проверить максимальную фотографию из реального рабочего набора после установки; синтетическая загрузка не доказывает квоты и права Netcup.

До установки нужно подтвердить: source checksums совпали; target_path/request_id и запись preview migration есть; production migration `2026_09_09_000003` есть; config cache отсутствует; production jobs не выполняются; PHP ZIP/GD/intl/mbstring/PDO доступны; нужные пути CLI и FastCGI соответствуют одному private-каталогу. Если preflight выявил отличия — остановить установку, передать только обезличенный вывод и code hashes. Секреты в чат не отправлять.

До apply сверить `framework_versions` из read-only preflight: проверены Laravel 12.69.2, Filament 5.8.1, Livewire 4.4.4 из текущего composer.lock. Если установлены другие версии — сначала исследовать расхождение; этот overlay не обновляет vendor и не разрешает произвольный composer update.

## 4. Резервная копия и backend update

Оставить `MADLEN_PRODUCTION_CONNECTED=false`, GitHub gate выключенным. Закрыть редактирование на короткое окно (не закрывая существующий публичный статический сайт). Предупредить администратора: старые сессии без хэша пароля потребуют нового входа; после сброса все прежние сессии будут отозваны. Сохранить текущий document root и private env отдельно с правами 600. Не выводить env через `cat`, `env`, `phpinfo`, `config:show`.

```bash
set -eu
PHP_BIN=/usr/local/php84/bin/php
BACKEND=/madebymadlen.de/app/backend
KIT=/madebymadlen.de/private/updates/madlen-release-kit-REPLACE_WITH_ACTUAL_NAME
: "${COMPOSER_PHAR:?Use the verified private PHAR from preflight}"
: "${COMPOSER_SHA256:?Use its previously verified SHA-256}"
"$PHP_BIN" "$KIT/preflight.php" --tools "$BACKEND" "$COMPOSER_PHAR" "$COMPOSER_SHA256"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUPS=/madebymadlen.de/private/backups
mkdir -p "$BACKUPS"
chmod 700 "$BACKUPS"
cd "$KIT"
"$PHP_BIN" overlay.php inspect "$BACKEND" > installed-state.json
"$PHP_BIN" overlay.php check "$BACKEND" "$BACKUPS/code-$STAMP" installed-state.json
"$PHP_BIN" backup-installed.php "$BACKEND" "$BACKUPS/db-$STAMP"
(cd "$BACKUPS/db-$STAMP" && sha256sum -c backup.sha256)
"$PHP_BIN" overlay.php apply "$BACKEND" "$BACKUPS/code-$STAMP" installed-state.json
cd "$BACKEND"
"$PHP_BIN" "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
"$PHP_BIN" -r 'require "vendor/autoload.php"; foreach (["App\\Services\\ProductionReleaseManager","App\\Console\\Commands\\RetryProductionPublication","App\\Data\\ProjectPreviewSnapshot","App\\Services\\ProjectPreviewSnapshotFactory","App\\Http\\Controllers\\AdminSessionController"] as $c) { if (!class_exists($c)) {fwrite(STDERR,"Missing class: $c\n"); exit(1);} } echo "Autoload OK\n";'
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" -r 'require "vendor/autoload.php"; foreach (["App\\Filament\\Auth\\RequestPasswordReset","App\\Filament\\Auth\\ResetPassword","App\\Http\\Middleware\\AuthenticateAdminSession","App\\Notifications\\AdminResetPassword"] as $c) { if (!class_exists($c)) {fwrite(STDERR,"Missing reset class: $c\n"); exit(1);} } echo "Password reset autoload OK\n";'
"$PHP_BIN" artisan route:list --path=password-reset
"$PHP_BIN" artisan migrate:status --no-ansi
"$PHP_BIN" artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --pretend --force
"$PHP_BIN" artisan migrate --path=database/migrations/2026_09_12_000006_add_production_operation_identity.php --force
"$PHP_BIN" artisan migrate:status --no-ansi
"$PHP_BIN" artisan route:list --path=publisher
```

Backup-helper берёт media root из установленного env/layout (обычно `/madebymadlen.de/private/storage/media`), сохраняет SQL, media и private env. Все три проверяются через `backup.sha256`; helper не использует старый BackupService. Если код принадлежит другому пользователю, исправить точечно по WCP; не использовать chmod 777 или рекурсивный chown всего хостинга. Composer всегда запускать указанным PHP84; `composer install/update` не требуется. `--no-scripts` не запускает посторонние hooks.

`config:clear` обязателен, потому что CLI и FastCGI имеют разные физические префиксы. `route:clear` нужен для claim endpoint и двух штатных password-reset маршрутов, `view:clear` — для изменённых Filament actions/форм. **config:cache запрещён.** Не запускать общий `optimize:clear`/`cache:clear`: они могут удалить действующие locks и ограничения частоты. Если OPcache настроен без проверки времён файлов, перезапустить только PHP приложения средствами WCP в этом окне; CLI opcache_reset не очищает FastCGI.

Мигрировать только указанный файл. Не запускать весь набор «на всякий случай». Если ожидаемая старая migration отсутствует — остановиться и исследовать, а не выполнять import или переустановку. Проверить в admin вход, editor, media и preview до включения production.

## 5. Каталоги и внешний сервис

После проверенных прав создать только следующие каталоги (не трогать существующие releases/media):

```bash
set -eu
mkdir -p /madebymadlen.de/private/packages/production /madebymadlen.de/private/incoming/production /madebymadlen.de/releases/static
chmod 700 /madebymadlen.de/private/packages/production /madebymadlen.de/private/incoming/production
chmod 755 /madebymadlen.de/releases/static
ensure_marker() {
  if [ -L "$1" ]; then printf 'STOP: symlink marker %s\n' "$1" >&2; return 1; fi
  if [ -e "$1" ]; then
    printf '%s\n' "$2" | cmp -s - "$1" || { printf 'STOP: unexpected marker %s\n' "$1" >&2; return 1; }
  else
    (set -C; printf '%s\n' "$2" > "$1")
  fi
}
ensure_marker /madebymadlen.de/private/incoming/production/.madlen-publisher-incoming madlen-production-incoming-v1
ensure_marker /madebymadlen.de/releases/static/.madlen-publisher-root madlen-production-root-v1
```

Если маркеры уже существуют, сначала сравнить их; не переписывать неожиданные значения. SSH-пользователь и пользователь FastCGI должны иметь согласованные права. При разных UID использовать проверенную общую группу и минимальные групповые права вместо 700; доступ к private через HTTP запрещён. `current` создаст первый release; вручную его не создавать.

В private env сохранить существующие секреты и preview-конфигурацию. Добавить/проверить только:

```dotenv
MADLEN_PRODUCTION_PUBLISHER=github-actions
MADLEN_PRODUCTION_CONNECTED=false
MADLEN_PRODUCTION_SOURCE_REVISION=<полный SHA будущего проверенного merge>
MADLEN_GITHUB_REPOSITORY=GlebMedvedovsky/madlen-final-website
MADLEN_GITHUB_WORKFLOW=madlen-production-publisher.yml
MADLEN_GITHUB_REF=main
MADLEN_MYSQLDUMP_BIN=/usr/bin/mysqldump
MADLEN_MYSQL_BIN=/usr/bin/mysql
```

`MADLEN_INSTALL_LAYOUT=netcup-sibling-private-v1` оставить. Не задавать абсолютные CLI-пути `MADLEN_PRODUCTION_*_ROOT` в общую env вслепую: HostingPathResolver рассчитывает их отдельно в CLI/FastCGI. Установленное значение destination `/madebymadlen.de/releases/static` нужно проверить также в FastCGI; при явном override убедиться, что он не ломает layout. Новые `MADLEN_GITHUB_TOKEN` (fine-grained, только Actions write нужного private repo) и случайный отдельный `MADLEN_PUBLISHER_API_TOKEN` вводить приватно. Не заменять ими preview/SMTP токены.

GitHub repository variables:

| Имя | Значение |
| --- | --- |
| MADLEN_CMS_URL | https://admin.madebymadlen.de (подтвердить реальный admin URL) |
| MADLEN_PRODUCTION_WORKFLOW_ENABLED | false до согласованного первого выпуска |
| NETCUP_SSH_HOST / NETCUP_SSH_USER / NETCUP_SSH_PORT | подтверждённые параметры SSH/SFTP |
| NETCUP_MADLEN_PRODUCTION_INCOMING_ROOT | /madebymadlen.de/private/incoming/production |
| NETCUP_MADLEN_BACKEND_ROOT | /madebymadlen.de/app/backend |
| NETCUP_PHP_BIN | /usr/local/php84/bin/php |

Repository secrets: `MADLEN_PUBLISHER_API_TOKEN` (тот же production API token), `NETCUP_PRODUCTION_SSH_PRIVATE_KEY` (выделенный ключ), `NETCUP_SSH_KNOWN_HOSTS` (ключ сервера, fingerprint проверен через доверенный канал Netcup). Не принимать результат ssh-keyscan без сверки fingerprint. Workflow не отключает host-key checking. Доступ к private repository и Actions разрешается только ответственным пользователям.

Workflow должен находиться в default branch main; exact source SHA checkout должен включать новый workflow и `scripts/production/extract-package.py`. Источники сборки не передаются на Netcup через npm. После редактирования env выполнить только `config:clear`, не config:cache.

Контактная форма использует уже установленный Laravel endpoint, отдельный AI/API-сервис не нужен. Существующие рабочие SMTP-значения не заменять. В private env сверить `MADLEN_CONTACT_MAILER=smtp`, `MADLEN_CONTACT_FROM_ADDRESS`, `MADLEN_CONTACT_RECIPIENT`, `MADLEN_CONTACT_ALLOWED_ORIGINS`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`. В этой версии Laravel применяется `MAIL_SCHEME`, а не устаревший `MAIL_ENCRYPTION`: значения схемы/порта брать из настроек конкретного ящика Netcup; TLS/проверку сертификата не отключать. `MADLEN_CONTACT_REQUIRE_ORIGIN=true` сохранить. `MADLEN_CONTACT_ENABLED=true` включать только для согласованной приёмки, если форма ещё выключена. По умолчанию frontend обращается к `https://admin.madebymadlen.de/api/contact`, а резервная ссылка/получатель — `contact@madlenmedvedovskyy.de`: подтвердить, что эти существующие адреса действительно рабочие. Если admin URL отличается, перед сборкой задать `MADLEN_CONTACT_ENDPOINT` в runner; URL должен оставаться HTTPS, без credentials.

## 6. Первый выпуск и document root

### Приёмка восстановления пароля до production gate

Сверить приватно `APP_URL=https://admin.madebymadlen.de`, `APP_LOCALE=de`, `SESSION_SECURE_COOKIE=true`, постоянный рабочий `CACHE_STORE` (не array/null) и сохранение сессий. URL в reset-письме принудительно использует именно этот HTTPS-домен независимо от Host запроса; относительный путь задаёт штатный маршрут Filament. Истечение подписи и broker-токена — 60 минут.

Оба механизма используют **общий `mail.mailers.smtp` из `backend/config/mail.php`**, с единственным набором MAIL_HOST/PORT/SCHEME/USERNAME/PASSWORD. Контакт выбирает его через `MADLEN_CONTACT_MAILER=smtp` (`ContactInquiryController::store()`), reset — через default `MAIL_MAILER=smtp` (`RequestPasswordReset::request()` → `AdminResetPassword` → штатный mail channel Laravel). Отдельного SMTP-аккаунта и дублирования credentials нет. Проверить оба переключателя и существующие MAIL_FROM_ADDRESS/NAME приватно, не печатая значения. Рабочие SMTP-значения не заменять; TLS не отключать. Работа контакта сама по себе не подтверждает правильность default mailer для reset. Log transport для reset запрещён кодом: ссылка не должна попадать в журнал. Отправка синхронная, queue worker/Node на Netcup не нужны. Отдельный серверный тест SMTP, доставки/спама и SPF/DKIM/DMARC обязателен; локальные array/fake проверки его не заменяют.

В согласованной проверке администратор сам вводит свой email и новые пароли; не передавать их или URL/токены в чат/отчёт. Открыть «Passwort vergessen?», получить немецкое письмо, убедиться в домене/HTTPS и 60 минутах, задать пароль (не менее 12 символов и не более 72 UTF-8-байт, верхний/нижний регистр, цифра, спецсимвол, подтверждение). Старый пароль, старый remember-cookie и сессии в других браузерах, включая открытые Livewire/preview/media, должны требовать входа. Новым паролем проверить CMS и preview. Повтор ссылки не меняет пароль. Неизвестный email показывает точно то же уведомление; 2 запроса/минуту на IP и 60 секунд между письмами аккаунта сохраняются. При ошибке SMTP пользователю остаётся то же сообщение с советом повторить через минуту, в server log — только обезличенное предупреждение. Существующий пароль при ошибке не меняется.

У сброса нет автоматического входа или регистрации. Не добавлять /register, обход CSRF или новые публичные диагностические endpoints. Встроенные старые сессии отзываются по хэшу пароля при следующем запросе (вне зависимости от file/database session driver); legacy-сессии без отметки хэша требуют входа уже после установки. Не восстанавливать старые пароли/remember tokens из backup ради code rollback.

### Первый статический выпуск

1. Согласовать содержимое: проект `Test` остаётся Entwurf; проверить DE/EN, обложки, порядок фото. В admin «Veröffentlichungen» действие «Website produktiv veröffentlichen» выпускает только уже опубликованные проекты; это подходящий первый выпуск текущего сайта без Test. «Veröffentlichen» внутри редактора намеренно включает выбранный проект, даже если он был черновиком — не нажимать его на Test.
2. В согласованное окно включить GitHub gate и `MADLEN_PRODUCTION_CONNECTED=true`; `config:clear`. Не запускать одновременно GitHub workflow вручную и CMS-кнопку.
3. Нажать один раз соответствующую кнопку CMS. Записать UUID/sequence. Проверить «Produktiv-Aufträge»: queued → building → uploading → active. Повтор того же нажатия не должен создавать второй job.
4. Проверить из private package `content-manifest.json`: Test отсутствует, утверждённые проекты есть. GitHub собирает реальный Astro, проверяет DE/EN/media, доставляет ZIP; SSH вызывает activation с тем же runner_id. Зафиксировать archive SHA, source SHA, current и target_release.
5. До смены document root проверить `current/index.html`, `current/en/index.html`, sitemap, проекты, загруженные `/media/...`, `.madlen-release.json`. Сохранить текущий docroot из WCP для первого rollback. Старый публичный сайт пока работает по прежнему docroot.
6. Только после успешных проверок изменить document root публичных madebymadlen.de/www на `/madebymadlen.de/releases/static/current` (либо эквивалентный путь интерфейса WCP), сохранив admin docroot `/app/backend/public`. Проверить HTTPS и чтение symlink веб-сервером; не менять DNS без отдельной задачи.
7. Проверить в браузере без admin cookies: DE/EN главная, портфолио/фильтры, Renaissance DE→EN→DE, все новые фото, чат/контактные ссылки, мобильное меню, кнопки Cookies/Chat/BackToTop. В public нет `/admin/preview/`, внутренних файлов, Test или ошибок 404. `/private`, `.env`, backend, manifest служебных пакетов не доступны по HTTP.
8. Проверить защищённый preview отдельно: актуальный `MADLEN_PREVIEW_SOURCE_REVISION` после будущего merge и **новый preview**, не старый архив. Никакая повторная установка CMS не нужна.
9. Контакт: проверить SMTP host/port/TLS/auth по Netcup, фиксированный from и получатель в env, Origin публичного сайта в allowlist. Отправить одну согласованную DE/EN тестовую заявку на реальный адрес и проверить доставку/Reply-To/спам. Локальный array transport доказывает обработку формы, но не доставку SMTP/DNS/SPF/DKIM/DMARC. Не включать SMTP автоматически без этой проверки.

Для последующей повседневной работы CMS production gate должен оставаться включённым после приёмки; его выключение — осознанная блокировка новых публикаций. Во время установки/расследования держать выключенным.

## 7. Ошибки, повтор и откат

При ошибке build/transfer сначала проверить job в CMS, GitHub run и `current`. Потерянный SSH-ответ не доказывает ошибку активации; поздний callback не переводит уже активный job в failed. Не нажимать новые publish-кнопки в разных вкладках для обхода зависшего заказа.

После подтверждения завершения/отмены старого GitHub run можно повторить **тот же** неизменяемый package:

```bash
cd /madebymadlen.de/app/backend
/usr/local/php84/bin/php artisan madlen:production:retry JOB_UUID --runner-stopped
```

Это не новый снимок, не новый project и не новый publication row. Более новый job уже существует — retry старого отклоняется. Новый runner заново собирает тот же исходный package: из-за `builtAt` checksum **результирующего ZIP** может отличаться, что нормально. Checksum исходного package, source revision, content checksum, UUID/sequence и новый runner_id продолжают проверяться.

Если после переключения `current` произошла ошибка CMS-транзакции, компенсатор возвращает прежний указатель и записывает `failedActivation`/`failedPackageChecksum` в `.publisher-state.json`. Только для этого последнего неактивного релиза повтор может заменить каталог: сначала полностью проверяется новый ZIP в staging, затем старый каталог переносится в `.incoming/failed-<sequence>-<uuid>-<random>` внутри static root, и новый занимает его место. Старый каталог сохраняется для расследования, через `current` не обслуживается. Изменённая контрольная сумма старого каталога, неправильный новый checksum, старый runner, активный/более старый/откатанный релиз не обходят защиту. Повтор с исходным ZIP также поддержан.

Если нет записи компенсации (например, аварийно завершился процесс), метаданные не совпадают или указатель неизвестен — остановиться и сверить current, запись CMS, state и оба ZIP. Не удалять каталог, не править checksum/state и не пытаться обойти проверку новым заказом. Это отдельное расследование; полная атомарность БД+файловой системы при аварии машины не обещается. Реальные сетевые таймауты/права проверяются на Netcup.

Для отката уже принятого выпуска дождаться отсутствия in-flight jobs, выключить GitHub gate (connected временно оставить true для команды) и выполнить:

```bash
cd /madebymadlen.de/app/backend
/usr/local/php84/bin/php artisan madlen:production:rollback PREVIOUS_SEQUENCE-UUID
```

Проверить current, DE/EN, status выбранного проекта. После первого переключения docroot, когда старого managed current ещё нет, основной rollback — вернуть **записанный прежний document root** в WCP. Не восстанавливать всю БД ради возврата статического сайта.

Code rollback: выключить production/runner, закрыть изменение CMS, проверить отсутствие активных операций. Из того же kit:

```bash
set -eu
: "${COMPOSER_PHAR:?Use the verified private PHAR from preflight}"
: "${COMPOSER_SHA256:?Use its previously verified SHA-256}"
/usr/local/php84/bin/php "$KIT/preflight.php" --tools /madebymadlen.de/app/backend "$COMPOSER_PHAR" "$COMPOSER_SHA256"
/usr/local/php84/bin/php "$KIT/overlay.php" rollback /madebymadlen.de/app/backend "$BACKUPS/code-$STAMP"
cd /madebymadlen.de/app/backend
/usr/local/php84/bin/php "$COMPOSER_PHAR" dump-autoload --no-dev --optimize --no-scripts --no-plugins
/usr/local/php84/bin/php artisan config:clear
/usr/local/php84/bin/php artisan route:clear
/usr/local/php84/bin/php artisan view:clear
```

Rollback проверяет неизменность обновлённых файлов и backups. Возвращает изменённые файлы, удаляет добавленные классы (шесть) и три добавленных файла переводов, но сохраняет файл аддитивной migration и колонки/ledger. Старый код не использует новые колонки. Publisher после code rollback остаётся выключенным: старые actions с локальным npm нельзя снова включать на Netcup. Не откатывать БД для отмены password-reset кода: новый пароль и отзыв старых credentials должны сохраниться; восстановления пароля в интерфейсе старого кода больше не будет.

Ошибка migration не означает восстановление рабочей БД. Сначала проверить `SHOW COLUMNS`, unique index и запись конкретной migration в `migrations`: MySQL DDL не всегда транзакционен. Если колонки и index созданы полностью, но ledger отсутствует — восстановить только запись migration после подтверждения схемы; если выполнена часть — довести только ожидаемые nullable-поля/index либо удалить только новые пустые поля в отдельном согласованном исправлении. Preview `target_path/request_id` уже установлен: не трогать его. Полный SQL restore нужен только при доказанном повреждении данных и отдельном согласовании потери последующих изменений; сначала испытать backup в отдельной БД. Ни `migrate:fresh`, ни общий `migrate:rollback`, ни повторный import здесь не нужны.

Хранить старые releases, incoming ZIP и backups до завершения приёмки. Установщик их не удаляет и ничего не публикует.
