#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
git_command=(git -c "safe.directory=${repository_root}" -C "${repository_root}")
output_root="${1:-${repository_root}/installation-artifacts}"
mkdir -p "${output_root}"
output_root="$(cd "${output_root}" && pwd)"

base_revision="$("${git_command[@]}" rev-parse HEAD)"
expected_revision="ef2e3f4fe09cc0adbc9801624852e5453ba4f2e8"
original_archive="madlen-netcup-install-20260910T021858Z-ef2e3f4fe09c.tar.gz"
original_archive_sha256="bd6d8fabec58a7d1286d27015a11b5d3bd8dbc2c2cbc55ab10d64d2f8b5bea6e"

[[ "${base_revision}" == "${expected_revision}" ]] || {
    echo "Refusing to build against unexpected base revision: ${base_revision}" >&2
    exit 1
}

relative_paths=(
    backend/.env.example
    backend/app/Support/HostingPathResolver.php
    backend/config/filesystems.php
    backend/config/madlen.php
    docs/NETCUP_INSTALLATION_RU.md
    scripts/installation/netcup-fpm-path-probe.php
    scripts/installation/write-private-env.php
)

for relative_path in "${relative_paths[@]}"; do
    [[ -f "${repository_root}/${relative_path}" && ! -L "${repository_root}/${relative_path}" ]] || {
        echo "Overlay source is missing or is a symlink: ${relative_path}" >&2
        exit 1
    }
done

stage_root="$(mktemp -d)"
trap 'rm -rf "${stage_root}"' EXIT
package_root="${stage_root}/madlen-env-helper-overlay"
mkdir -p "${package_root}/files"

for relative_path in "${relative_paths[@]}"; do
    mkdir -p "${package_root}/files/$(dirname "${relative_path}")"
    cp -p "${repository_root}/${relative_path}" "${package_root}/files/${relative_path}"
done

cp -p "${repository_root}/scripts/installation/apply-netcup-env-helper-overlay.sh" "${package_root}/apply-overlay.sh"
chmod 0755 "${package_root}/apply-overlay.sh"

cat > "${package_root}/original-files.sha256" <<'MANIFEST'
3462793dd73d3043b46947d539e448a7e99e29e6b32f01c632bd3adc31808dd9  ./backend/.env.example
MISSING  ./backend/app/Support/HostingPathResolver.php
029c4e6e574cfd70d4db0d3a2c3339a361d3307b3a3f7ded8f5e1eb45bb03c2b  ./backend/config/filesystems.php
0db3b6e6b3e0af2ab14dca2dfcc412eb880eef5b55c77ec9f27de2c2de64f421  ./backend/config/madlen.php
fca3ad4817f9f20725af0f33c0fb335b86de529164bc24ea7e2ea9f76eab8839  ./docs/NETCUP_INSTALLATION_RU.md
MISSING  ./scripts/installation/netcup-fpm-path-probe.php
3ff3c3993af286cf6e4a2739b72dfc3f9f1645990f61794c536d316f312d615c  ./scripts/installation/write-private-env.php
MANIFEST

created_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
overlay_id="netcup-env-helper-fix-$(date -u +%Y%m%d%H%M%S)-${base_revision:0:12}"
source_files_sha256="$({
    for relative_path in "${relative_paths[@]}"; do
        printf '%s  %s\n' "$(sha256sum "${repository_root}/${relative_path}" | awk '{print $1}')" "${relative_path}"
    done
    sha256sum "${repository_root}/scripts/installation/apply-netcup-env-helper-overlay.sh"
} | sha256sum | awk '{print $1}')"

cat > "${package_root}/metadata.json" <<JSON
{
  "schemaVersion": 1,
  "purpose": "Compatibility overlay for the already extracted Netcup app candidate",
  "overlayId": "${overlay_id}",
  "baseRevision": "${base_revision}",
  "sourceFilesSha256": "${source_files_sha256}",
  "createdAt": "${created_at}",
  "originalArchive": "${original_archive}",
  "originalArchiveSha256": "${original_archive_sha256}",
  "containsSecrets": false,
  "changesOriginalInstallManifest": false
}
JSON

(
    cd "${package_root}"
    find . -type f ! -name 'overlay-files.sha256' -print0 \
        | sort -z \
        | xargs -0 sha256sum \
        > overlay-files.sha256
)

archive_name="madlen-netcup-env-helper-overlay-${stamp}-${base_revision:0:12}.tar.gz"
archive_path="${output_root}/${archive_name}"
tar -czf "${archive_path}" -C "${stage_root}" madlen-env-helper-overlay

(
    cd "${output_root}"
    sha256sum "${archive_name}" > "${archive_name}.sha256"
)

cp "${package_root}/metadata.json" "${archive_path}.metadata.json"
cp "${package_root}/overlay-files.sha256" "${archive_path}.files.sha256"

printf 'OVERLAY=%s\n' "${archive_path}"
printf 'OVERLAY_SHA256=%s\n' "$(sha256sum "${archive_path}" | awk '{print $1}')"
printf 'OVERLAY_ID=%s\n' "${overlay_id}"
printf 'BASE_REVISION=%s\n' "${base_revision}"
printf 'ORIGINAL_ARCHIVE=%s\n' "${original_archive}"
printf 'ORIGINAL_ARCHIVE_SHA256=%s\n' "${original_archive_sha256}"
printf 'SOURCE_FILES_SHA256=%s\n' "${source_files_sha256}"
