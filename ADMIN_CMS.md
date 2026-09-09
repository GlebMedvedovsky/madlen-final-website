# Madlen CMS — local setup and operations

The CMS is a German-only Filament 5 interface backed by Laravel 12 and MySQL 8.4. Website content remains bilingual (DE/EN). The public Astro site is still static: saving a draft never changes the active site; only a successful release build and atomic promotion does.

## Local addresses

- Admin login: <http://localhost:8088/admin/login>
- Locally published website: <http://localhost:8089>
- Astro development server (optional): <http://localhost:4321>

Both CMS-facing ports bind to `127.0.0.1`. The database has no host port.

## First setup

Run these commands from the repository root in PowerShell. They are explicitly scoped to Compose project `madlen`.

```powershell
Copy-Item madlen.env.example .env
Copy-Item backend/.env.example backend/.env
docker compose --project-name madlen build app runtime-init
docker compose --project-name madlen run --rm --no-deps --user root app composer install --no-interaction
docker compose --project-name madlen run --rm --no-deps --user root --workdir /workspace app npm ci
docker compose --project-name madlen run --rm --no-deps --user root app php artisan key:generate
docker compose --project-name madlen up -d db app web site
docker compose --project-name madlen exec app php artisan migrate --force
docker compose --project-name madlen exec app php artisan madlen:import
```

Replace all placeholder passwords in the root `.env` before first startup. Neither `.env` file is committed.

Create or reset the single administrator interactively; the password is never accepted as a command-line argument and must contain at least 12 characters:

```powershell
docker compose --project-name madlen exec app php artisan madlen:admin madlen@example.com --name=Madlen
```

Public registration is disabled. Email delivery is intentionally not configured. Until production mail prerequisites are approved, password recovery is the same secure interactive command with the existing email address.

## Daily operation

```powershell
# Start without recreating or deleting data
docker compose --project-name madlen up -d db app web site

# Show real counts and active release
docker compose --project-name madlen exec app php artisan madlen:status

# Non-destructive stop; volumes remain
docker compose --project-name madlen stop
```

Never use `down -v` for normal operation. Madlen uses only `madlen_internal` and volumes prefixed `madlen_`; it does not use MediaShop resources.

### Durable runtime permissions

`runtime-init` is a short, project-scoped initialization service. It runs as root only long enough to create and repair ownership/modes on `backend/storage`, `backend/bootstrap/cache`, `.astro`, and the dedicated Madlen media/release volumes. The long-running PHP-FPM service and ordinary `docker compose ... exec app php artisan ...` commands run as `www-data`, so web previews and CLI publication create compatible files. The public Nginx containers retain read-only access to completed releases.

No recurring `chmod`, `chown`, `777`, or root Artisan command is required. After upgrading an older local checkout that predates `runtime-init`, apply the one-time service recreation below; it does not recreate the database or delete any volume:

```powershell
docker compose --project-name madlen up -d --force-recreate runtime-init app
docker compose --project-name madlen exec app id
docker compose --project-name madlen exec app php artisan madlen:status
```

The first command touches only Madlen's initialization and application services. Do not run it with project name `medyshop` and do not add `down -v` or `prune`.

The baseline import is idempotent. It creates missing records by stable source ID and does not overwrite later CMS edits:

```powershell
docker compose --project-name madlen exec app php artisan madlen:import
```

## Editing and publication

1. In **Projekte**, open a project or choose **Neu**. Its URL slug stays stable after creation.
2. Use **Deutsche Inhalte** and **Englische Inhalte**. These tabs change website content fields, not the German admin language.
3. Upload files under **Medien**. Choose title/gallery images in the project. Use drag-and-drop or the accessible up/down buttons to reorder; retain left/right placement.
4. Save. New projects remain **Entwurf** and the active public release is unchanged.
5. Choose **Vorschau**. The actual Astro templates are built under a time-limited, authenticated URL. Preview HTML and draft media return 404 without the matching logged-in admin.
6. Choose **Veröffentlichen** only when required DE and EN project fields are complete. Success is reported only after a real Astro production build, route validation and atomic promotion.
7. **Nicht mehr veröffentlichen** excludes a project from the next release. The trash is reversible through the table filter and **Wiederherstellen**.

