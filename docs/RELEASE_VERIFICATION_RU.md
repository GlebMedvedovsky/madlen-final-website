# Madlen — проверка кандидата выпуска

## Исправления review PR #4

Ветка `release/netcup-cms-candidate`, база этого исправления `5591c48135dae76dd9aaa70076238fba38716c3f`. Netcup, реальные CMS-данные, секреты и merge не затрагиваются.

- `EditProject`: ответ принятой операции выдаёт новый UUID для следующего нажатия. Старый Livewire-снимок при потерянном ответе сохраняет исходный UUID: повтор не сохраняет форму повторно и не создаёт ещё один заказ. Проверены очередной и уже активный старые запросы, новый текст без remount/Save, неизменность первого manifest.
- `StaticReleaseActivator`: при компенсации ошибки CMS сохраняется checksum неудачного релиза. Повтор с новым runner и новым ZIP разрешает заменить только этот последний неактивный каталог после полной проверки нового staging; старый каталог сохраняется в `.incoming/failed-…`. Checksum/source/content identity, active/старые/откатанные релизы, high-water sequence и runner-проверки не отключены.
- Runbook/preflight: нет вызова `stat` или поиска Composer в PATH. Проверенный private `composer.phar` запускается PHP84, проверяется по ранее доверенной SHA-256 до выполнения. Gate инструментов повторяется до apply/rollback; `config:cache` по-прежнему запрещён.

Реально повторено после изменений:

- Полный `php artisan test`: **43 passed, 5818 assertions** (434.03 s), изолированная SQLite и временные файлы. В том числе CMS, preview/editor recovery, contact, backup и publication.
- Целевые `ReleaseAcceptanceTest|ProductionPublisherPreparationTest`: **14 passed, 260 assertions**. Новая регрессия ошибки CMS исполняет **две настоящие локальные Astro-сборки** одного immutable package: разные `builtAt` и SHA ZIP, `production:retry`, новый claim/runner, отказ старому runner/ложной checksum/изменённому checksum старого каталога, восстановление, DE/EN и rollback.
- **8 Node-тестов** routing/preview request recovery; проверки порядка production workflow и фильтра непубличных артефактов — PASS.
- Настоящий Symfony YAML parser + `bash -n` **17 workflow-блоков**; Python ZIP traversal/private/symlink регрессии — PASS.
- `php scripts/release/test-preflight.php`: успешный gate в PATH **без stat и composer**, шесть отказов (checksum, отсутствующий PHAR/инструменты, public PHAR, symlink, unsafe permissions), **7 bash-блоков runbook**. PHAR синтетический; это не проверка установленного Netcup Composer или его доверенности.
- Настоящий Chromium (`qa-publish-review-browser.mjs`): в одной вкладке две публикации кнопкой, два разных UUID/заказа, две реальные локальные сборки/активации, новый текст в DE и переход на EN того же проекта. Третий серверный ответ после создания заказа принудительно оборван; из той же браузерной сессии повторён **точный исходный HTTP body** на фактический hashed Livewire endpoint: HTTP 200, прежний request_id, один заказ, **0 перезагрузок**, поля сохранены. Это replay через browser fetch, не заявление о добавлении автоматического UI-retry. Evidence: `installation-artifacts/release-qa/review-publish-browser.json`, `review-publish-same-tab.png`; внешняя сеть закрыта, dispatch fake.

Состав остаётся **26 production-файлов**, новых runtime-классов или миграций сверх кандидата review не добавляет. Старый архив `madlen-release-kit-20260911T010215Z.tar.gz` сохранён побайтно (SHA-256 `a88f8e5d64d7f160361041f3d4f3d724dc38c7e27b766351fbde69a0f27b50ed`), но **не содержит этих исправлений**. После merge нужен новый kit из итоговой ревизии. Исходные server checksums, FastCGI/права, реальный dispatch/SSH/SMTP остаются серверной приёмкой; локальные fake HTTP и synthetic DB failure её не заменяют.

