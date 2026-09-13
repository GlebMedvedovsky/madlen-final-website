# Madlen backend

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Responsibility and components

I am Gleb Medvedovskyy. I designed the backend around Laravel 12, Filament 5 and Livewire 4 to separate editorial work from public delivery. I manage architecture, configuration and release decisions; implementation and testing use AI-assisted workflows. Laravel, Filament and Livewire are third-party projects, not frameworks I authored.

I use the CMS for projects, ordered galleries, bilingual content, media and revisions. `ContentManifestService` exports content; `ProductionPublisher` coordinates external releases. `PreviewBuilder` creates protected previews. The legacy local release service is not the Netcup publication path.

### 2. Local environment

I use the repository's isolated Docker Compose setup for backend work. Review [the Compose configuration](../compose.yaml) and the example environment files before starting a disposable environment. Use project name `madlen`; do not share application databases or volumes with another project.

Install dependencies from `composer.lock`, not an unrestricted update. The current lockfile records Laravel 12.69.2, Filament 5.8.1 and Livewire 4.4.4. A lockfile describes dependencies, not proof of the installed server state.

Do not use the framework's generic setup script against production: it can generate configuration and run migrations. Existing CMS data must never be replaced by a development import. Local credentials belong in ignored environment files.

### 3. Tests and mail

Run the backend suite only with a verified isolated test database and temporary storage:

```bash
php artisan test
```

Tests cover editorial operations, preview isolation/recovery, publication identity and activation, contact validation, password reset, media and backup behaviour. Some tests execute actual local Astro builds; external dispatch and mail transport are substituted where documented. See the [verification record](../docs/RELEASE_VERIFICATION_RU.md), not an unconditional “all systems passed” claim.

The contact controller selects the configured contact mailer; administrator recovery uses Laravel's default mailer. Both can select the same `mail.mailers.smtp` configuration. I do not maintain a second SMTP account for recovery. Passwords, tokens and reset links must not be logged or included in reports.

### 4. Operations and ownership

[The CMS guide](../ADMIN_CMS.md) describes editorial actions. [The release procedure](../docs/NETCUP_RELEASE_RU.md) covers preflight, backups, autoload, migrations and rollback.

On Netcup, PHP runs the backend and release activation; Astro builds run externally. Configuration remains uncached because hosting paths differ between CLI and FastCGI. I keep runtime state, media and credentials separate from code updates.

Framework licence terms are retained in dependencies. This README does not grant a new licence for the portfolio's photographs, videos or project-specific assets.

---

<a id="russian"></a>
## Русский

### 1. Ответственность и компоненты

Я — Gleb Medvedovskyy. Я спроектировал backend на Laravel 12, Filament 5 и Livewire 4, чтобы отделить редакционную работу от публичной доставки сайта. Я управляю архитектурой, настройкой и решениями о выпуске; реализация и тестирование используют AI-assisted процессы. Laravel, Filament и Livewire — сторонние проекты, а не разработанные мной фреймворки.

Я использую CMS для проектов, упорядоченных галерей, двуязычного контента, медиа и истории изменений. `ContentManifestService` экспортирует контент; `ProductionPublisher` управляет внешними выпусками. `PreviewBuilder` создаёт защищённый предпросмотр. Старый локальный сервис выпуска не используется для публикации на Netcup.

### 2. Локальное окружение

Для backend я использую изолированную конфигурацию Docker Compose из репозитория. Перед запуском одноразового окружения изучите [конфигурацию Compose](../compose.yaml) и примеры переменных окружения. Используйте имя проекта `madlen`; не объединяйте рабочие базы или тома с другим проектом.

Зависимости устанавливаются из `composer.lock`, без произвольного обновления. Текущий lockfile фиксирует Laravel 12.69.2, Filament 5.8.1 и Livewire 4.4.4. Lockfile описывает зависимости, но не доказывает состояние установленного сервера.

Не запускайте стандартный setup-скрипт фреймворка на production: он может создавать конфигурацию и применять миграции. Существующие данные CMS нельзя заменять импортом из среды разработки. Локальные credentials хранятся в игнорируемых файлах окружения.

### 3. Тесты и почта

Backend-тесты запускаются только с проверенной изолированной тестовой базой и временным хранилищем:

```bash
php artisan test
```

Тесты охватывают редакционные операции, изоляцию и восстановление preview, идентичность и активацию публикаций, валидацию контакта, сброс пароля, медиа и резервное копирование. Часть тестов выполняет настоящие локальные сборки Astro; внешняя отправка заданий и почтовый transport заменяются там, где это указано. Ориентир — [отчёт о проверках](../docs/RELEASE_VERIFICATION_RU.md), а не безусловное утверждение «все системы проверены».

Контроллер контакта выбирает настроенный contact mailer; восстановление администратора использует mailer Laravel по умолчанию. Оба могут выбирать одну конфигурацию `mail.mailers.smtp`. Я не использую отдельный SMTP-аккаунт для восстановления. Пароли, токены и ссылки сброса нельзя записывать в журнал или отчёт.

### 4. Эксплуатация и авторство

[Руководство CMS](../ADMIN_CMS.md) описывает редакционные действия. [Инструкция выпуска](../docs/NETCUP_RELEASE_RU.md) охватывает preflight, резервные копии, autoload, миграции и откат.

На Netcup PHP обслуживает backend и активацию релизов; Astro собирается снаружи. Конфигурация остаётся без кеширования, поскольку пути CLI и FastCGI различаются. Я отделяю рабочее состояние, медиа и credentials от обновлений кода.

Лицензии фреймворков сохраняются в зависимостях. Этот README не предоставляет новую лицензию на фотографии, видео или собственные ресурсы портфолио.