Homepage video, poster and Memories photo are selected under **Startseiten-Medien** after uploading a prepared file under **Medien**.

Imported images and uploaded WebP derivatives are shown in the admin through an authenticated media route. The route never exposes private image originals; draft-only derivatives remain unavailable without an authorized admin session. Project and homepage selectors include a thumbnail (or a video preview) and an explicit selection/edit action.

The English services page intentionally has six blocks while the German page has seven: German **Videografie** and **Videoschnitt** map to the single English block **Videography & Editing**. In the service list, Videoschnitt is therefore marked **Zusammengefasst**, not as a missing translation. Running `madlen:import` fills only missing/known-placeholder English values from checkpoint `f44a51b`; nonempty later edits are preserved and repeated runs do not duplicate records.

Under **Seiten & Texte**, supported singleton areas use German-labelled DE/EN fields and repeaters. JSON keys and technical identifiers are not editable. Unknown nested values are retained when a known field is saved, and changing one language leaves the other language byte-for-byte unchanged when it was not edited. Settings are likewise limited to **Hauptsprache**, **Kontakt-E-Mail**, and **Website-Adresse**.

CLI equivalents:

```powershell
docker compose --project-name madlen exec app php artisan madlen:preview madlen@example.com
docker compose --project-name madlen exec app php artisan madlen:publish
docker compose --project-name madlen exec app php artisan madlen:rollback 3
docker compose --project-name madlen exec app php artisan madlen:release:export
```

The last command creates an immutable static archive and SHA-256 file only. `MADLEN_PRODUCTION_PUBLISHER=unconfigured` is deliberate: no Netcup upload or public deployment occurs.

## Prepared production publishing boundary (not connected)

### Audit result and selected architecture

The pre-existing implementation already provided local draft previews, immutable content manifests, real Astro builds, public-media selection, route validation, an atomic local `current` symlink, rollback, backup, and export of a completed local release. Production publishing itself was missing both an implementation and target configuration; the previous workflow document contained two intentional `exit 1` placeholders.

The smallest approach compatible with the currently advertised **Netcup Webhosting 2000** capabilities is:

1. Laravel/Filament runs as the PHP application on Netcup, with a new database/user dedicated only to Madlen and private persistent media outside every public document root.
2. A production click snapshots published database content and copies only referenced public derivatives/selected public media into a private immutable ZIP. Draft-only projects, unrelated originals, backend source, environment files and database data are not included.
3. The CMS uses a server-side, narrowly scoped token to dispatch a GitHub Actions workflow. The owner computer and local Docker are not involved.
4. The runner downloads that exact package through the bearer-protected CMS endpoint, checks SHA-256 and package identifiers, checks out the exact 40-character application commit, runs Astro with Node 22, and validates both languages, every project route, the sitemap, and referenced `/media/` files.
5. The runner transfers one checksummed static ZIP into a dedicated Madlen incoming directory over SSH/SFTP. A PHP Artisan command on Netcup extracts it into a new versioned directory, validates it again, rejects duplicate/older sequence numbers, and atomically changes only the Madlen `current` symlink.
6. Only after target activation succeeds does the runner report `active` to the CMS. German status and failure messages are retained in `production_publications`. A failed build, checksum, transfer, validation or switch leaves the previous `current` release in use.

This split is necessary because Netcup's current product page advertises SSH/PHP/cron for Webhosting 2000 but explicitly says Node.js features start with Webhosting 4000. Official Webhosting documentation also confirms PHP 8.4 shell paths, database management, secure SFTP/FTPES, scheduled tasks, configurable document roots and PHP extensions. These are general/current product statements, not proof of the purchased account's exact paths or limits:

- <https://www.netcup.com/en/hosting>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/php-shell>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/php-extensions>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/access-database>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/ftp-access>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/scheduled-tasks>
- <https://www.netcup.com/en/helpcenter/documentation/web-hosting/hosting-settings>