## Исторический отчёт первоначального кандидата (до review)

Проверка выполнена локально 11.09.2026. Код подготовлен к commit/review, но **не объявляется установленным или проверенным на Netcup**. Ветка `fix/preview-editor-flow`, исходный HEAD `f3133d6175189e13bc8ae34b60cf2f984519ad15`. Серверная база сравнения — `88e1af1b55e56cdf47085bbba11d3c130831a1bc`. Commit, push, merge, реальная публикация и изменения Netcup не выполнялись.

## Что исправлено

- Все действия публикации CMS используют внешний ProductionPublisher. Старые actions локального npm удалены; сам legacy-сервис оставлен для совместимости существующих тестов, не как серверный путь публикации.
- Явная публикация сохраняет форму и создаёт неизменяемый package выбранного проекта. Посторонние черновики не включаются. Предпросмотр несохранённой формы не сохраняет основной Project.
- Постоянный request_id, исключение параллельных заказов, claim конкретного runner, безопасный повтор того же package после проверки остановки прежнего runner, отказ запоздалым callback/активациям.
- Статусы публикации/снятия/удаления согласуются с успешным переключением current. При ошибке транзакции возвращается прежний current. Откат возвращает связанные lifecycle-статусы, не перезаписывая более новые тексты/фотографии.
- Групповое удаление не удаляет часть выбранных записей перед отказом. Публичные проекты удаляются через внешний выпуск. Восстановление не публикует автоматически; используемые медиа, включая ссылки из восстанавливаемых удалённых проектов, защищены.
- Сохранённые пути production package не зависят от различающихся физических префиксов CLI/FastCGI.
- BackupService использует настраиваемый `/usr/bin/mysqldump`; добавлены проверенные backup/install/rollback инструменты.
- Во frontend переведены оставшиеся немецкие тексты Cookies на EN. Устранён мобильный выход длинных названий проектов за экран. HomepageStory, линия, фото, видео, Hero, Header и ChatBot не менялись.

## Реально выполненные проверки

| Проверка | Результат и границы |
| --- | --- |
| Полный Laravel/PHP набор | **41 тест, 5740 assertions**, успешно; PHP 8.4.25, SQLite :memory:, временные файлы. |
| Обычный Astro build | Успешно, **49 страниц DE/EN**, Node 22.23.2; отдельный output, без preview base и CMS manifest. |
| Preview/production package → Astro | Реальные локальные сборки из неизменяемых пакетов; результат проверен и активирован в изолированном static root. |
| Runtime routing/recovery | 8 Node-тестов: base один раз, Renaissance DE/EN, потеря ответа/500/419/поздний ответ. |
| Финальный preview bundle | Исполнен клиентский ChatBot после scopeUrls: DE/EN project/contact остаются в том же preview. |
| Workflow | Настоящий Symfony YAML parser, `bash -n` для **17** shell-блоков, policy-проверка порядка/очистки. GitHub runner не запускался. |
| Защита артефактов | Реальный ZIP и отказы для traversal, absolute/backslash, private-файлов, symlink; build filter сохраняет публичные соседние файлы. |
| Миграция | Отдельная MySQL 8.4: все пять новых полей, unique request_id, down/up. Не рабочая CMS-БД. |
| Backup/restore | Реальные mysqldump/mysql, SQL UTF-8 и media sentinel round trip в отдельную БД. Локальный mysqldump — MariaDB client 10.11.18; Netcup client ещё нужно проверить. |
| Установщик | Архив распакован в временный каталог; SHA архива/всех файлов; отказ при неизвестном исходнике, stale report и symlink; apply, повтор, rollback, отказ при последующих правках, повторная установка после rollback. `.env`/media sentinel не изменились; PHP syntax всего payload проверен. |
| Git | `git diff --check` успешно. Пользовательские изменения и старые отдельные overlay-скрипты не сбрасывались. |

