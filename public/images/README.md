# Madlen image assets

[English](#english) · [Русский](#russian)

<a id="english"></a>
## English

### 1. Static assets and CMS media

I maintain this directory for versioned frontend assets: branding, decorative graphics and baseline media referenced by the Astro source. A file here is publicly deliverable unless an explicit build rule excludes it. I never store credentials, private originals, backups or internal documents here.

For ordinary portfolio updates, I use **Medien** in the CMS and assign uploaded images under **Projekte**. I do not replace source arrays or upload files directly into an active static release. Gallery order is the saved project order, not filename or upload order.

### 2. Image preparation

I preserve the original composition and prepare readable DE/EN alternative text. The CMS accepts JPEG, PNG and WebP images and creates web derivatives while retaining uploaded originals. Actual server limits may be stricter than application defaults. Videos require a supported, prepared MP4/WebM file; the CMS does not transcode them automatically.

Cover and gallery are separate assignments. Homepage video, poster and Memories photograph are separate slots. I verify both languages and mobile layouts in preview before publication; replacing a file does not automatically replace all assignments or generate a poster.

### 3. Source references and rights

An asset at `public/images/example.webp` is referenced as `/images/example.webp`, without `/public`. Existing filenames and references remain the source of truth; this example does not prescribe a new directory structure.

The software project's authorship does not transfer rights to photographs, videos, logos or supplied artwork. I use only assets cleared for their intended publication. See the [CMS guide](../../ADMIN_CMS.md) for editorial operations.

---

<a id="russian"></a>
## Русский

### 1. Статические ресурсы и медиа CMS

Я использую этот каталог для версионируемых ресурсов frontend: фирменной графики, декоративных элементов и исходных медиа, на которые ссылается код Astro. Файл здесь может быть опубликован, если его явно не исключает правило сборки. Я не храню здесь credentials, приватные оригиналы, резервные копии или внутренние документы.

Для обычного обновления портфолио я использую **Medien** в CMS и назначаю загруженные изображения в **Projekte**. Я не заменяю массивы исходников и не загружаю файлы непосредственно в активный статический релиз. Порядок галереи определяется сохранённым порядком проекта, а не именами файлов или последовательностью загрузки.

### 2. Подготовка изображений

Я сохраняю исходную композицию и подготавливаю понятные альтернативные тексты DE/EN. CMS принимает JPEG, PNG и WebP и создаёт web-производные, сохраняя загруженные оригиналы. Реальные ограничения сервера могут быть строже стандартных лимитов приложения. Для видео нужен подготовленный поддерживаемый MP4/WebM; CMS не перекодирует его автоматически.

Обложка и галерея назначаются отдельно. Видео главной страницы, постер и фотография Memories — отдельные слоты. Перед публикацией я проверяю обе языковые версии и мобильную раскладку в preview; замена файла не переназначает автоматически все связи и не создаёт постер.

### 3. Ссылки на ресурсы и права

Файл `public/images/example.webp` подключается как `/images/example.webp`, без `/public`. Источник истины — существующие имена и ссылки; этот пример не задаёт новую структуру каталогов.

Авторство программного проекта не передаёт права на фотографии, видео, логотипы или предоставленную графику. Я использую только ресурсы, разрешённые для соответствующей публикации. Редакционные операции описаны в [руководстве CMS](../../ADMIN_CMS.md).