The prepared workflow is [`docs/production-publisher.example.yml`](docs/production-publisher.example.yml), not `.github/workflows/*`; it cannot run. `MADLEN_PRODUCTION_CONNECTED=false` and `MADLEN_PRODUCTION_PUBLISHER=unconfigured` keep the API, admin production action, and dispatch disabled. A local test adapter uses temporary directories only and must never be described as a live deployment.

### Prepared external private preview boundary (not connected)

Local development still uses `MADLEN_PREVIEW_EXECUTION=local`; the existing `PreviewBuilder` invokes Node directly and returns the completed authenticated preview as before. A separately selectable `external` mode is now prepared for a PHP host without Node.js:

1. The same preview action captures one immutable `includeDrafts=true` manifest, only its referenced media derivatives, a random preview token, expiry, checksum, and an exact compatible 40-character source revision. Editing the draft after this point does not alter that package and no content edit requires a Git commit.
2. A runner with a separate preview API token downloads only that job's package, verifies the identifiers/checksum/expiry, checks out the pinned source, and builds the DE and EN routes with Node. The result contains URLs scoped to `/admin/preview/{token}` and job metadata.
3. The runner transfers one checksummed ZIP to a preview-only private incoming directory. The CMS callback validates paths, ZIP entries, job/token/source metadata, DE/EN/project routes and referenced media, then atomically moves the result to that token's private build directory.
4. The existing authenticated preview route serves the returned HTML, scripts, styles and draft media with `private, no-store` and `noindex`. The route returns 404 to an unauthenticated user or another administrator. Waiting/building pages refresh automatically; failed and expired states are explained in German.
5. Duplicate callbacks with the same checksum are idempotent. A conflicting, wrong-job, failed or expired result cannot replace another preview or activate a public release. Hourly `madlen:previews:cleanup` removes only files whose token/job paths match their expired preview record.

The disabled runner example is [`docs/external-preview.example.yml`](docs/external-preview.example.yml), not `.github/workflows/*`; it cannot run. The protected endpoints are closed unless all three values are deliberately selected: `MADLEN_PREVIEW_EXECUTION=external`, `MADLEN_EXTERNAL_PREVIEW_CONNECTED=true`, and `MADLEN_EXTERNAL_PREVIEW_DRIVER=github-actions`. Defaults remain local/unconfigured/false.

### One-time backend installation/update versus routine publication

Backend installation or update is a separate operator procedure. It may install Composer dependencies, configure `backend/.env`, run reviewed migrations, prepare persistent directories and cache Laravel configuration. It must not be performed by a content-publication workflow.

Routine publication performs only: immutable content package → external static build → new versioned static directory → atomic pointer switch → status callback. It does **not** run Composer, deploy backend source, write `.env`, migrate/reseed/reset the database, replace private uploads, or touch another site.

The new production-publication and external-preview migrations exist locally but have not been run against the working MySQL database. During the later authorized backend update, and only after a database backup, the operator will run:

```sh
cd <NETCUP_MADLEN_BACKEND_ROOT>
<NETCUP_PHP_BIN> artisan migrate --force
<NETCUP_PHP_BIN> artisan config:cache
```

Prepare two separate Madlen-only directories once. Their marker files are mandatory safety stops used by the activator:

```sh
install -d -m 0750 <NETCUP_MADLEN_INCOMING_ROOT> <NETCUP_MADLEN_DESTINATION_ROOT>
printf '%s\n' 'madlen-production-incoming-v1' > <NETCUP_MADLEN_INCOMING_ROOT>/.madlen-publisher-incoming
printf '%s\n' 'madlen-production-root-v1' > <NETCUP_MADLEN_DESTINATION_ROOT>/.madlen-publisher-root
```

Prepare three additional, mutually separate private preview directories outside every document root. The markers are mandatory for packaging, result acceptance and cleanup:

```sh
install -d -m 0750 <NETCUP_MADLEN_PREVIEW_PACKAGE_ROOT> <NETCUP_MADLEN_PREVIEW_INCOMING_ROOT> <NETCUP_MADLEN_PREVIEW_RESULT_ROOT>
printf '%s\n' 'madlen-preview-packages-v1' > <NETCUP_MADLEN_PREVIEW_PACKAGE_ROOT>/.madlen-preview-packages
printf '%s\n' 'madlen-preview-incoming-v1' > <NETCUP_MADLEN_PREVIEW_INCOMING_ROOT>/.madlen-preview-incoming
printf '%s\n' 'madlen-preview-results-v1' > <NETCUP_MADLEN_PREVIEW_RESULT_ROOT>/.madlen-preview-results
```

The public site's document root will later point to `<NETCUP_MADLEN_DESTINATION_ROOT>/current`. The admin document root points separately to `<NETCUP_MADLEN_BACKEND_ROOT>/public`. Do not point either domain at the repository root, backend storage, package directory, incoming directory, originals, backups, or database exports.

### Placeholder backend configuration

Keep all values in `backend/.env` on the server; never commit or print them. Empty values are intentional until connection work is authorized.

```dotenv
MADLEN_PRODUCTION_PUBLISHER=github-actions
MADLEN_PRODUCTION_CONNECTED=false
MADLEN_PRODUCTION_PACKAGE_ROOT=<PRIVATE_ABSOLUTE_PACKAGE_PATH>
MADLEN_PRODUCTION_INCOMING_ROOT=<NETCUP_MADLEN_INCOMING_ROOT>
MADLEN_PRODUCTION_DESTINATION_ROOT=<NETCUP_MADLEN_DESTINATION_ROOT>
MADLEN_PRODUCTION_SOURCE_REVISION=<EXACT_40_CHARACTER_COMMIT>
MADLEN_PUBLISHER_API_TOKEN=<RANDOM_SHARED_RUNNER_TOKEN>
MADLEN_GITHUB_REPOSITORY=<OWNER/REPOSITORY>
MADLEN_GITHUB_WORKFLOW=madlen-production-publisher.yml
MADLEN_GITHUB_REF=main
MADLEN_GITHUB_TOKEN=<NARROW_WORKFLOW_DISPATCH_TOKEN>

MADLEN_PREVIEW_TTL=120
MADLEN_PREVIEW_EXECUTION=local
MADLEN_EXTERNAL_PREVIEW_CONNECTED=false
MADLEN_EXTERNAL_PREVIEW_DRIVER=unconfigured
MADLEN_PREVIEW_PACKAGE_ROOT=<PRIVATE_PREVIEW_PACKAGE_PATH>
MADLEN_PREVIEW_INCOMING_ROOT=<PRIVATE_PREVIEW_INCOMING_PATH>
MADLEN_PREVIEW_RESULT_ROOT=<PRIVATE_PREVIEW_RESULT_PATH>
MADLEN_PREVIEW_SOURCE_REVISION=<EXACT_40_CHARACTER_COMPATIBLE_COMMIT>
MADLEN_PREVIEW_API_TOKEN=<SEPARATE_RANDOM_PREVIEW_RUNNER_TOKEN>
MADLEN_PREVIEW_GITHUB_REPOSITORY=<OWNER/REPOSITORY>
MADLEN_PREVIEW_GITHUB_WORKFLOW=madlen-external-preview.yml
MADLEN_PREVIEW_GITHUB_REF=main
MADLEN_PREVIEW_GITHUB_TOKEN=<NARROW_PREVIEW_WORKFLOW_DISPATCH_TOKEN>
```

Keep `MADLEN_PRODUCTION_CONNECTED=false` until the migration, protected API, external workflow, SSH target, checksums, candidate validation, symlink test, failure test and rollback test have all succeeded on a non-public target. Setting the driver name alone must not make the admin report a connection.

Keep preview execution `local` and `MADLEN_EXTERNAL_PREVIEW_CONNECTED=false` until its migration, three private marked directories, protected API, runner workflow, expiry/cleanup schedule, wrong-job rejection and authenticated result serving have succeeded on the actual non-public host. Production publishing and external preview use different API/dispatch tokens and independent directories.

GitHub repository secrets needed later (or environment secrets if the private-repository plan supports them):