## Настоящий браузер, не HTML-имитация

Использован установленный Chromium через Playwright. Сеть контекстов ограничена localhost. Standalone CMS имела отдельную SQLite и tmpfs, из настоящего проекта читались только код и исходные публичные материалы. Никакие реальные CMS-данные, `.env`, SMTP, GitHub API или Netcup не использовались.

- Загрузка двух изображений через Media UI, создание DE/EN проекта, выбор обложки, изменение порядка галереи, сохранение Entwurf.
- Несохранённый текст → новая вкладка ожидания → реальная локальная сборка → выбранный проект; исходные URL/поля/БД сохранены. Закрытие preview и отдельное сохранение из редактора.
- Реальное завершение сессии → HTTP **419** → вход в новой вкладке → свежий CSRF и повтор с **тем же request_id** → ровно один preview → явное сохранение без reload редактора. Evidence: `session-recovery.json`.
- Renaissance DE→EN→DE и локализованные ChatBot ссылки внутри preview без повторного base.
- Публикация кнопкой CMS, повторное нажатие без нового job, настоящий package/build/приём результата/current, публичный DE/EN проект. Посторонние draft отсутствуют.
- Ошибка доставки имитирована статусом runner: прежний HTTP-сайт и published-статус остаются. Retry использует прежнюю запись/пакет. После успешного unpublish проект HTTP 404, после rollback HTTP 200 и published восстановлен. Удаление черновика через UI — soft delete. Публичное удаление/восстановление/групповые действия дополнительно покрыты Livewire-тестами.
- Главная, индекс портфолио, Renaissance, услуги, контакт: DE/EN при **320, 390, 768, 1440 px** (40 сочетаний). Нет горизонтальной прокрутки, изображения загружаются, console errors/404 отсутствуют. Реально нажаты мобильное меню, Cookies, ChatBot, фильтры; переключение DE/EN через клавиатурно открытое мобильное меню сохраняет проект. Ещё восемь служебных/информационных маршрутов проверены HTTP.
- Отправка обеих контактных форм через настоящий POST к локальному Laravel: HTTP 200, локализованный success. **Array mailer, не SMTP-доставка.** Защита формы, ошибки transport, rate limit и Origin проверены backend-тестами.

Скриншоты и JSON evidence: `installation-artifacts/release-qa/`. В частности `preview-de.png`, `preview-en.png`, `session-419-editor-preserved.png`, `session-recovered-saved.png`, `production-en.png`, `home-{de,en}-{390,1440}.png`, `project-{de,en}-390.png`, `contact-{de,en}-success.png`; итоговые JSON: `browser-production.json`, `session-recovery.json`, `frontend-browser.json`, `navigation-browser.json`. Файлы с `error`/`progress` — промежуточная диагностика, не финальный результат.

В ходе проверки исправлены два дефекта **самого тестового стенда**: кэш realpath долгоживущего PHP dev-server после переключения symlink и недостаточная прокрутка для поздно загружаемых изображений. Проверки повторены; эти изменения не выдаются за исправление Apache/Netcup. Проверены именно Chromium/эмуляция viewport, не физический телефон и не Safari/WebKit.

## Выпуск и оставшиеся внешние проверки

Единственная инструкция: [NETCUP_RELEASE_RU.md](NETCUP_RELEASE_RU.md). В комплекте — 26 backend production-файлов, два новых PHP-класса, одна аддитивная migration, source-копии workflow/распаковщика/двух frontend-компонентов, manifest, checksums, backup/preflight/apply/rollback. `config:cache` запрещён; Composer запускается `/usr/local/php84/bin/php`. Установленные preview-классы и target_path/request_id остаются.

Перед установкой требуется **один read-only preflight** из раздела 3 инструкции. Фактические Netcup checksums не получены: manifest before — проверяемая Git-база, а не подмена хэшей сервера. Неизвестные исходники останавливают installer.

