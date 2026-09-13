# Madlen production publisher

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. My release model

I am Gleb Medvedovskyy. I designed and manage a controlled publication process rather than direct editing of public files. The implementation uses AI-assisted development and testing; I distinguish its safeguards from the operational checks required for each release.

The CMS uses `ProductionPublisher` for external production requests. GitHub Actions builds Astro from the pinned source revision; Netcup validates and activates the result. The legacy local npm publisher is not the hosting workflow. Normal content changes are saved in the CMS, while code/workflows are reviewed in GitHub.

### 2. A deliberate publication

I review both languages, media, shared saved content and excluded drafts before requesting a release. Project **Veröffentlichen** saves the current form and includes that selected project. **Website produktiv veröffentlichen** uses saved public content and does not publish unrelated drafts.

The request binds an operation, immutable snapshot and source revision. The external runner claims it, builds, packages and delivers the result. The backend checks identity, checksums, allowed paths and release ordering before activating `current`. I then verify the job's active state and the public DE/EN website.

### 3. Lost responses and new work

I inspect **Produktiv-Aufträge** before retrying. An unconfirmed dispatch may already have started; repeating clicks can create ambiguity.

The original request identity is retained for a lost response. After an acknowledged project-publication response, the editor receives a new identity for the next deliberate publication, including new content. Site-wide publication retains its identity for that list-page lifetime: only reopen it for a new operation after the previous outcome is known.

Technical retry reuses the existing immutable package/job and requires confirmation that the previous runner stopped. A fresh build can produce a different output ZIP because build metadata changes; the new ZIP checksum must still be verified. I do not edit stored checksums or remove release directories to force acceptance.

### 4. Failure and rollback

Before activation, a failed build or delivery leaves the old site available. A handled CMS-transaction failure after switching triggers compensation. Recovery permits replacement only of the identified latest inactive compensated release, after validating the new result; the failed directory is retained. Active, older or rolled-back releases remain protected.

For unresolved crash/timeout states, I compare the job, runner, release metadata and actual pointer before proceeding. I do not promise universal database/filesystem atomicity under a machine failure.

Production rollback selects a verified previous managed release with no jobs in flight and reconciles associated project lifecycle state. It does not overwrite newer text or media. CMS revision restoration, project restoration and database recovery are separate actions.

### 5. Operational references

I follow the [release procedure](NETCUP_RELEASE_RU.md) for preflight, code updates, backups, retry and rollback. The [CMS guide](../ADMIN_CMS.md) covers editor actions; the [verification record](RELEASE_VERIFICATION_RU.md) identifies actual test coverage and limitations.

A configured production connection is not a guarantee of ongoing external-service availability. I change release gates only in an agreed operational window; reading this runbook does not itself authorise a deployment.

---

<a id="russian"></a>
## Русский

### 1. Моя модель выпуска

Я — Gleb Medvedovskyy. Я спроектировал и управляю контролируемой публикацией вместо прямого редактирования публичных файлов. Реализация использует AI-assisted разработку и тестирование; я разделяю предусмотренные защиты и эксплуатационные проверки каждого выпуска.

CMS использует `ProductionPublisher` для внешних production-запросов. GitHub Actions собирает Astro из закреплённой ревизии; Netcup проверяет и активирует результат. Старый локальный npm publisher не является процессом хостинга. Обычный контент сохраняется в CMS, а код/workflow проходят review в GitHub.

### 2. Осознанная публикация

До запроса выпуска я проверяю оба языка, медиа, общий сохранённый контент и исключённые черновики. **Veröffentlichen** проекта сохраняет текущую форму и включает именно этот проект. **Website produktiv veröffentlichen** использует сохранённый публичный контент и не публикует посторонние черновики.

Запрос связывает операцию, неизменяемый снимок и ревизию исходников. Внешний runner принимает задание, собирает, упаковывает и доставляет результат. До активации `current` backend проверяет идентичность, checksums, допустимые пути и порядок релизов. Затем я проверяю активный статус задания и публичный сайт DE/EN.

### 3. Потерянные ответы и новые изменения

Перед повтором я проверяю **Produktiv-Aufträge**. Неподтверждённая отправка уже могла запустить работу; повторные нажатия создают неопределённость.

При потерянном ответе сохраняется исходная идентичность запроса. После подтверждённого ответа публикации проекта редактор получает новую идентичность для следующей осознанной публикации с новым контентом. Общая публикация сохраняет идентичность на время жизни страницы списка: открывайте её заново для новой операции только после выяснения предыдущего результата.

Технический retry повторно использует существующий неизменяемый пакет/задание и требует подтверждения остановки прежнего runner. Новая сборка может дать другой итоговый ZIP из-за изменения метаданных сборки; checksum нового ZIP всё равно проверяется. Я не редактирую сохранённые checksums и не удаляю каталоги релизов для принудительного принятия.

### 4. Ошибка и откат

До активации ошибка сборки или доставки оставляет прежний сайт доступным. Обработанная ошибка транзакции CMS после переключения вызывает компенсацию. Восстановление разрешает заменить только установленный последний неактивный компенсированный релиз после проверки нового результата; ошибочный каталог сохраняется. Активные, старые и отменённые откатом релизы остаются защищёнными.

При неопределённой аварии или timeout я сравниваю задание, runner, метаданные релиза и фактический указатель. Я не обещаю универсальную атомарность базы и файловой системы при аварии машины.

Production rollback выбирает проверенный предыдущий управляемый релиз при отсутствии текущих заданий и согласует связанные статусы проектов. Более новые тексты и медиа не перезаписываются. Восстановление ревизии CMS, проекта и базы — отдельные действия.

### 5. Эксплуатационные документы

Для preflight, обновления кода, резервных копий, retry и rollback я следую [инструкции выпуска](NETCUP_RELEASE_RU.md). [Руководство CMS](../ADMIN_CMS.md) описывает действия редактора; [отчёт о проверках](RELEASE_VERIFICATION_RU.md) — фактическое покрытие и ограничения.

Настроенное production-подключение не гарантирует постоянную доступность внешних сервисов. Я меняю разрешение выпусков только в согласованное эксплуатационное окно; само чтение этого руководства не разрешает развёртывание.
