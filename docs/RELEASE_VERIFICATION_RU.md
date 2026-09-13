# Madlen verification record

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. How I interpret evidence

I am Gleb Medvedovskyy. I manage acceptance criteria and release decisions for this project, using AI-assisted implementation and testing workflows. The results below are recorded outcomes from earlier development and release checks. They are not a claim that I manually executed every test, that this documentation update reran the application suite, or that local simulation proves every production condition.

I separate four evidence types: automated assertions, real local builds, isolated browser interaction and live operational observations. Sensitive fixtures, tokens, credentials and private evidence paths are not published here.

### 2. Recorded automated results

| Stage | Recorded result | Scope and limits |
| --- | --- | --- |
| Initial release candidate | 41 backend tests / 5,740 assertions; ordinary Astro build of 49 pages. | Isolated database/storage and local builds; not a Netcup acceptance run. |
| Publication review | 43 backend tests / 5,818 assertions; targeted publication tests 14 / 260. | New same-editor publication identity; lost-request reuse; two real output builds with different ZIP checksums during activation recovery. |
| Password recovery | 54 backend tests / 6,018 assertions, including 11 new tests / 200 assertions. | Known/unknown email, invalid/expired/reused links, strength/confirmation, mail failure, session revocation, CSRF and disabled registration. |
| Client regressions | 8 recorded Node tests for preview routing/request recovery. | Base once, project DE/EN, final client processing, lost/late response and 500/419 scenarios. |
| Workflow and packaging | Symfony YAML parsing; shell syntax checks for 17 workflow blocks; ZIP safety and build-filter checks. | Parsing/policy checks, not a real GitHub runner or SSH outage. |
| Preflight compatibility | Success without cmp, stat or Composer in PATH; six rejection cases and eight individually missing-tool cases. | Synthetic trusted-PHAR fixture, not the server's Composer credentials or installed state. |
| Backend kit | 36 allowlisted production files; payload hashes, extraction, apply/reapply and rollback guards. | Isolated copies and sentinel data; does not prove the current server's before-hashes. |
| Database and backup | Additive migration up/down in isolated MySQL; SQL/media round trip using actual local dump/restore tools. | Separate database, not restoration of the working CMS. |

These counts belong to their recorded stages, not a single combined test run. The current backend allowlist is [backend-files.json](../scripts/release/backend-files.json). Password recovery adds no migration; the consolidated kit's new migration is production operation identity.

### 3. Recorded browser exercises

Earlier Chromium tests used isolated local CMS data and restricted networking. They exercised media upload, DE/EN project creation, gallery ordering, draft save, unsaved project preview in a second tab, return to editing and explicit save.

Session-recovery checks exercised 419 → login in another tab → refreshed CSRF → retry with the same request identity and one preview, without reloading the original editor. Publication checks exercised separate deliberate requests in one editor and replay of a lost response without duplicate jobs. The lost-response publication test replayed the original HTTP body through browser fetch; it was not a claim of a new automatic retry button.

Frontend checks covered DE/EN at 320, 390, 768 and 1440 px, navigation, images, filters, mobile menu, Cookies, ChatBot and the contact form. Contact submissions used an array mailer: real HTTP handling, not real SMTP delivery.

Password-reset browser exercises covered seven scenario groups, including two sessions, remembered login, invalid/expired/reused links, transport failure, old-session rejection and CSRF. The canonical HTTPS origin was proxied to localhost. These were real Chromium interactions, not physical-phone, Safari/WebKit, DNS or production-certificate tests.

### 4. Recorded production observations