После будущего review/commit/merge: новый точный source SHA для production и preview, workflow в main, приватно настроенные GitHub/SSH secrets. Затем backend backup/update/autoload/только ожидаемая migration, права каталогов, первый выпуск без Test при старом docroot, и лишь после проверки — согласованное переключение docroot на current. Приёмка завершается реальной проверкой SSH/SFTP, GitHub dispatch, FastCGI путей/лимитов/OPcache, symlink-serving/HTTPS, авторизации preview, отсутствия private-файлов в HTTP и SMTP-доставки. Для первого выпуска откат docroot — к записанному прежнему значению; для последующих — проверенная команда rollback current.

Сбои сборки/доставки и компенсация DB-транзакции испытаны локально, но это не испытание реального сетевого обрыва SSH или аварийного выключения хостинга. Полной транзакционности между файловой системой и БД при падении процесса/машины не обещаем: перед повтором обязательно сверить current и запись заказа по runbook. Полный restore рабочей БД не является штатным лечением ошибки аддитивной migration.

Реальная SMTP-доставка, DNS почты, WCP document root, внешние credentials и установленные server hashes — оставшиеся проверки, которые нельзя честно выполнить локально. Секреты в чат не нужны. Production gate и сервер в ходе этой работы не включались; Test не публиковался.

## Дополнение: восстановление пароля администратора (после PR #4)

Рабочая ветка `fix/admin-password-reset` создана в отдельном worktree от заново полученного `origin/main = 88a45f3ef04d4beb41a6a682d9cc09e5b94073b0`. Пользовательская рабочая копия и два прежних архива не изменены (проверены SHA-256). Следующие проверки выполнены на этапе локальной реализации, до подготовки commit и draft PR; merge и установки не было.

Использованы установленные Laravel **12.69.2**, Filament **5.8.1**, Livewire **4.4.4**, без новых зависимостей. Включён штатный password broker/Filament flow; наследники страниц задают одинаковый результат запроса, немецкое письмо и строгую валидацию. URL — подписанный `https://admin.madebymadlen.de`, не зависит от входного Host или APP_URL, 60 минут. Токен хранится хэшированным и потребляется штатным broker; транзакционная блокировка строки сериализует его потребление. Новый пароль не может совпадать с прежним; проверка совпадения выполняется только при действительном токене.

Механизм AuthenticateSession проверяет хэш пароля во всех web/Livewire/preview/media/CSRF-recovery запросах. Login event записывает отметку при настоящем входе. Старая сессия без отметки не получает автоматически новый хэш: требуется вход. Native reset меняет пароль и remember token, удаляет reset token; автоматического входа нет, регистрации нет.

### Автоматические проверки этого дополнения

- **54 backend-теста / 6018 assertions**, без failures: новая группа — **11 тестов / 200 assertions**, плюс полный CMS/preview/production/contact набор. SQLite `:memory:`, array/fake mail и поддельные GitHub ответы. Полный набор включает реальные локальные Astro preview/production builds, финальную JS-постобработку, повтор публикации из редактора, две разные сборки при recovery и rollback.
- Новые тесты: известный/неизвестный email и broker throttle с одинаковым notification/form state; немецкий email; защита от подмены домена; подпись/60 минут; invalid/expired/reused token; слабый/неподтверждённый/слишком длинный UTF-8 пароль; запрет прежнего пароля; IP/reset limits; отключённая регистрация; mail failure и последующий retry; запрет log transport; старые/unstamped sessions; настоящий Login event.
- **8 Node-тестов** preview-base, Renaissance DE/EN, lost/late response, 500/419 recovery — pass.
- Обычная Astro-сборка DE/EN: exit 0, **49 страниц**. В stderr Astro/Vite печатает `The build was canceled` при начальной синхронизации, после чего основной build завершается `Complete!`; это не скрыто и не выдано за отсутствие сообщений в логе. Frontend-код этим исправлением не менялся.
- Symfony YAML parser и `bash -n` для **17** workflow-блоков — pass; workflow не запускался.
- Обновлённый preflight: **6 отказных сценариев**, запуск без stat/Composer в PATH, **7** bash-блоков runbook — pass.
- Распакованный тестовый kit: **36 production-файлов**, per-file SHA-256, guard исходников/stale report/symlink, apply/reapply/rollback, отказ при последующей правке, сохранение аддитивной миграции и неизменность synthetic env/media — pass.
- В отдельной Linux-копии через PHP 8.4 выполнен Composer `dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts --no-plugins` без сети; все **шесть** новых классов совокупного комплекта разрешаются.

