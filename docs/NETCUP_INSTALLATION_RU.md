# Madlen installation boundaries

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Initial migration versus an update

I am Gleb Medvedovskyy. I manage installation as a controlled transfer of code, configuration and persistent data. Madlen is already hosted on Netcup; initial-import instructions are not an everyday maintenance procedure.

I use the [current release procedure](NETCUP_RELEASE_RU.md) for an installed system. Historical partial overlays are not interchangeable with the consolidated release kit. Their original instructions remain associated with their original releases; I do not repeat an old installation over a working backend.

### 2. What belongs where

| Responsibility | Handling |
| --- | --- |
| Code and workflow | Reviewed Git revisions and verified release payloads. |
| Database and editorial content | Persistent CMS state, backed up before migration. |
| Uploaded originals and derivatives | Persistent private media storage; not replaced by a source checkout. |
| Application key, mail and service credentials | Existing protected configuration, transferred only through approved private channels. |
| Public site | Validated static release selected by the managed `current` pointer. |

I do not publish hosting usernames, database addresses, SMTP account values or private filesystem paths. The operator maintains a separate private parameter sheet. CLI and FastCGI paths must be resolved through the existing hosting layout, not guessed from one another.

### 3. Requirements for a genuinely new destination

I confirm that the destination database and media target are empty and intended for this migration before any import. If either contains data, I stop and investigate; I do not replace it with a local dump or fallback content.

Before transfer, I create a consistent database/media backup, verify archive and internal checksums, and restore-test it in an isolated database. Code, dependency lockfiles and configuration backups are recorded separately. The destination uses verified PHP and required extensions; dependencies follow the lockfile.

I transfer into a new private staging directory, check the package contents and permissions, preserve the application key and import only the approved data set. The public document root remains unchanged until acceptance. No registration, diagnostic endpoint or weaker authentication is introduced to make installation easier.

### 4. Hosting acceptance

I check CLI and FastCGI independently: path resolution, private-storage permissions, upload/time/memory limits, sessions, HTTPS and public-file isolation. A CLI symlink test does not prove that the web server can serve the release pointer.

Netcup performs no Node/npm build. PHP runs the backend; an external runner builds Astro. The configuration must remain uncached. A trusted private Composer PHAR is run with the same PHP 8.4 interpreter; an unknown PHAR's freshly calculated checksum is not a trust source.

Preview acceptance includes authenticated owner-only access, expiry, DE/EN navigation and an unchanged public release. Contact and password-reset acceptance includes actual mail delivery; local fake/array transports cannot prove SMTP or inbox delivery. Any scheduler is checked as a recurring task, not just a one-off command.

### 5. Recovery and handover

I record the old document root and rollback target before the first cutover. I retain backups and previous releases until acceptance. Static rollback, code rollback and data restoration are separate procedures; an additive migration error does not justify restoring the entire working database.

The handover records the deployed source revision, package integrity, completed checks, known limitations and the next responsible operator. Subsequent changes use the CMS or the reviewed code-release process—not another initial import.

See the [CMS guide](../ADMIN_CMS.md), [publisher runbook](PRODUCTION_PUBLISHER_RUNBOOK_RU.md) and [verification record](RELEASE_VERIFICATION_RU.md).

---

<a id="russian"></a>
## Русский

### 1. Первоначальный перенос и обновление

Я — Gleb Medvedovskyy. Я управляю установкой как контролируемым переносом кода, конфигурации и постоянных данных. Madlen уже размещён на Netcup; инструкции первоначального импорта не предназначены для повседневного обслуживания.

Для установленной системы я использую [актуальную инструкцию выпуска](NETCUP_RELEASE_RU.md). Исторические частичные overlay не взаимозаменяемы с единым release-kit. Их исходные инструкции относятся к исходным выпускам; я не повторяю старую установку поверх работающего backend.

### 2. Разделение компонентов

| Область ответственности | Обращение |
| --- | --- |
| Код и workflow | Проверенные ревизии Git и проверенный payload выпуска. |
| База и редакционный контент | Постоянное состояние CMS с резервной копией перед переносом. |
| Загруженные оригиналы и производные | Постоянное приватное хранилище медиа; checkout исходников его не заменяет. |
| Ключ приложения, почтовые и сервисные credentials | Существующая защищённая конфигурация; перенос только согласованным приватным каналом. |
| Публичный сайт | Проверенный статический релиз, выбранный управляемым указателем `current`. |

Я не публикую имена аккаунтов хостинга, адреса базы, значения SMTP-аккаунта или приватные пути файловой системы. Оператор ведёт отдельный приватный список параметров. Пути CLI и FastCGI определяются существующей конфигурацией хостинга, а не угадываются друг из друга.

### 3. Требования к действительно новому окружению

Перед импортом я подтверждаю, что целевая база и каталог медиа пусты и предназначены именно для этого переноса. Если там есть данные, я останавливаюсь и исследую их; не заменяю их локальным дампом или резервным контентом.

До переноса я создаю согласованную копию базы/медиа, проверяю архив и внутренние checksums и тестирую восстановление в изолированной базе. Код, lockfiles зависимостей и копии конфигурации учитываются отдельно. На целевом окружении используются проверенный PHP и необходимые расширения; зависимости соответствуют lockfile.

Я переношу пакет в новый приватный staging-каталог, проверяю состав и права, сохраняю ключ приложения и импортирую только одобренный набор данных. Публичный document root не меняется до приёмки. Для упрощения установки не добавляются регистрация, диагностический endpoint или ослабленная авторизация.

### 4. Приёмка хостинга

Я отдельно проверяю CLI и FastCGI: определение путей, права приватного хранилища, лимиты загрузки, времени и памяти, сессии, HTTPS и изоляцию публичных файлов. Проверка symlink из CLI не доказывает, что веб-сервер обслуживает указатель релиза.

На Netcup нет сборок Node/npm. PHP обслуживает backend; внешний runner собирает Astro. Конфигурация остаётся без кеширования. Доверенный приватный Composer PHAR запускается тем же интерпретатором PHP 8.4; только что вычисленная checksum неизвестного PHAR не является источником доверия.

Приёмка preview включает доступ только авторизованному владельцу, истечение срока, навигацию DE/EN и неизменность публичного релиза. Контакт и сброс пароля проверяются с фактической доставкой почты; локальные fake/array transport не доказывают работу SMTP или доставку во входящие. Scheduler проверяется как повторяющаяся задача, а не разовая команда.

### 5. Восстановление и передача в эксплуатацию

Перед первым переключением я фиксирую прежний document root и цель отката. До приёмки сохраняю резервные копии и предыдущие релизы. Откат статики, откат кода и восстановление данных — разные процедуры; ошибка аддитивной миграции не является основанием возвращать целиком рабочую базу.

При передаче фиксируются установленная ревизия, целостность пакета, выполненные проверки, ограничения и следующий ответственный оператор. Последующие изменения выполняются через CMS или проверенный процесс выпуска кода, а не повторный первоначальный импорт.

См. [руководство CMS](../ADMIN_CMS.md), [runbook публикации](PRODUCTION_PUBLISHER_RUNBOOK_RU.md) и [отчёт о проверках](RELEASE_VERIFICATION_RU.md).
