#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
output_root="${1:?Usage: build-netcup-preview-overlay.sh OUTPUT_DIR ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY}"
original_archive="${2:?Usage: build-netcup-preview-overlay.sh OUTPUT_DIR ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY}"
compatibility_overlay="${3:?Usage: build-netcup-preview-overlay.sh OUTPUT_DIR ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY}"
expected_base_revision="ef2e3f4fe09cc0adbc9801624852e5453ba4f2e8"
required_overlay_id="netcup-env-helper-fix-20260910033355-ef2e3f4fe09c-no-awk"

for command_name in git tar sha256sum cp mkdir chmod find sort xargs php date basename dirname mktemp rm; do
    command -v "${command_name}" >/dev/null 2>&1 || {
        echo "Missing build command: ${command_name}" >&2
        exit 1
    }
done

[[ -f "${original_archive}" && -f "${original_archive}.sha256" ]] || {
    echo "Original archive or its checksum is missing." >&2
    exit 1
}
[[ -f "${compatibility_overlay}" ]] || {
    echo "Compatibility overlay is missing." >&2
    exit 1
}
(cd "$(dirname "${original_archive}")" && sha256sum -c "$(basename "${original_archive}").sha256" >/dev/null)

source_revision="$(git -C "${repository_root}" rev-parse HEAD)"
[[ "${source_revision}" =~ ^[0-9a-f]{40}$ ]] || exit 1
[[ "${source_revision}" != "${expected_base_revision}" ]] || {
    echo "Refusing to label current preview fixes with the old base revision." >&2
    exit 1
}
[[ -z "$(git -C "${repository_root}" status --porcelain --untracked-files=normal)" ]] || {
    echo "Commit and review the preview changes before building the server overlay." >&2
    exit 1
}

files=(
    backend/app/Services/AstroBuildService.php
    backend/app/Services/PreviewBuilder.php
    backend/app/Services/ExternalPreviewBuilder.php
    backend/app/Services/ExternalPreviewPackager.php
    backend/app/Services/ExternalPreviewStorage.php
    backend/app/Services/ExternalPreviewWorkflowDispatcher.php
    backend/app/Services/ExternalPreviewStatus.php
    backend/app/Services/ExternalPreviewResultImporter.php
    backend/app/Services/PreviewCleanupService.php
    backend/app/Http/Controllers/ExternalPreviewRunnerController.php
    backend/app/Http/Controllers/PreviewController.php
    backend/app/Filament/Resources/Projects/Pages/EditProject.php
    backend/app/Filament/Resources/Releases/Pages/ListReleases.php
)

for relative in "${files[@]}"; do
    [[ -f "${repository_root}/${relative}" && ! -L "${repository_root}/${relative}" ]] || {
        echo "Overlay source is missing or is a symlink: ${relative}" >&2
        exit 1
    }
done

mkdir -p "${output_root}"
stage_root="$(mktemp -d)"
cleanup() { rm -rf -- "${stage_root}"; }
trap cleanup EXIT HUP INT TERM

tar -xzf "${original_archive}" -C "${stage_root}"
candidate="${stage_root}/madlen-install"
[[ -d "${candidate}" && ! -L "${candidate}" ]] || exit 1
base_revision="$(php -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["baseRevision"]??"";' "${candidate}/.madlen-install.json")"
[[ "${base_revision}" == "${expected_base_revision}" ]] || {
    echo "Original package has an unexpected base revision." >&2
    exit 1
}

tar -xzf "${compatibility_overlay}" -C "${stage_root}"
compatibility_root="${stage_root}/madlen-env-helper-overlay"
compatibility_id="$(php -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["overlayId"]??"";' "${compatibility_root}/metadata.json")"
[[ "${compatibility_id}" == "${required_overlay_id}" ]] || {
    echo "Unexpected compatibility overlay." >&2
    exit 1
}
PHP_BIN="$(command -v php)" bash "${compatibility_root}/apply-overlay.sh" "${candidate}" >/dev/null

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
overlay_id="netcup-preview-runtime-${stamp}-${source_revision:0:12}"
package_root="${stage_root}/madlen-preview-runtime-overlay"
mkdir -p "${package_root}/files"

: > "${package_root}/original-files.sha256"
for relative in "${files[@]}"; do
    old_hash="$(sha256sum "${candidate}/${relative}")"
    old_hash="${old_hash%% *}"
    printf '%s  ./%s\n' "${old_hash}" "${relative}" >> "${package_root}/original-files.sha256"
    mkdir -p "${package_root}/files/$(dirname "${relative}")"
    cp -p "${repository_root}/${relative}" "${package_root}/files/${relative}"
done

cp -p "${repository_root}/scripts/installation/apply-netcup-preview-overlay.sh" "${package_root}/apply-overlay.sh"
chmod 0755 "${package_root}/apply-overlay.sh"
original_archive_sha="$(sha256sum "${original_archive}")"
original_archive_sha="${original_archive_sha%% *}"
compatibility_overlay_sha="$(sha256sum "${compatibility_overlay}")"
compatibility_overlay_sha="${compatibility_overlay_sha%% *}"

cat > "${package_root}/metadata.json" <<JSON
{
  "schemaVersion": 1,
  "purpose": "Runtime patch for the installed Madlen external preview",
  "overlayId": "${overlay_id}",
  "baseRevision": "${expected_base_revision}",
  "sourceRevision": "${source_revision}",
  "requiredOverlayId": "${required_overlay_id}",
  "originalArchive": "$(basename "${original_archive}")",
  "originalArchiveSha256": "${original_archive_sha}",
  "compatibilityOverlay": "$(basename "${compatibility_overlay}")",
  "compatibilityOverlaySha256": "${compatibility_overlay_sha}",
  "containsSecrets": false,
  "containsDatabaseOrMedia": false,
  "requiresComposerAutoloadRefresh": false
}
JSON

(
    cd "${package_root}"
    find . -type f ! -name overlay-files.sha256 -print0 \
        | sort -z \
        | xargs -0 sha256sum \
        > overlay-files.sha256
)

archive_name="madlen-netcup-preview-runtime-overlay-${stamp}-${source_revision:0:12}.tar.gz"
archive_path="${output_root}/${archive_name}"
[[ ! -e "${archive_path}" ]] || {
    echo "Overlay archive already exists." >&2
    exit 1
}
tar -czf "${archive_path}" -C "${stage_root}" madlen-preview-runtime-overlay
archive_sha="$(sha256sum "${archive_path}")"
archive_sha="${archive_sha%% *}"
printf '%s  %s\n' "${archive_sha}" "${archive_name}" > "${archive_path}.sha256"
cp "${package_root}/metadata.json" "${archive_path}.metadata.json"
cp "${package_root}/overlay-files.sha256" "${archive_path}.files.sha256"

printf 'OVERLAY=%s\nSHA256=%s\nSOURCE_REVISION=%s\n' "${archive_path}" "${archive_sha}" "${source_revision}"
