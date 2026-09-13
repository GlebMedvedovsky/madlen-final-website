# Madlen CMS — operating guide

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Purpose and overview

I am Gleb Medvedovskyy, owner and developer of this software project. I designed the CMS to keep editorial decisions separate from code changes and public delivery.

**I designed and developed this project with AI-assisted development, testing and deployment workflows.**

I take responsibility for architecture, configuration, review and release management. AI-assisted implementation and recorded automated checks support that work; they do not imply that I manually wrote all code or authored Madlen's portfolio content.

The interface is German. I retain its exact button names in this guide. The website is DE/EN; the platform is Astro with Laravel 12, Filament 5 and Livewire 4. Netcup is the production hosting provider. GitHub manages code and workflows; ordinary content changes go through the CMS.

| Action | CMS effect | Public effect |
| --- | --- | --- |
| **Speichern** | Saves the form; a draft stays a draft. | None until publication. |
| Project **Vorschau** | Snapshots validated unsaved fields without saving the project. | Private preview only. |
| Project **Veröffentlichen** | Saves fields and requests publication of this project. | Changes after successful activation. |
| **Website produktiv veröffentlichen** | Snapshots saved content and published projects. | One new site release; unrelated drafts excluded. |

### 2. Sign-in and password recovery

I use the [administrator sign-in](https://admin.madebymadlen.de/admin/login), not a public registration form. **E-Mail-Adresse** and **Passwort** are required.

For recovery, select **Passwort vergessen?**, enter the administrator email and choose **E-Mail zusenden** once. Known and unknown addresses receive the same on-screen message. Check the inbox and spam folder; if no message arrives, wait at least a minute before trying again or contact the administrator. Do not share a recovery link.

The German email contains a one-time signed HTTPS link on `admin.madebymadlen.de`, valid for 60 minutes. The new password requires confirmation, at least 12 characters, upper/lowercase letters, a number and a special character, and at most 72 UTF-8 bytes. It must differ from the previous password.

An invalid, expired or used link requires a new request. Successful reset does not sign in automatically: sign in with the new password. The old password, recovery token and previous authenticated/remembered sessions no longer grant access; session rejection occurs on the next request. Delivery errors do not change the password. Rate limits, CSRF and login protection remain active.

### 3. Übersicht / Dashboard

I use **Übersicht** to check project and media counts, draft/readiness information, connection state and the active publication. This is a read-only overview: no required fields or save action.

With the external publisher, publication links lead to **Produktiv-Aufträge**. “Connected” describes configuration, not a continuous test of GitHub, SMTP or SSH. Open the relevant record before acting; a count or readiness indicator does not prove that the current content was published.

### 4. Projekte

I create and edit portfolio projects here. The list offers category/status/trash filters and project ordering.

Required: a unique **Stabile URL-Kennung**, existing **Kategorie**, image **Titelbild**, numeric **Reihenfolge**, and **Titel / Beschreibung** in both language tabs. Titles allow 255 characters. The identifier is locked after creation; use letters, digits and hyphens for preview/publication. Use a non-negative integer for ordering; smaller values come first.

A gallery can be empty, but each added row requires an image and **Links/Rechts** placement. Select the cover separately. Use **Galeriebild hinzufügen**, then drag or use up/down controls; filenames and upload order do not determine the gallery order. Duplicate image associations are rejected in preview/publication.

- **Erstellen** creates a draft. **Speichern** does not build; an already published project's status stays published.
- **Vorschau** checks current fields; create a new record first to use its editor preview.
- **Veröffentlichen** saves and requests a release. A later build failure does not undo that save.
- **Nicht mehr veröffentlichen** removes the public project only after activation.
- **Als Entwurf duplizieren** creates a draft with reused media.
- **Löschen** removes a public project through a release before soft deletion; non-public projects can be soft-deleted directly.
- **Wiederherstellen** returns a deleted project as a draft, not as published.

Bulk deletion containing public/in-progress projects is refused as a whole. No permanent-delete action is offered. I check DE/EN, cover, order and preview before publishing once. Missing English fields, duplicate gallery rows and unfinished media are common errors.

### 5. Medien

I use **Medien** for reusable images and prepared videos. A file is required on creation. Single upload accepts JPEG, PNG, WebP, MP4 and WebM; **Mehrere Dateien hochladen** accepts images, not videos. SVG/RAW are not supported uploads.

DE/EN **Alternativtext** is optional (1,000 characters); **Bildunterschrift** is optional (2,000). I still provide meaningful alternative text. Captions are stored but are not exported as a separate project-caption field.

Uploads are checked and images receive a WebP derivative; originals remain. Defaults are 20 MiB/image, 50 MiB/video, 12,000 px maximum image edge and 2,400 px derivative width. Hosting limits can be lower. Videos are not transcoded. Imported source assets must be replaced by uploading and assigning a new asset.

Saving makes media available in the CMS, not automatically visible on the website. Assign it to a project or homepage slot and publish. Used media, including references from restorable projects, are protected from deletion. After a partial multi-upload failure, inspect the list before retrying. I verify image dimensions, both languages and actual composition in preview.

### 6. Seiten & Texte

I edit existing navigation, homepage, portfolio, service-introduction, about, contact, footer and legal text here. This section does not create routes or change layout. Only existing records can be edited; technical keys are protected.

Visible text fields are required in both languages. Single-line fields allow 1,000 characters; benefit titles allow 255 and require their body text. Added paragraphs require text. Legal documents require both language values.

**Speichern** saves both languages and records a revision. Changes appear publicly only through a successful site publication. I reopen the record and check both tabs before **Gesamte Vorschau**.

A label does not create a feature. Chat has additional code-owned text; contact option labels must match backend validation. Some process/CTA/meta text remains in source code. Empty required fields, wrong language tabs and pasting privacy text into terms are risks; verify record identity and full rendered content.

### 7. Leistungen

I manage service cards, ordering, highlighting and per-language visibility here. Identifier, numeric position and German title/description are required. The identifier is locked after creation; title limit is 255. The optional symbol allows 20 characters. English title/description are required when English is enabled.

German videography and editing are separate cards; English uses the combined **Videography & Editing** entry. The existing editing record therefore hides its English editor. This does not translate or merge text automatically.

Saving, sorting, soft deletion and restoration change CMS content first. A release is required for public changes. If every German service is disabled/deleted, the DE page uses its source fallback; this is not a reliable way to remove the whole section. EN has no such fallback. I check both visibility switches and the rendered DE/EN lists before publishing.

### 8. Veröffentlichungen

I use this section for site-wide actions. Its older/local Release table is not the external publisher's authoritative history. There are no editable required fields.

- **Gesamte Vorschau** builds saved content, including drafts but normally excluding unpublished projects. It opens the preview homepage in the same tab; open this list in a separate admin tab to preserve an editor.
- **Website produktiv veröffentlichen** requests a confirmed site release from saved content and already published projects.
- **Öffentliche Website öffnen** opens the public site.
- **Backup erstellen** creates a private database/media backup; it is not a restore test or download/restore wizard.

The site-wide action retains its request identity for the lifetime of this page. After a clearly completed job, reopen the list for a deliberately new site-wide publication. Do not reload to recover an uncertain response: inspect the existing job first. Backup/list viewing does not change the public site.

### 9. Produktiv-Aufträge

I track external publication here. It appears when the GitHub publisher is configured as connected. The read-only table shows job number, status, progress, requester and activation time; the error column can be enabled. There are no create, edit-status, retry, delete or rollback controls.

Find the number from the confirmation and follow that job in a separate tab. Waiting, building and uploading are not completion. Refresh the list if necessary; do not reload an unsaved editor. Share only the job number, time and sanitised error with support.

A job holds its original content snapshot and source revision. Later editing does not update that package. **Produktiv aktiv**, followed by a public DE/EN check, is the completion criterion—not simply a successful GitHub run.

### 10. Startseiten-Medien

I assign three existing homepage slots: video, video poster and Memories photograph. **Auswählen** opens the assignment; **Anderes Medium auswählen** is required. Video accepts video media, the other two accept images. Both languages share the file.

Saving changes the CMS assignment; a release makes it public. There are no controls for frame, tilt, line geometry or spacing. Upload first, select the correct slot, save and preview both languages/mobile. Select a poster separately: choosing a video does not generate one or repair incompatible codecs/proportions.

### 11. Einstellungen

I keep existing general settings here: required primary language, contact email and website URL; email/URL fields allow 1,000 characters. Records can be edited, not created or deleted. Never enter credentials.

These values are saved/exported, but the current frontend does not consume these three general settings; it does consume media slots. Saving or publishing them does not change routing, the hard-coded mail fallback, SMTP recipients or Astro's site origin. Domain/recipient changes therefore require technical review, not repeated publication. Settings have no revision-restore action.

### 12. Änderungsverlauf

I use revision history for project, page/text and service fields. It is read-only except **Diesen Stand wiederherstellen**, which requires confirmation. An edit revision usually stores the state before the change.

Restoring creates the current CMS field state, not a public rollback. Project identifiers/current publication status are preserved for existing non-deleted projects; a restored deleted project becomes a draft. Gallery relations/order are not part of the field revision. Media/settings/homepage slots do not have equivalent restoration here.

Compare newer changes and both languages before restoring. Do not restore a whole bilingual record to repair one field if it would overwrite a correct translation. Check the gallery separately and publish only after review.

### 13. Vorschau

I preview an existing project's current form without saving it. **Vorschau** opens a waiting tab and then the selected project, initially in German. The original URL and fields remain. Closing preview neither saves nor discards them.

Media must already exist in the media library. Unfinished uploads, deleted media and duplicate gallery associations are rejected rather than silently replaced. Other page content comes from saved CMS records. Drafts may be visible in preview without being eligible for normal production publication.

Access is authenticated, owner-bound and time-limited (default 120 minutes); sharing the URL does not authorise another user. DE/EN project and ChatBot links retain one preview prefix.

- Blocked tab: use **Vorschau öffnen**, not another build.
- Lost response/server error: **Erneut versuchen** retains the operation identity and may return the earlier snapshot.
- **Sitzung abgelaufen** / 419: **Anmeldung öffnen**, sign in with the same account in the new tab, return and retry. Authenticated recovery refreshes CSRF without reloading the editor.
- Validation/build error: fix or investigate the reported cause before requesting another preview.

This two-tab recovery is specific to project preview; site-wide preview uses saved content and the same tab. Preview does not disable the contact form: submitting it may send real mail.

### 14. Contact form

I use a Laravel mail endpoint, not a CMS inbox or booking/payment system. Required visitor fields are name, email, request type, preferred date, location, message and privacy acknowledgement; phone is optional. Name allows 2–120 characters, message 10–5,000, date/location up to 160. Request types must match the language-specific backend list.

Validation, allowed origins, honeypot, timing checks and per-IP limits protect submission. A success response confirms transport acceptance, not inbox delivery. On error, retain the text and follow the displayed guidance or direct email alternative. A lost response may follow an accepted email; the form does not have publication-job idempotency.

CMS labels do not configure mail recipients or SMTP. Contact and password recovery use the shared SMTP configuration when their respective mailer selectors choose it. Real delivery and mail-domain checks require a separately agreed test.

### 15. Impressum, Datenschutz and AGB

I keep each legal document in its own bilingual CMS record.

| Record | German route | English route |
| --- | --- | --- |
| `legal.legal-notice` | `/impressum` | `/en/legal-notice` |
| `legal.privacy` | `/datenschutz` | `/en/privacy` |
| `legal.terms` | `/agb` | `/en/terms` |

The key selects the route; the second non-empty text line becomes the visible heading. Check the whole text, not just the browser title. Legal pages currently do not preserve their route through the header language switch; use the matching footer link.

The read-only audit on 13 September 2026 found privacy text in German terms and a hosting placeholder in German privacy. CMS, the active manifest and served HTML agreed: this was a content issue, not routing. Correct earlier German terms exist in revision history. Equivalent defects were not found in EN or Impressum. This documentation change does not resolve those live records.

My correction procedure is: back up the affected values → change only intended language fields → save → inspect all six pages in protected preview → obtain final website-owner/legal review → create one site-wide publication → wait for activation → inspect all six public pages. Do not restore a full older record that contains an obsolete English translation. Hosting must identify the actual provider, netcup GmbH; no placeholders. I do not present technical checks as legal approval.

### 16. Working with DE/EN

I review both language tabs before saving; switching tabs does not save. Required English fields can prevent saving while the German tab is visible.

Project identifier, category, cover, media and gallery order/placement are shared. Titles, descriptions, alternative text and page content are language-specific. Projects have one publication status, not independent DE/EN approvals. Services have language-visibility controls.

I check the homepage, localised sections and the same portfolio project in both languages, including mobile navigation. A preview must retain its own base path exactly once. There is no automatic translation or guarantee of semantic equivalence.

### 17. Standard change procedure

I identify the affected records and current job first, then prepare media, permissions and both languages. I avoid parallel editing of the same record.

Project preview may use unsaved fields; other sections require saving before site-wide preview. After review, the editor decides whether to retain a draft or publish. A new site build also includes saved changes to shared sections and already public projects, so I review that full scope.

I record the job number and check the public result after activation. Routine content is not maintained in parallel in source arrays or fallback JSON. A code merge does not automatically change the source revision pinned by the server.

### 18. Publication step by step

1. I ensure no unresolved publication is running and the intended content is saved/reviewed.
2. Use project **Veröffentlichen** to include that selected project, or **Website produktiv veröffentlichen** for the saved public site. Never publish a test draft unintentionally.
3. Confirm once and record the job number.
4. The system prepares a checksummed content/media package, dispatches the external workflow and builds the pinned Astro source.
5. Delivery and activation verify package/source/content identity, runner and safe file paths before switching `current`.
6. Wait for **Produktiv aktiv**, then inspect public DE/EN pages, legal documents, images and excluded drafts.

Failures before activation preserve the old site. A handled CMS transaction failure after switching triggers compensation to the previous pointer. A machine/process crash can require investigation; filesystem and database changes are not claimed to be one universal transaction.

### 19. Errors, waiting and retries

I inspect the existing job before any retry. **Übergabe unbestätigt** means the dispatch response is unknown; the runner may already be working. Build/transfer errors do not justify repeated clicks or new jobs from other tabs.

A lost project-publication response retains its request identity and immutable snapshot. After an acknowledged response, a later deliberate publication from the same editor receives a new identity and new content. These are different operations.

Technical retry is performed by the developer only after confirming the old runner is stopped. It reuses the original package/job; a new build can have a different output ZIP checksum, which is still validated. Old runners, stale jobs and unsafe release replacement remain rejected. See the [runbook](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md).

### 20. Rollback and restoration

I distinguish four operations: restoring a deleted project as a draft; restoring selected CMS fields from history; rolling back a managed public release; and recovering data from a backup.

Production rollback is a developer command, not a CMS button. It requires a verified target and no running publication. It restores the static release and associated publish/unpublish/delete state, but preserves newer text/media. The next publication may reintroduce saved changes unless those are reviewed.

Backups contain database/media and checksums; sensitive configuration/code require the corresponding protected operational backup. A ready archive is not a restore test. Restore into a separate test database first. Do not restore the whole working database to repair one legal field or an additive migration error. Migration recovery first compares schema, indexes and migration history.

### 21. Security rules

I keep credentials, reset/preview links, private paths, database exports and media backups out of public documentation and Git. Do not paste executable code into CMS text, share administrator sessions or weaken CSRF, origin checks, expiry or owner checks.

Do not edit active release files, import fallback content over the working CMS, remove media directly on the server or run destructive database resets. Server configuration, infrastructure credentials and recovery are developer-controlled operations. Updates preserve the existing application key and data.

On the current hosting layout, **config caching is prohibited** because CLI/FastCGI resolve paths differently. Detailed procedures use reviewed private parameters rather than publishing account details.

### 22. Editorial and development boundaries

I provide CMS controls for existing content, projects, galleries, media, service visibility and homepage assignments. Layout, route definitions, new feature types, contact validation, mail transport, publication logic and hosting configuration remain code/operations responsibilities.

Changing a label does not change its implementation. GitHub reviews code/workflow changes; normal CMS edits do not require a content commit. I do not promise functionality not connected to the frontend, such as general settings taking immediate effect or stored captions becoming visible.

Verification claims and outstanding operational checks are maintained in the [verification record](docs/RELEASE_VERIFICATION_RU.md).

### 23. Daily checklist

- [ ] I identified the correct record, both languages and any existing job.
- [ ] Required fields, media rights, cover and gallery order are checked.
- [ ] I reviewed the appropriate preview and saved the intended content.
- [ ] Other saved public changes are approved; unrelated drafts remain excluded.
- [ ] Legal changes have final owner/legal review; all six legal routes were checked.
- [ ] I confirmed publication once and recorded its job number.
- [ ] Activation and the actual public DE/EN result are verified.
- [ ] Errors are investigated without duplicate requests or destructive recovery.

### 24. Status glossary

| Value | Meaning |
| --- | --- |
| Project `draft` / Entwurf | Excluded from ordinary production; explicitly selecting publish can include it. |
| Project `published` / Veröffentlicht | Eligible for production snapshots; newly saved changes are not instantly online. |
| Project `unpublished` / Nicht veröffentlicht | Normally excluded from production/site preview; selected project preview remains possible. |
| Soft-deleted | In trash; restoring a project returns it as a draft. |
| Job `preparing` / `prepared` | Preparing / package ready. |
| Job `queued` / `building` / `uploading` | Waiting / building / transferring; not yet active. |
| Job `dispatch_unknown` | Dispatch unconfirmed; inspect the existing job. |
| Job `active` | Verified release activated and acknowledged by CMS. |
| Job `superseded` | Previously active; replaced by a newer release. |
| Job `failed` | Investigate; saved editorial data is not automatically reverted. |
| Job `rolled_back` | Reversed by rollback, not a new publishable operation. |
| Preview `ready` / `failed` / `expired` | Available / failed / expired; independent of production. |
| Backup `ready` | Archive created, not proof of successful restoration. |

I always distinguish the CMS record, preview snapshot, publication job and website actually being served.

---

<a id="russian"></a>
## Русский

### 1. Назначение и обзор

Я — Gleb Medvedovskyy, владелец и разработчик этого программного проекта. Я спроектировал CMS так, чтобы отделить редакционные решения от изменений кода и публичной доставки.

**Я спроектировал и разработал этот проект, используя AI-assisted процессы разработки, тестирования и развёртывания.**

Я отвечаю за архитектуру, настройку, review и управление выпусками. Реализация с помощью AI и зафиксированные автоматические проверки поддерживают эту работу; это не означает, что я вручную написал весь код или создал контент портфолио Мадлен.

Интерфейс немецкий. Я сохраняю в руководстве точные названия кнопок. Сайт работает на DE/EN; платформа — Astro с Laravel 12, Filament 5 и Livewire 4. Production размещается на Netcup. GitHub используется для кода и workflow; обычный контент изменяется через CMS.

| Действие | Результат в CMS | Публичный результат |
| --- | --- | --- |
| **Speichern** | Сохраняет форму; черновик остаётся черновиком. | До публикации изменений нет. |
| **Vorschau** проекта | Создаёт снимок валидированных несохранённых полей без сохранения проекта. | Только закрытый preview. |
| **Veröffentlichen** проекта | Сохраняет поля и запрашивает публикацию этого проекта. | Изменения после успешной активации. |
| **Website produktiv veröffentlichen** | Создаёт снимок сохранённого контента и опубликованных проектов. | Один новый выпуск сайта; посторонние черновики исключены. |

### 2. Вход и восстановление пароля

Я использую [вход администратора](https://admin.madebymadlen.de/admin/login), а не публичную регистрацию. **E-Mail-Adresse** и **Passwort** обязательны.

Для восстановления выберите **Passwort vergessen?**, введите email администратора и один раз нажмите **E-Mail zusenden**. Для существующего и неизвестного адреса экранное сообщение одинаково. Проверьте входящие и спам; если письма нет, подождите минимум минуту перед повтором или обратитесь к администратору. Не передавайте ссылку восстановления.

Немецкое письмо содержит одноразовую подписанную HTTPS-ссылку на `admin.madebymadlen.de` со сроком 60 минут. Новый пароль требует подтверждения, минимум 12 символов, верхний и нижний регистр, цифру и специальный символ, максимум 72 UTF-8-байта. Он должен отличаться от прежнего.

Для неверной, просроченной или использованной ссылки нужен новый запрос. После успешного сброса автоматического входа нет: войдите новым паролем. Старый пароль, токен восстановления и прежние авторизованные/запомненные сессии больше не дают доступ; сессия отклоняется при следующем запросе. Ошибка доставки не меняет пароль. Ограничения частоты, CSRF и защита входа сохраняются.

### 3. Übersicht / Dashboard

Я использую **Übersicht** для просмотра количества проектов и медиа, сведений о черновиках и готовности, подключения и активной публикации. Это обзор без редактирования: обязательных полей и сохранения нет.

При внешнем publisher ссылки публикаций ведут в **Produktiv-Aufträge**. «Подключено» означает состояние конфигурации, а не постоянную проверку GitHub, SMTP или SSH. Перед действием откройте соответствующую запись: счётчик или индикатор готовности не доказывает публикацию текущего контента.

### 4. Projekte

Здесь я создаю и редактирую проекты портфолио. Список поддерживает фильтры категории, статуса, корзины и сортировку проектов.

Обязательны: уникальная **Stabile URL-Kennung**, существующая **Kategorie**, изображение **Titelbild**, числовая **Reihenfolge**, **Titel / Beschreibung** в обеих языковых вкладках. Заголовки — до 255 символов. Идентификатор после создания заблокирован; для preview и публикации используйте буквы, цифры и дефисы. Порядок задавайте неотрицательным целым числом; меньшие значения идут раньше.

Галерея может быть пустой, но каждая добавленная строка требует изображение и размещение **Links/Rechts**. Обложка выбирается отдельно. Используйте **Galeriebild hinzufügen**, затем перетаскивание или кнопки вверх/вниз; имена файлов и порядок загрузки не определяют галерею. Повторные связи с одним изображением отклоняются при preview и публикации.

- **Erstellen** создаёт черновик. **Speichern** не запускает сборку; статус опубликованного проекта сохраняется.
- **Vorschau** проверяет текущие поля; для нового проекта сначала создайте запись.
- **Veröffentlichen** сохраняет и запрашивает выпуск. Последующая ошибка сборки не отменяет сохранение.
- **Nicht mehr veröffentlichen** убирает проект с сайта только после активации.
- **Als Entwurf duplizieren** создаёт черновик с повторным использованием медиа.
- **Löschen** сначала удаляет публичный проект через выпуск, затем помещает в корзину; непубличный проект можно сразу мягко удалить.
- **Wiederherstellen** возвращает удалённый проект черновиком, а не опубликованным.

Массовое удаление с публичными или обрабатываемыми проектами отклоняется целиком. Окончательное удаление в интерфейсе не предлагается. Перед однократной публикацией я проверяю DE/EN, обложку, порядок и preview. Типичные ошибки — незаполненный английский, дубли в галерее и незавершённая загрузка.

### 5. Medien

Я использую **Medien** для повторно используемых изображений и подготовленных видео. При создании файл обязателен. Одиночная загрузка принимает JPEG, PNG, WebP, MP4 и WebM; **Mehrere Dateien hochladen** — изображения, но не видео. SVG/RAW не поддерживаются.

**Alternativtext** DE/EN необязателен (1 000 символов); **Bildunterschrift** также необязательна (2 000). Тем не менее я заполняю осмысленные альтернативные тексты. Подписи сохраняются, но не экспортируются отдельным полем подписи проекта.

Загрузки проверяются; для изображений создаётся WebP-производная, оригиналы сохраняются. Стандартные ограничения: 20 MiB на изображение, 50 MiB на видео, сторона изображения до 12 000 px, ширина производной до 2 400 px. Хостинг может ограничивать сильнее. Видео не перекодируется. Для замены импортированного ресурса исходников нужно загрузить и назначить новое медиа.

Сохранение делает медиа доступным в CMS, но не автоматически на сайте. Назначьте его проекту или слоту главной и опубликуйте. Используемые медиа, включая связи с восстанавливаемыми проектами, защищены от удаления. После частичного сбоя множественной загрузки сначала проверьте список. Я проверяю размеры, оба языка и реальную композицию в preview.

### 6. Seiten & Texte

Здесь я редактирую существующие тексты навигации, главной, портфолио, вводного блока услуг, страницы обо мне, контакта, футера и юридических страниц. Раздел не создаёт маршруты и не меняет дизайн. Редактируются только существующие записи; технические ключи защищены.

Видимые текстовые поля обязательны на обоих языках. Однострочные поля допускают 1 000 символов; заголовки преимуществ — 255, текст преимущества обязателен. Добавленные абзацы требуют текста. Юридические документы требуют обе языковые версии.

**Speichern** сохраняет оба языка и фиксирует ревизию. Публично изменения появляются только после успешной общей публикации. Перед **Gesamte Vorschau** я повторно открываю запись и проверяю обе вкладки.

Подпись не создаёт новую функцию. Часть текста чата управляется кодом; названия вариантов контакта должны соответствовать backend-валидации. Некоторые тексты процесса, CTA и метаданных остаются в исходниках. Риски — пустые обязательные поля, неверная языковая вкладка и вставка Datenschutz в AGB; проверяйте запись и полный результат отображения.

### 7. Leistungen

Здесь я управляю карточками услуг, порядком, выделением и видимостью по языкам. Обязательны идентификатор, числовая позиция, немецкие заголовок и описание. Идентификатор после создания заблокирован; заголовок — до 255 символов. Необязательный символ допускает 20 символов. Английские заголовок и описание обязательны при включённом EN.

На немецком видеография и монтаж — отдельные карточки; английская версия использует общую **Videography & Editing**. Поэтому у существующей записи монтажа английский редактор скрыт. Это не автоматический перевод или объединение текстов.

Сохранение, сортировка, мягкое удаление и восстановление сначала меняют только CMS. Для публичного результата нужен выпуск. Если отключить или удалить все немецкие услуги, DE-страница использует резервный список исходников; так нельзя надёжно убрать весь раздел. У EN такого fallback нет. Перед публикацией я проверяю оба переключателя видимости и списки DE/EN.

### 8. Veröffentlichungen

Я использую этот раздел для общих действий сайта. Его таблица старых/локальных Release не является основной историей внешнего publisher. Редактируемых обязательных полей нет.

- **Gesamte Vorschau** собирает сохранённый контент, включая черновики, но обычно исключая снятые с публикации проекты. Открывает главную preview в той же вкладке; чтобы сохранить редактор, откройте список в отдельной вкладке CMS.
- **Website produktiv veröffentlichen** после подтверждения запрашивает общий выпуск сохранённого контента и уже опубликованных проектов.
- **Öffentliche Website öffnen** открывает публичный сайт.
- **Backup erstellen** создаёт приватную копию базы и медиа; это не тест восстановления и не мастер скачивания/восстановления.

Общее действие сохраняет идентичность запроса на время жизни страницы. После однозначно завершённого задания повторно откройте список для осознанной новой общей публикации. Не перезагружайте страницу для восстановления неопределённого ответа: сначала проверьте существующее задание. Просмотр списка и резервное копирование не меняют публичный сайт.

### 9. Produktiv-Aufträge

Здесь я отслеживаю внешнюю публикацию. Раздел появляется при настроенном подключённом GitHub publisher. Таблица только для чтения показывает номер, статус, прогресс, инициатора и время активации; колонку ошибки можно включить. Создание, редактирование статуса, retry, удаление и rollback в ней не предусмотрены.

Найдите номер из подтверждения и наблюдайте именно это задание в отдельной вкладке. Ожидание, сборка и передача ещё не означают завершения. При необходимости обновляйте список, но не несохранённый редактор. Поддержке передавайте только номер, время и очищенную от чувствительных данных ошибку.

Задание хранит исходный снимок и ревизию кода. Последующее редактирование не меняет пакет. Критерий завершения — **Produktiv aktiv** и последующая публичная проверка DE/EN, а не просто успешный GitHub run.

### 10. Startseiten-Medien

Я назначаю три существующих слота главной: видео, постер видео и фотографию Memories. **Auswählen** открывает назначение; **Anderes Medium auswählen** обязательно. Видео принимает видеомедиа, остальные два — изображения. Файл общий для обоих языков.

Сохранение меняет назначение в CMS; публичным его делает выпуск. Настроек рамки, наклона, геометрии линии или интервалов нет. Сначала загрузите файл, выберите верный слот, сохраните и проверьте оба языка и мобильную версию. Постер выбирается отдельно: выбор видео не создаёт его и не исправляет несовместимые кодеки или пропорции.

### 11. Einstellungen

Здесь я храню существующие общие настройки: обязательные основной язык, контактный email и URL сайта; email/URL — до 1 000 символов. Записи можно редактировать, но не создавать или удалять. Не вводите credentials.

Эти значения сохраняются и экспортируются, но текущий frontend не использует именно эти три общие настройки; медиа-слоты он использует. Их сохранение или публикация не меняет маршрутизацию, фиксированную почтовую альтернативу, SMTP-получателя или site origin Astro. Поэтому изменение домена или получателя требует технического review, а не повторных публикаций. Восстановления настроек через историю нет.

### 12. Änderungsverlauf

Я использую историю для полей проектов, страниц/текстов и услуг. Она доступна для чтения, кроме **Diesen Stand wiederherstellen**, требующего подтверждения. Ревизия редактирования обычно содержит состояние до изменения.

Восстановление задаёт текущие поля CMS, а не откатывает публичный сайт. У существующего неудалённого проекта сохраняются идентификатор и текущий статус публикации; восстановленный удалённый проект становится черновиком. Связи и порядок галереи не входят в ревизию полей. Для медиа, настроек и слотов главной аналогичного восстановления здесь нет.

Перед восстановлением сравните новые изменения и оба языка. Не восстанавливайте целую двуязычную запись ради одного поля, если это перезапишет корректный перевод. Отдельно проверьте галерею; публикуйте только после проверки.

### 13. Vorschau

Я проверяю текущую форму существующего проекта без сохранения. **Vorschau** открывает вкладку ожидания, затем выбранный проект, сначала на немецком. Исходные URL и поля сохраняются. Закрытие preview не сохраняет и не сбрасывает их.

Медиа должны уже существовать в библиотеке. Незавершённые загрузки, удалённые медиа и дубли галереи отклоняются, а не незаметно заменяются. Остальной контент берётся из сохранённых записей CMS. Черновик может быть виден в preview, не входя в обычную production-публикацию.

Доступ требует авторизации, связан с владельцем и ограничен по времени (стандартно 120 минут); передача URL не даёт доступ другому пользователю. Ссылки проекта DE/EN и ChatBot сохраняют один preview-префикс.

- Вкладка заблокирована: используйте **Vorschau öffnen**, не новую сборку.
- Потерян ответ/ошибка сервера: **Erneut versuchen** сохраняет идентичность операции и может вернуть прежний снимок.
- **Sitzung abgelaufen** / 419: **Anmeldung öffnen**, войдите тем же аккаунтом в новой вкладке, вернитесь и повторите. Авторизованное восстановление обновляет CSRF без перезагрузки редактора.
- Ошибка валидации/сборки: исправьте или исследуйте указанную причину перед новым preview.

Восстановление с двумя вкладками относится именно к preview проекта; общий preview использует сохранённый контент и ту же вкладку. Preview не отключает контактную форму: отправка может доставить настоящее письмо.

### 14. Контактная форма

Я использую почтовый endpoint Laravel, а не входящие CMS или систему бронирования/оплаты. Обязательны имя, email, тип запроса, желаемая дата, место, сообщение и согласие с уведомлением о конфиденциальности; телефон необязателен. Имя — 2–120 символов, сообщение — 10–5 000, дата/место — до 160. Тип запроса должен соответствовать языковому списку backend.

Отправку защищают валидация, разрешённые origin, honeypot, проверка времени и лимиты на IP. Успех подтверждает принятие transport, а не доставку во входящие. При ошибке сохраните текст и следуйте сообщению либо используйте прямой email. Потерянный ответ может следовать за уже принятым письмом; у формы нет идемпотентности заданий публикации.

Подписи CMS не настраивают получателей или SMTP. Контакт и восстановление пароля используют общую SMTP-конфигурацию, если она выбрана соответствующими переключателями mailer. Реальная доставка и настройки почтового домена проверяются отдельно по согласованию.

### 15. Impressum, Datenschutz и AGB

Я храню каждый юридический документ в отдельной двуязычной записи CMS.

| Запись | Немецкий маршрут | Английский маршрут |
| --- | --- | --- |
| `legal.legal-notice` | `/impressum` | `/en/legal-notice` |
| `legal.privacy` | `/datenschutz` | `/en/privacy` |
| `legal.terms` | `/agb` | `/en/terms` |

Ключ выбирает маршрут; вторая непустая строка текста становится видимым заголовком. Проверяйте весь текст, а не только заголовок вкладки. Сейчас переключатель языка в хедере не сохраняет маршрут юридической страницы; используйте соответствующую ссылку футера.

Read-only аудит 13 сентября 2026 года обнаружил Datenschutz в немецком AGB и плейсхолдер хостинга в немецком Datenschutz. CMS, активный манифест и отдаваемый HTML совпали: причина в контенте, не в маршрутизации. Прежний корректный немецкий AGB есть в истории. Аналогичные дефекты в EN и Impressum не обнаружены. Это обновление документации не исправляет рабочие записи.

Мой порядок исправления: сохранить исходные значения → изменить только нужные языковые поля → сохранить → проверить все шесть страниц в защищённом preview → получить финальную проверку владельца сайта/юриста → создать одну общую публикацию → дождаться активации → проверить все шесть публичных страниц. Не восстанавливайте целиком старую запись с устаревшим английским переводом. Нужно указать реального провайдера netcup GmbH, без плейсхолдеров. Я не выдаю технические проверки за юридическое одобрение.

### 16. Работа с DE/EN

Перед сохранением я проверяю обе языковые вкладки; переключение вкладки не сохраняет. Обязательные английские поля могут мешать сохранению даже при открытой немецкой вкладке.

Идентификатор проекта, категория, обложка, медиа, порядок и размещение галереи общие. Заголовки, описания, альтернативные тексты и тексты страниц разделены по языкам. У проекта один статус публикации, а не независимые согласования DE/EN. У услуг есть переключатели языковой видимости.

Я проверяю главную, локализованные разделы и один и тот же проект на обоих языках, включая мобильную навигацию. Preview должен сохранять свой базовый путь ровно один раз. Автоматического перевода или гарантии смыслового соответствия нет.

### 17. Стандартный порядок изменений

Сначала я определяю затронутые записи и текущее задание, затем подготавливаю медиа, разрешения и оба языка. Избегаю параллельного редактирования одной записи.

Preview проекта может использовать несохранённые поля; для остальных разделов перед общим preview нужно сохранение. После проверки редактор решает оставить черновик или публиковать. Новая сборка также включает сохранённые изменения общих разделов и уже публичных проектов, поэтому я проверяю весь этот объём.

Я фиксирую номер задания и проверяю публичный результат после активации. Обычный контент не ведётся параллельно в массивах исходников или резервном JSON. Merge кода не меняет автоматически ревизию исходников, закреплённую на сервере.

### 18. Публикация по шагам

1. Я убеждаюсь, что нет неразрешённой текущей публикации, а нужный контент сохранён и проверен.
2. Используйте **Veröffentlichen** проекта для включения именно этого проекта либо **Website produktiv veröffentlichen** для сохранённого публичного сайта. Не публикуйте тестовый черновик случайно.
3. Подтвердите один раз и запишите номер задания.
4. Система подготавливает пакет контента/медиа с checksums, запускает внешний workflow и собирает закреплённые исходники Astro.
5. До переключения `current` доставка и активация проверяют идентичность пакета, исходников, контента, runner и безопасность путей.
6. Дождитесь **Produktiv aktiv**, затем проверьте публичные DE/EN-страницы, юридические документы, изображения и исключённые черновики.

Ошибки до активации сохраняют прежний сайт. Обработанная ошибка транзакции CMS после переключения вызывает возврат прежнего указателя. При аварии процесса или машины может потребоваться расследование; изменения файловой системы и базы не объявляются единой универсальной транзакцией.

### 19. Ошибки, ожидание и повторы

Перед повтором я проверяю существующее задание. **Übergabe unbestätigt** означает неизвестный результат отправки; runner уже может работать. Ошибка сборки или передачи не является причиной повторных нажатий или новых заданий из других вкладок.

Потерянный ответ публикации проекта сохраняет идентичность запроса и неизменяемый снимок. После подтверждённого ответа следующая осознанная публикация из того же редактора получает новую идентичность и новый контент. Это разные операции.

Технический retry выполняет разработчик только после подтверждения остановки прежнего runner. Он повторно использует исходный пакет/задание; новая сборка может иметь другую checksum итогового ZIP, которая всё равно проверяется. Старые runner, устаревшие задания и небезопасная замена релизов отклоняются. Подробнее — в [runbook](docs/PRODUCTION_PUBLISHER_RUNBOOK_RU.md).

### 20. Откат и восстановление

Я разделяю четыре операции: возврат удалённого проекта черновиком; восстановление отдельных полей CMS из истории; откат управляемого публичного релиза; восстановление данных из резервной копии.

Production rollback — команда разработчика, а не кнопка CMS. Нужны проверенная цель и отсутствие текущей публикации. Он возвращает статический релиз и связанные состояния публикации, снятия и удаления, сохраняя новые тексты/медиа. Следующая публикация может повторно включить сохранённые изменения, если их не проверить.

Резервные копии содержат базу, медиа и checksums; чувствительная конфигурация и код требуют соответствующей защищённой эксплуатационной копии. Готовый архив не означает проверенное восстановление. Сначала восстанавливайте в отдельную тестовую базу. Не возвращайте целиком рабочую базу ради одного юридического поля или ошибки аддитивной миграции. Восстановление миграции начинается со сравнения схемы, индексов и истории миграций.

### 21. Правила безопасности

Я не включаю credentials, ссылки сброса/preview, приватные пути, выгрузки базы и резервные копии медиа в публичную документацию и Git. Не вставляйте исполняемый код в тексты CMS, не передавайте сессии администратора и не ослабляйте CSRF, проверку origin, срок действия или проверку владельца.

Не редактируйте файлы активного релиза, не импортируйте резервный контент поверх рабочей CMS, не удаляйте медиа непосредственно на сервере и не выполняйте разрушительный сброс базы. Конфигурация сервера, credentials инфраструктуры и восстановление относятся к операциям разработчика. Обновления сохраняют существующий ключ приложения и данные.

В текущей конфигурации хостинга **кеширование config запрещено**, поскольку CLI/FastCGI по-разному разрешают пути. Подробные процедуры используют проверенные приватные параметры, а не публикуют данные аккаунтов.

### 22. Границы редакционной работы и разработки

Я предоставляю средства CMS для существующего контента, проектов, галерей, медиа, видимости услуг и назначений главной страницы. Дизайн, определения маршрутов, новые типы функций, валидация контакта, почтовый transport, логика публикации и конфигурация хостинга остаются задачами кода и эксплуатации.

Изменение подписи не меняет реализацию. Изменения кода/workflow проходят review в GitHub; обычные правки CMS не требуют коммита контента. Я не обещаю функции без связи с frontend, например немедленное применение общих настроек или отображение сохранённых подписей.

Результаты проверок и оставшиеся эксплуатационные проверки ведутся в [отчёте](docs/RELEASE_VERIFICATION_RU.md).

### 23. Ежедневный чек-лист

- [ ] Я определил правильную запись, оба языка и существующее задание.
- [ ] Обязательные поля, права на медиа, обложка и порядок галереи проверены.
- [ ] Я проверил соответствующий preview и сохранил нужный контент.
- [ ] Другие сохранённые публичные изменения одобрены; посторонние черновики исключены.
- [ ] Юридические изменения прошли финальную проверку владельца/юриста; проверены все шесть маршрутов.
- [ ] Я подтвердил публикацию один раз и записал номер задания.
- [ ] Активация и реальный публичный результат DE/EN проверены.
- [ ] Ошибки исследуются без дубликатов запросов или разрушительного восстановления.

### 24. Словарь статусов

| Значение | Смысл |
| --- | --- |
| Проект `draft` / Entwurf | Исключён из обычной production-сборки; явная публикация выбранного проекта может включить его. |
| Проект `published` / Veröffentlicht | Включается в production-снимки; новые сохранённые изменения не появляются немедленно. |
| Проект `unpublished` / Nicht veröffentlicht | Обычно исключён из production/общего preview; целевой preview проекта возможен. |
| Мягко удалён | В корзине; восстановление проекта возвращает его черновиком. |
| Задание `preparing` / `prepared` | Подготовка / пакет готов. |
| Задание `queued` / `building` / `uploading` | Ожидание / сборка / передача; ещё не активно. |
| Задание `dispatch_unknown` | Отправка не подтверждена; проверьте существующее задание. |
| Задание `active` | Проверенный релиз активирован и подтверждён CMS. |
| Задание `superseded` | Ранее активное; заменено новым выпуском. |
| Задание `failed` | Требует расследования; сохранённые редакционные данные не откатываются автоматически. |
| Задание `rolled_back` | Отменено откатом, не является новой операцией публикации. |
| Preview `ready` / `failed` / `expired` | Доступен / ошибка / истёк; независимо от production. |
| Backup `ready` | Архив создан, но успешное восстановление не доказано. |

Я всегда разделяю запись CMS, снимок preview, задание публикации и фактически отдаваемый сайт.