- `MADLEN_CMS_URL`, `MADLEN_PUBLISHER_API_TOKEN`, `MADLEN_PREVIEW_API_TOKEN`;
- `NETCUP_SSH_HOST`, `NETCUP_SSH_USER`, `NETCUP_SSH_PRIVATE_KEY`, pinned `NETCUP_SSH_HOST_KEY`.

GitHub repository variables needed later:

- `NETCUP_MADLEN_INCOMING_ROOT`, `NETCUP_MADLEN_PREVIEW_INCOMING_ROOT`, `NETCUP_MADLEN_BACKEND_ROOT`, `NETCUP_PHP_BIN` (the documented PHP 8.4 path is currently `/usr/local/php84/bin/php`, but verify it in the account).

Use a deploy key/account restricted to the Madlen directories wherever Netcup permits it. Do not use the hosting-control-panel login or an emailed verification code for routine publication. The CMS dispatch token needs only the single repository/workflow permission required for workflow dispatch; the workflow's repository permission remains `contents: read`. Verify whether the private repository's current GitHub plan supports protected environment secrets before choosing environments; do not purchase or silently assume a paid plan.

### Later connection sequence

1. Record and verify the account-specific facts listed below.
2. Review and commit these files through the normal source-control process; select that immutable commit as `MADLEN_PRODUCTION_SOURCE_REVISION`.
3. Back up the dedicated Madlen database/media, install or update the PHP backend once, then run the reviewed migration. Do not import or reseed over existing production content.
4. Create the production and preview private package/incoming/result/destination directories and their marker files. Verify the PHP application user can write only the intended Madlen paths.
5. In a throwaway Madlen-only destination, verify ZIP, file permissions, symlink creation and same-filesystem atomic `rename`; test failure and rollback before changing any public document root.
6. Copy the reviewed example into `.github/workflows/madlen-production-publisher.yml`, configure protected secrets/variables and a pinned SSH host key, and test against the non-public target.
7. Copy the reviewed preview example into `.github/workflows/madlen-external-preview.yml`, configure its separate token and private incoming path, and exercise package/build/return/auth/expiry against the non-public host. Keep the production switch off during this test.
8. Configure the two document roots, HTTPS/security settings and the host scheduler for `artisan schedule:run`. Only after each flow passes independently should its own connection switch be enabled and configuration cached.

The host-side activation and rollback commands, after connection, are:

```sh
<NETCUP_PHP_BIN> artisan madlen:production:activate PUBLICATION_UUID SEQUENCE ARCHIVE_BASENAME SHA256
<NETCUP_PHP_BIN> artisan madlen:production:rollback SEQUENCE-PUBLICATION_UUID
```

Both refuse to run while the publisher is unconnected. Rollback changes only the retained Madlen static pointer and deliberately keeps the highest processed sequence, so a delayed older job cannot undo the rollback. It does not alter content records, private uploads or backups.

### Local verification commands

These checks use SQLite `:memory:` and a random OS temporary directory. The application process runs as `www-data`; no local release/history row is written to the working MySQL database.

```powershell
docker compose --project-name madlen exec app id
docker compose --project-name madlen exec app php artisan test tests/Feature/ExternalPreviewPreparationTest.php --do-not-cache-result
docker compose --project-name madlen exec app php artisan test tests/Feature/ProductionPublisherPreparationTest.php --do-not-cache-result
docker compose --project-name madlen exec app php artisan test tests/Feature/PublicationPipelineTest.php --do-not-cache-result
docker compose --project-name madlen exec --workdir /workspace app npm run build
git diff --check
```

The production preparation test exercises: disabled-by-default behavior; draft exclusion; derivative-only packaging; package checksum; actual DE/EN Astro build; generated project routes and media; mocked workflow dispatch; protected runner download/status callbacks; successful isolated activation; simulated transfer failure retaining the old site; rejection of an older job; and rollback to a retained release.

The external preview test exercises: explicit disabled mode; immutable draft and derivative-only packaging; mocked dispatch; real DE/EN Astro build from the package; a draft edit during the build; private result return/import; authenticated German/English pages and a referenced draft image; unauthorized and wrong-owner rejection; normalized private cache headers; duplicate callback; wrong-job/out-of-order rejection; German waiting/building/ready/failed/expired states; scoped expiry cleanup; and an unchanged active public release. It uses SQLite `:memory:` plus a randomized OS temporary directory and creates no normal release/publication history.

