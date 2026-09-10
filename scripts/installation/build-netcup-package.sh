#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
git_command=(git -c "safe.directory=${repository_root}" -C "${repository_root}")
output_root="${1:-${repository_root}/installation-artifacts}"
mkdir -p "${output_root}"
output_root="$(cd "${output_root}" && pwd)"

stage_root="$(mktemp -d)"
trap 'rm -rf "${stage_root}"' EXIT
package_root="${stage_root}/madlen-install"
mkdir -p "${package_root}"

is_excluded() {
    case "$1" in
        installation-artifacts/*|public/design-reference/*|public/images/start_seite.jpeg|public/start_seite.jpeg|public/images/Kukes1.jpg|\
        "public/images/Grafik Elemente/Blaues_Element_Wolke.png"|\
        "public/images/Grafik Elemente/Linie_Blau_Klein.png"|\
        "public/images/Grafik Elemente/Linine_Blau_Gross.png"|\
        "public/images/Grafik Elemente/Rosa_Blau_Linie.png") return 0 ;;
        *) return 1 ;;
    esac
}

copy_candidate_file() {
    local relative_path="$1"
    is_excluded "${relative_path}" && return 0
    [[ -f "${repository_root}/${relative_path}" ]] || return 0
    [[ ! -L "${repository_root}/${relative_path}" ]] || {
        echo "Refusing source symlink: ${relative_path}" >&2
        exit 1
    }
    mkdir -p "${package_root}/$(dirname "${relative_path}")"
    cp -p "${repository_root}/${relative_path}" "${package_root}/${relative_path}"
}

while IFS= read -r -d '' relative_path; do
    copy_candidate_file "${relative_path}"
done < <("${git_command[@]}" ls-files --cached --others --exclude-standard -z)

base_revision="$("${git_command[@]}" rev-parse HEAD)"
created_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
source_diff_checksum="$({
    "${git_command[@]}" diff --binary HEAD --
    while IFS= read -r -d '' relative_path; do
        is_excluded "${relative_path}" && continue
        [[ -f "${repository_root}/${relative_path}" ]] || continue
        printf 'UNTRACKED %s ' "${relative_path}"
        sha256sum "${repository_root}/${relative_path}" | awk '{print $1}'
    done < <("${git_command[@]}" ls-files --others --exclude-standard -z)
} | sha256sum | awk '{print $1}')"

if "${git_command[@]}" diff --quiet HEAD -- &&
   [[ -z "$("${git_command[@]}" ls-files --others --exclude-standard | while IFS= read -r item; do is_excluded "${item}" || printf x; done)" ]]; then
    source_state="committed"
else
    source_state="local-candidate-with-uncommitted-changes"
fi

temporary_key='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
(
    cd "${package_root}/backend"
    APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY="${temporary_key}" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    composer install \
        --no-dev \
        --prefer-dist \
        --classmap-authoritative \
        --no-interaction \
        --no-progress
    APP_ENV=production APP_DEBUG=false APP_KEY="${temporary_key}" php artisan filament:assets
)

find "${package_root}/backend/storage" -type f ! -name '.gitignore' -delete
find "${package_root}/backend/bootstrap/cache" -type f ! -name '.gitignore' -delete
rm -f "${package_root}/backend/.env" "${package_root}/backend/.phpunit.result.cache"

cat > "${package_root}/.madlen-install.json" <<JSON
{
  "schemaVersion": 1,
  "purpose": "Netcup installation candidate",
  "baseRevision": "${base_revision}",
  "sourceState": "${source_state}",
  "sourceDiffSha256": "${source_diff_checksum}",
  "createdAt": "${created_at}",
  "containsProductionVendor": true,
  "containsSecrets": false,
  "runnerSourcePin": null
}
JSON

(
    cd "${package_root}"
    find . -type f ! -name '.madlen-install-files.sha256' -print0 \
        | sort -z \
        | xargs -0 sha256sum \
        > .madlen-install-files.sha256
)

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
archive_name="madlen-netcup-install-${stamp}-${base_revision:0:12}.tar.gz"
archive_path="${output_root}/${archive_name}"
tar -czf "${archive_path}" -C "${stage_root}" madlen-install
(
    cd "${output_root}"
    sha256sum "${archive_name}" > "${archive_name}.sha256"
)
cp "${package_root}/.madlen-install.json" "${archive_path}.source.json"
cp "${package_root}/.madlen-install-files.sha256" "${archive_path}.files.sha256"

printf 'ARCHIVE=%s\n' "${archive_path}"
printf 'ARCHIVE_SHA256=%s\n' "$(sha256sum "${archive_path}" | awk '{print $1}')"
printf 'BASE_REVISION=%s\n' "${base_revision}"
printf 'SOURCE_STATE=%s\n' "${source_state}"
printf 'SOURCE_DIFF_SHA256=%s\n' "${source_diff_checksum}"