The last documented successful production build used source revision `2625ce4f6224bd7068a7b41344acb71119ea4d42`. Its [GitHub run](https://github.com/GlebMedvedovsky/madlen-final-website/actions/runs/34653647080) recorded activation. The main-branch workflow revision and the source revision selected by a publication are separate identities.

A read-only audit on 13 September 2026 compared the three legal CMS records, the active content snapshot and all six DE/EN public HTML files. They matched. German terms contained privacy text, and German privacy contained a hosting placeholder. English legal pages and Impressum had no equivalent observed mismatch. These were content defects; this documentation update does not save or publish their correction.

The same audit observed the active managed release and the test project remaining a draft. These are dated observations, not perpetual statements about server state.

### 5. Checks still requiring operational acceptance

I require explicit verification of actual SMTP delivery and spam handling, mail-domain configuration, HTTPS/certificate behaviour, FastCGI permissions/limits, installed dependency versions, current file checksums, backup restorability and any release following a code/content change.

Local failure injection does not replace a real delivery-path check. Sequential reset tests do not establish all concurrent MySQL behaviours. A handled activation exception is not a guarantee of universal recovery from a machine crash. Legal correctness requires the website owner's or legal adviser's final review.

### 6. Documentation-only review

For a documentation-only change, I check the Markdown diff, links and headings, EN/RU structural parity, source-linked technical statements and absence of credentials/private infrastructure values. Application and workflow files must remain unchanged. I report any targeted runbook checks actually rerun in the associated PR; the historical application counts above must not be presented as new results.

The [release procedure](NETCUP_RELEASE_RU.md) defines delivery and recovery. A documentation PR does not deploy a kit, change the pinned production source, run a publication or approve a merge.

---

<a id="russian"></a>
## Русский

### 1. Как я оцениваю доказательства

Я — Gleb Medvedovskyy. Я управляю критериями приёмки и решениями о выпуске, используя AI-assisted реализацию и тестирование. Ниже приведены зафиксированные результаты прежних проверок разработки и выпуска. Это не утверждение, что я вручную запускал каждый тест, что обновление документации повторило тесты приложения или что локальная имитация доказывает все условия production.

Я разделяю четыре вида доказательств: автоматические assertions, реальные локальные сборки, изолированную работу в браузере и наблюдения действующей системы. Чувствительные fixtures, токены, credentials и приватные пути материалов проверки здесь не публикуются.

### 2. Зафиксированные автоматические результаты

| Этап | Зафиксированный результат | Объём и ограничения |
| --- | --- | --- |
| Первоначальный кандидат | 41 backend-тест / 5 740 assertions; обычная сборка Astro из 49 страниц. | Изолированные база/хранилище и локальные сборки; не приёмка Netcup. |
| Review публикации | 43 backend-теста / 5 818 assertions; целевые тесты публикации 14 / 260. | Новая идентичность публикации из того же редактора; повтор потерянного запроса; две реальные сборки с разными checksums ZIP при восстановлении активации. |
| Восстановление пароля | 54 backend-теста / 6 018 assertions, включая 11 новых / 200 assertions. | Существующий/неизвестный email, неверные/просроченные/использованные ссылки, надёжность/подтверждение, сбой почты, отзыв сессий, CSRF и отключённая регистрация. |
| Клиентские регрессии | 8 зафиксированных Node-тестов маршрутизации/recovery preview. | Один base, проект DE/EN, финальная клиентская обработка, потерянный/поздний ответ и сценарии 500/419. |
| Workflow и упаковка | Парсинг Symfony YAML; синтаксис 17 shell-блоков workflow; безопасность ZIP и фильтры сборки. | Проверки парсинга и правил, не настоящий runner GitHub или обрыв SSH. |
| Совместимость preflight | Успех без cmp, stat и Composer в PATH; шесть отказов и восемь случаев отсутствующего инструмента по отдельности. | Синтетический доверенный PHAR, не Composer или установленное состояние сервера. |
| Backend kit | 36 production-файлов allowlist; hashes payload, распаковка, защиты apply/reapply и rollback. | Изолированные копии и контрольные данные; не доказательство текущих before-hashes сервера. |
| База и backup | Аддитивная миграция up/down в изолированном MySQL; цикл SQL/медиа с настоящими локальными dump/restore-инструментами. | Отдельная база, не восстановление рабочей CMS. |

Числа относятся к указанным этапам, а не к одному общему запуску. Актуальный allowlist — [backend-files.json](../scripts/release/backend-files.json). Для восстановления пароля новой миграции нет; новая миграция единого комплекта относится к идентичности production-операций.

### 3. Зафиксированные браузерные сценарии

Предыдущие проверки Chromium использовали изолированные локальные данные CMS и ограниченную сеть. Проверялись загрузка медиа, создание проекта DE/EN, порядок галереи, сохранение черновика, preview несохранённого проекта во второй вкладке, возврат к редактированию и явное сохранение.

Проверка восстановления сессии включала 419 → вход в другой вкладке → обновление CSRF → повтор с прежней идентичностью и одним preview, без перезагрузки исходного редактора. Проверки публикации включали отдельные осознанные запросы в одном редакторе и повтор потерянного ответа без дубликатов. Тест потерянного ответа публикации повторял исходное HTTP body через browser fetch; он не утверждал наличие новой кнопки автоматического retry.

Frontend проверялся на DE/EN при 320, 390, 768 и 1440 px: навигация, изображения, фильтры, мобильное меню, Cookies, ChatBot и контактная форма. Контакт использовал array mailer: настоящая HTTP-обработка, но не реальная SMTP-доставка.

Браузерные проверки сброса пароля охватывали семь групп сценариев, включая две сессии, запомненный вход, неверные/просроченные/использованные ссылки, сбой transport, отказ старым сессиям и CSRF. Канонический HTTPS origin проксировался на localhost. Это настоящая работа Chromium, но не физический телефон, Safari/WebKit, DNS или production-сертификат.

### 4. Зафиксированные наблюдения production

Последняя документированная успешная production-сборка использовала ревизию `2625ce4f6224bd7068a7b41344acb71119ea4d42`. В [GitHub run](https://github.com/GlebMedvedovsky/madlen-final-website/actions/runs/34653647080) зафиксирована активация. Ревизия workflow в main и ревизия исходников, выбранная публикацией, — разные идентификаторы.

Read-only аудит 13 сентября 2026 года сравнил три юридические записи CMS, активный снимок контента и все шесть публичных HTML DE/EN. Они совпали. Немецкий AGB содержал Datenschutz, а немецкий Datenschutz — плейсхолдер хостинга. В английских юридических страницах и Impressum аналогичного несоответствия не наблюдалось. Это дефекты контента; обновление документации не сохраняет и не публикует их исправление.

Тот же аудит подтвердил активный управляемый релиз и тестовый проект в статусе черновика. Это наблюдения на конкретную дату, а не бессрочные утверждения о состоянии сервера.

### 5. Проверки, требующие эксплуатационной приёмки

Я требую явной проверки фактической SMTP-доставки и спама, настроек почтового домена, HTTPS/сертификата, прав и лимитов FastCGI, установленных версий зависимостей, текущих checksums файлов, восстановления резервной копии и каждого выпуска после изменений кода/контента.

Локальная имитация отказа не заменяет проверку реального пути доставки. Последовательные тесты сброса не доказывают все конкурентные сценарии MySQL. Обработанное исключение активации не гарантирует универсального восстановления после аварии машины. Юридическая корректность требует финальной проверки владельцем сайта или юристом.

### 6. Review только документации

Для документационного изменения я проверяю Markdown diff, ссылки и заголовки, структурное соответствие EN/RU, технические утверждения по исходникам и отсутствие credentials/приватных значений инфраструктуры. Файлы приложения и workflow должны остаться неизменными. В PR я указываю только реально повторённые целевые проверки runbook; исторические числа тестов приложения выше нельзя выдавать за новые результаты.

[Инструкция выпуска](NETCUP_RELEASE_RU.md) определяет доставку и восстановление. Документационный PR не устанавливает комплект, не меняет закреплённую production-ревизию, не запускает публикацию и не одобряет merge.
