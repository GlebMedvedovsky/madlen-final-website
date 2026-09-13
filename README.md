# Made by Madlen

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Project and authorship

I am **Gleb Medvedovskyy**, the owner and developer of this software project. I built Made by Madlen as a bilingual photography and videography portfolio with a private content management system.

**I designed and developed this project with AI-assisted development, testing and deployment workflows.**

I am responsible for the project direction, architectural decisions, configuration, review and release management. Implementation and verification use AI-assisted workflows alongside the project's frameworks and tools. This is not a claim that I manually wrote every line of code or personally performed every automated check. I distinguish recorded test results from live operational verification.

The portfolio presents Madlen's work. My software role does not imply authorship of her photographs, videos or editorial content. Third-party frameworks retain their own authorship and licences.

[Public website](https://madebymadlen.de) · [Repository](https://github.com/GlebMedvedovsky/madlen-final-website)

### 2. Architecture

| Layer | My implementation |
| --- | --- |
| Public frontend | Astro, TypeScript and component-scoped CSS; static German and English pages. |
| Private backend | Laravel 12, Filament 5 and Livewire 4; database-backed CMS and authenticated media access. |
| Editorial workflow | Projects, media, DE/EN text, service cards, homepage media and revision history. |
| Preview | A protected, expiring build from an immutable content snapshot; project preview includes validated unsaved fields. |
| Production | A controlled release process: CMS request → external Astro build → verified delivery → activation of `current`. |
| Hosting | Netcup hosts the backend and the generated production website. Node/npm builds run outside the hosting account. |

GitHub stores code and workflows. Routine content changes belong in the CMS, not in source files or direct edits to a published release. Saving a CMS record does not immediately change the static website.

### 3. Editorial and release workflow

1. I upload and organise media, or the authorised editor does so in the CMS.
2. The editor prepares both languages, chooses a cover and orders the gallery, then saves a draft.
3. Project **Vorschau** opens a separate tab without saving the unsaved form to the main project record.
4. The editor returns to the original form and explicitly chooses **Speichern** or **Veröffentlichen**.
5. A publication request packages an immutable snapshot. GitHub Actions builds the pinned source revision and delivers a checked result.
6. The backend activates the verified release. A failure before activation leaves the previous website available.

Publishing a selected draft explicitly includes it; unrelated drafts remain excluded. A site-wide publication includes saved changes to already published projects and shared content. A successful response gives the project editor a new request identity for the next deliberate publication; retrying a lost response retains the original identity.

Rollback selects a previous managed static release and reconciles the associated project lifecycle changes. It does not overwrite newer editorial text or media. See the [publisher runbook](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md).

### 4. Local frontend development

Use the lockfile and the Node version required by the current workflow. The release workflow uses Node 22.

```bash
npm ci
npm run dev
```

The development server is normally available at `http://localhost:4321`.

| Command | Purpose |
| --- | --- |
| `npm run build` | Ordinary static build into `dist/`. |
| `npm run preview` | Serve that local static build; not an authenticated CMS preview. |
| `npm run build:preview` | Build a prepared CMS preview package using its required arguments. |
| `npm run build:publication` | Build a prepared production package using its required arguments. |
| `npm run test:preview-routing` | Base-path and bilingual routing regression tests. |
| `npm run test:preview-editor-client` | Project-preview request recovery tests. |

A build without a CMS manifest uses repository fallback content. It is not a copy of the current production CMS. Backend setup and isolated testing are described in the [backend guide](backend/README.md).

### 5. Contact, access and security

The public contact form submits to Laravel. Server-side validation, origin checks, anti-bot controls and rate limits protect the endpoint; SMTP credentials never enter the frontend. Contact mail and administrator password recovery can use the same configured SMTP mailer.

The German administrator interface provides password recovery with a one-time, signed HTTPS link, a 60-minute expiry and session invalidation after a successful reset. Public registration is disabled. Preview access remains tied to its authenticated owner and expiry.

I keep credentials, database exports, media backups and infrastructure account details outside public documentation and Git. I do not treat a successful build as proof of email delivery, legal compliance or a complete production acceptance test.

### 6. Repository map

| Location | Purpose |
| --- | --- |
| `src/` | Astro pages, components, routing and CMS-content consumption. |
| `backend/` | Laravel application, Filament administration and backend tests. |
| `content/` | Import/fallback content, not the live editorial database. |
| `public/images/` | Versioned frontend assets; see the [media guide](public/images/README.md). |
| `scripts/preview/`, `scripts/production/` | External build and packaging tools. |
| `scripts/release/` | Backend allowlist, preflight, installer and isolated package checks. |
| `.github/workflows/` | Preview and production build workflows. |

### 7. Documentation and verification

- [CMS operating guide](ADMIN_CMS.md)
- [Backend development](backend/README.md)
- [Production publisher](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md)
- [Release and recovery procedure](docs/NETCUP_RELEASE_RU.md)
- [Initial installation boundaries](docs/NETCUP_INSTALLATION_RU.md)
- [Verification record and limitations](docs/RELEASE_VERIFICATION_RU.md)

Each guide starts in English and includes its complete Russian translation. Existing filenames ending in `_RU.md` are retained for compatibility with repository tooling.

The verification record distinguishes automated tests, isolated browser exercises and production observations. The documentation update does not run a deployment or resolve live editorial issues. Legal pages require a separate content review and final approval by the website owner or legal adviser.

---

<a id="russian"></a>
## Русский

### 1. Проект и авторство

Я — **Gleb Medvedovskyy**, владелец и разработчик этого программного проекта. Я создал Made by Madlen как двуязычное портфолио фотографа и видеографа с закрытой системой управления контентом.

**Я спроектировал и разработал этот проект, используя AI-assisted процессы разработки, тестирования и развёртывания.**

Я отвечаю за направление проекта, архитектурные решения, настройку, проверку изменений и управление выпусками. Реализация и проверки используют процессы с помощью AI наряду с фреймворками и инструментами проекта. Это не означает, что я вручную написал каждую строку кода или лично выполнил каждую автоматическую проверку. Я разделяю зафиксированные результаты тестов и проверки работающей системы.

Портфолио представляет работы Мадлен. Моя роль в разработке ПО не означает авторства её фотографий, видео или редакционных текстов. Авторство и лицензии сторонних фреймворков сохраняются.

[Публичный сайт](https://madebymadlen.de) · [Репозиторий](https://github.com/GlebMedvedovsky/madlen-final-website)

### 2. Архитектура

| Уровень | Моя реализация |
| --- | --- |
| Публичный frontend | Astro, TypeScript и CSS компонентов; статические немецкие и английские страницы. |
| Закрытый backend | Laravel 12, Filament 5 и Livewire 4; CMS с базой данных и авторизованным доступом к медиа. |
| Редакционная работа | Проекты, медиа, тексты DE/EN, карточки услуг, медиа главной страницы и история изменений. |
| Предпросмотр | Защищённая сборка с ограниченным сроком действия из неизменяемого снимка контента; предпросмотр проекта включает валидированные несохранённые поля. |
| Production | Контролируемый выпуск: запрос CMS → внешняя сборка Astro → проверенная доставка → активация `current`. |
| Хостинг | Netcup размещает backend и собранный публичный сайт. Сборки Node/npm выполняются вне аккаунта хостинга. |

GitHub хранит код и workflow. Обычные изменения контента выполняются через CMS, а не редактированием исходников или файлов опубликованного релиза. Сохранение записи CMS не меняет статический сайт немедленно.

### 3. Работа с контентом и выпуск

1. Я загружаю и организую медиа либо это делает авторизованный редактор в CMS.
2. Редактор заполняет обе языковые версии, выбирает обложку, упорядочивает галерею и сохраняет черновик.
3. **Vorschau** проекта открывает отдельную вкладку, не сохраняя несохранённую форму в основную запись проекта.
4. Редактор возвращается в исходную форму и отдельно выбирает **Speichern** или **Veröffentlichen**.
5. Запрос публикации формирует неизменяемый снимок. GitHub Actions собирает закреплённую ревизию исходников и доставляет проверенный результат.
6. Backend активирует проверенный релиз. При ошибке до активации прежний сайт остаётся доступным.

Публикация выбранного черновика явно включает его; посторонние черновики не включаются. Общая публикация сайта включает сохранённые изменения уже опубликованных проектов и общих разделов. После подтверждённого ответа редактор проекта получает новую идентичность запроса для следующей осознанной публикации; повтор потерянного ответа сохраняет исходную идентичность.

Откат выбирает предыдущий управляемый статический релиз и согласует связанные изменения статусов проектов. Более новые редакционные тексты и медиа не перезаписываются. Подробнее — в [руководстве публикации](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md).

### 4. Локальная разработка frontend

Используйте lockfile и версию Node, указанную в актуальном workflow. Workflow выпуска использует Node 22.

```bash
npm ci
npm run dev
```

Сервер разработки обычно доступен по адресу `http://localhost:4321`.

| Команда | Назначение |
| --- | --- |
| `npm run build` | Обычная статическая сборка в `dist/`. |
| `npm run preview` | Запуск этой локальной сборки; это не авторизованный предпросмотр CMS. |
| `npm run build:preview` | Сборка подготовленного пакета предпросмотра CMS с обязательными аргументами. |
| `npm run build:publication` | Сборка подготовленного production-пакета с обязательными аргументами. |
| `npm run test:preview-routing` | Регрессионные тесты базового пути и двуязычных маршрутов. |
| `npm run test:preview-editor-client` | Тесты восстановления запросов предпросмотра проекта. |

Сборка без манифеста CMS использует резервный контент репозитория. Это не копия текущей production CMS. Настройка backend и изолированное тестирование описаны в [руководстве backend](backend/README.md).

### 5. Контактная форма, доступ и безопасность

Публичная контактная форма отправляет данные в Laravel. Endpoint защищают серверная валидация, проверка origin, антибот-механизмы и ограничения частоты запросов; SMTP credentials не попадают во frontend. Контактная форма и восстановление пароля администратора могут использовать один настроенный SMTP mailer.

Немецкий интерфейс администратора поддерживает восстановление пароля по одноразовой подписанной HTTPS-ссылке со сроком 60 минут и отзывом сессий после успешного сброса. Публичная регистрация отключена. Предпросмотр доступен только авторизованному владельцу в пределах срока действия.

Я храню credentials, выгрузки базы, резервные копии медиа и сведения об аккаунтах инфраструктуры вне публичной документации и Git. Успешная сборка для меня не является доказательством доставки почты, юридического соответствия или полной приёмки production.

### 6. Структура репозитория

| Расположение | Назначение |
| --- | --- |
| `src/` | Страницы Astro, компоненты, маршрутизация и чтение контента CMS. |
| `backend/` | Приложение Laravel, панель Filament и backend-тесты. |
| `content/` | Контент импорта и резервной сборки, не рабочая редакционная база. |
| `public/images/` | Версионируемые ресурсы frontend; см. [руководство медиа](public/images/README.md). |
| `scripts/preview/`, `scripts/production/` | Инструменты внешней сборки и упаковки. |
| `scripts/release/` | Список backend-файлов, preflight, установщик и изолированные проверки пакета. |
| `.github/workflows/` | Workflow сборки предпросмотра и production. |

### 7. Документация и проверки

- [Руководство CMS](ADMIN_CMS.md)
- [Разработка backend](backend/README.md)
- [Production publisher](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md)
- [Порядок выпуска и восстановления](docs/NETCUP_RELEASE_RU.md)
- [Границы первоначальной установки](docs/NETCUP_INSTALLATION_RU.md)
- [Результаты проверок и ограничения](docs/RELEASE_VERIFICATION_RU.md)

Каждое руководство начинается с английской версии и содержит её полный русский перевод. Существующие имена с окончанием `_RU.md` сохранены для совместимости с инструментами репозитория.

В отчёте о проверках разделены автоматические тесты, изолированные браузерные сценарии и наблюдения production. Обновление документации не запускает развёртывание и не исправляет редакционные проблемы действующего сайта. Юридические страницы требуют отдельной проверки контента и окончательного одобрения владельцем сайта или юристом.