## Media rules

- Images: actual JPEG, PNG or WebP content; default maximum 20 MiB and 12,000 px on the longest edge.
- Prepared video: MP4 or WebM; default maximum 50 MiB. The CMS does not transcode large video.
- Uploaded originals remain private and persistent. Images receive an immutable WebP derivative up to 2,400 px wide; JPEG orientation is applied. Originals are retained.
- Replacing a file creates a new immutable media URL. Shared/cover/homepage media cannot be trashed while still referenced.
- Public releases receive copies of only CMS-uploaded media used by that release. Draft media is not exposed through a public storage route.

Limits are configurable with `MADLEN_MAX_IMAGE_KB`, `MADLEN_MAX_VIDEO_KB`, `MADLEN_MAX_IMAGE_DIMENSION`, and `MADLEN_WEB_MAX_WIDTH`.

## Backup and isolated restore test

Backups coordinate a consistent MySQL dump, private media archive and checksums:

```powershell
docker compose --project-name madlen exec app php artisan madlen:backup
docker compose --project-name madlen --profile testing up -d db_test
docker compose --project-name madlen exec app php artisan madlen:backup:restore-test BACKUP_UUID
```

Restore is hard-stopped unless the destination database name ends in `_restore_test`. The `db_test` service uses its own `madlen_test_db_data` volume and no host port. A production restore is intentionally not automated in this version.

## Verification

```powershell
docker compose --project-name madlen exec app php artisan test
docker compose --project-name madlen exec --workdir /workspace app node scripts/test-madlen-assist.mjs
docker compose --project-name madlen exec --workdir /workspace app npm run build
git diff --check
```

Tests force `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` before Laravel boots, then verify the actual PDO driver. They abort rather than target the working MySQL database.

## Netcup account facts still required

No production installation or connection has been performed. Official documentation establishes general product support, but the following must be read from the purchased **Webhosting 2000 NUE** account or confirmed by Netcup before target configuration:

- exact product generation/location and current quotas: webspace, inode/file count, database count/size, cron count/frequency, process/runtime/memory limits, maximum file and HTTP response sizes;
- actual PHP web/CLI versions and enabled PDO MySQL, intl, GD WebP/JPEG/PNG, EXIF, mbstring, OpenSSL, fileinfo, ZIP and bcmath extensions;
- actual MySQL/MariaDB server/version and a new Madlen-only database/user; never reuse a database belonging to another project;
- absolute admin document root, static-site document root, private storage, production package/incoming/destination paths and preview package/incoming/result paths, all separated from one another and other sites;
- whether the account permits symlinks and atomic replacement of a symlink within the same filesystem; verify empirically in a throwaway directory;
- SSH hostname/port, host-key fingerprint, public-key authentication, SFTP/SCP support and the executable PHP 8.4 path; do not assume `rsync`;
- outbound HTTPS from PHP to `api.github.com`, and inbound HTTPS from GitHub Actions to the protected package/status endpoints, including any WAF/download-size/timeout restriction;
- HTTPS certificate, trusted proxy/session-cookie settings and the exact public/admin URLs;
- provider backup coverage and an independent backup destination. Netcup describes its ordinary restore backups as voluntary and not guaranteed, so they are not a substitute for the CMS backup procedure;
- scheduled-task availability and frequency for Laravel housekeeping, external-preview expiry cleanup and status reconciliation. Do not assume Docker, Node, Redis, websockets or a permanent queue worker on Webhosting 2000;
- GitHub Actions availability/minutes for the private repository, and whether protected environments are included in the current plan; repository secrets are the no-purchase fallback;
- SMTP/provider details and legal approval before enabling password-reset email, contact delivery, analytics or optional cookies.

Both the production publisher and protected external preview are prepared and locally tested, but neither is connected or production-verified. Until these account facts and both real runner/host paths are verified, the correct production status remains **Nicht konfiguriert** and preview execution remains **local**.