Первый запуск полного набора на read-only/noexec mounts не позволял Astro удалить его временную .astro и backup-тесту выполнить синтетический mysqldump. Набор повторён на отдельной записываемой Linux-копии с теми же исходниками и изолированной БД: приведены результаты успешного повторного запуска, а не замена проверок чтением кода.

### Браузер и границы проверки

**PASS: настоящий Chromium, семь групп сценариев.** Два независимых входа и remember-cookie; ссылка возле пароля; одинаковый результат известного/неизвестного email; неверная подпись (403), истёкший broker token; имитация сбоя почты и успешный retry; слабый/неподтверждённый пароль; успешный reset и возврат к login; старый Livewire snapshot с cookies (401), старая сессия на CSRF-recovery (404, токен не выдан), replay старых cookies и remember-only cookie; повтор ссылки и старого пароля отклонены, новым паролем вход успешен; неверный CSRF (419). Число пользователей, previews и publications не изменилось.

Запросы старой сессии повторяются из стабильного локального HTML probe в том же browser origin, поскольку исходная вкладка CMS может автоматически переходить на login при отзыве доступа. Это не обход backend: запросы Livewire/CSRF идут в настоящий локальный Laravel со старым подписанным snapshot и cookies; подменяется только пустой HTML-документ, запускающий проверочный fetch.

Сценарий воспроизводится `scripts/release/qa-password-reset-browser.mjs` с локальным PHP fixture `qa-password-reset.php` и отдельной SQLite. Итог и обзорные изображения находятся в `installation-artifacts/password-reset-qa/`; токены, пароли и содержимое cookies в итоговый JSON не записываются. Canonical HTTPS origin проксируется Playwright только на localhost: это настоящий Chromium/UI/Livewire, но не проверка реального TLS/DNS или Netcup. Срок broker-токена и ошибка отправки изменяются только в disposable fixture.

Реальная SMTP-доставка, сертификат/HTTPS, session/cache driver и права FastCGI, MySQL-конкурентность на Netcup остаются серверными проверками. Одноразовость последовательно проверена локально; конкурентную MySQL-гонку отдельным серверным тестом здесь не воспроизводили.

### Доставка

Единый состав — **36** production-файлов: 26 из PR #4 + десять из этого исправления. Четыре новых reset-класса + два production-класса; одна migration PR #4, **нет новой reset migration**. Нужны Composer autoload, config:clear, route:clear и view:clear; config:cache запрещён.

Инструкция — только `docs/NETCUP_RELEASE_RU.md`. Перед commit проверено: reset через default `MAIL_MAILER=smtp` и контакт через `MADLEN_CONTACT_MAILER=smtp` используют один `mail.mailers.smtp` из `backend/config/mail.php` и существующие MAIL_* credentials, без отдельного аккаунта. Код для этого менять не потребовалось; полный набор тестов при подготовке PR повторно не запускался. Серверная доставка проверяется отдельно. Пакет, исходные server hashes, backup и application checks проверяются до первого production gate; Test остаётся Entwurf.

Финальный архив в этой задаче **не создаётся**: после review/нового merge требуется чистый checkout его точного SHA, новая сборка и проверка metadata/payload/checksums. Старый kit из merge PR #4 сохраняется контрольным, но не устанавливается как полный комплект с reset.
