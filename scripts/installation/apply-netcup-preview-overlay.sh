#!/usr/bin/env bash

set -euo pipefail

missing_commands=()
for overlay_command in sha256sum cp mkdir chmod dirname rm mv; do
    command -v "${overlay_command}" >/dev/null 2>&1 || missing_commands+=("${overlay_command}")
done
if (( ${#missing_commands[@]} > 0 )); then
    printf 'MISSING: %s\n' "${missing_commands[@]}" >&2
    exit 1
fi

overlay_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
target_root="${1:-/madebymadlen.de/app}"
php_bin="${PHP_BIN:-/usr/local/php84/bin/php}"
active_temp=""

cleanup() {
    if [[ -n "${active_temp}" && -e "${active_temp}" && ! -L "${active_temp}" ]]; then
        rm -f -- "${active_temp}"
    fi
}
trap cleanup EXIT HUP INT TERM

[[ -x "${php_bin}" ]] || { echo "Required PHP binary is unavailable: ${php_bin}" >&2; exit 1; }
[[ -d "${target_root}" && ! -L "${target_root}" ]] || {
    echo "Application root is missing or is a symlink: ${target_root}" >&2
    exit 1
}

cd "${overlay_root}"
sha256sum -c overlay-files.sha256 >/dev/null

read_metadata() {
    "${php_bin}" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $v=$m[$argv[2]]??""; if(!is_string($v)){exit(1);} echo $v;' metadata.json "$1"
}

overlay_id="$(read_metadata overlayId)"
base_revision="$(read_metadata baseRevision)"
source_revision="$(read_metadata sourceRevision)"
required_overlay_id="$(read_metadata requiredOverlayId)"
candidate_revision="$("${php_bin}" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["baseRevision"]??"";' "${target_root}/.madlen-install.json")"

[[ "${overlay_id}" =~ ^netcup-preview-runtime-[a-zA-Z0-9-]+$ \
    && "${source_revision}" =~ ^[0-9a-f]{40}$ \
    && "${required_overlay_id}" =~ ^[a-z0-9-]+$ \
    && "${candidate_revision}" == "${base_revision}" ]] || {
    echo "Application base revision or overlay metadata does not match." >&2
    exit 1
}
required_state="${target_root}/.madlen-overlays/${required_overlay_id}/state"
[[ -f "${required_state}" && ! -L "${required_state}" && "$(<"${required_state}")" == "APPLIED" ]] || {
    echo "The required Netcup compatibility overlay is not recorded as APPLIED." >&2
    exit 1
}
[[ -d "${target_root}/.madlen-overlays" && ! -L "${target_root}/.madlen-overlays" ]] || {
    echo "The application overlay record root is missing or unsafe." >&2
    exit 1
}

record_root="${target_root}/.madlen-overlays/${overlay_id}"
state_file="${record_root}/state"

is_allowed_path() {
    case "$1" in
        ./backend/app/Services/AstroBuildService.php|\
        ./backend/app/Services/PreviewBuilder.php|\
        ./backend/app/Services/ExternalPreviewBuilder.php|\
        ./backend/app/Services/ExternalPreviewPackager.php|\
        ./backend/app/Services/ExternalPreviewStorage.php|\
        ./backend/app/Services/ExternalPreviewWorkflowDispatcher.php|\
        ./backend/app/Services/ExternalPreviewStatus.php|\
        ./backend/app/Services/ExternalPreviewResultImporter.php|\
        ./backend/app/Services/PreviewCleanupService.php|\
        ./backend/app/Http/Controllers/ExternalPreviewRunnerController.php|\
        ./backend/app/Http/Controllers/PreviewController.php|\
        ./backend/app/Providers/AppServiceProvider.php|\
        ./backend/app/Filament/Resources/Projects/Pages/EditProject.php|\
        ./backend/app/Filament/Resources/Releases/Pages/ListReleases.php|\
        ./backend/config/madlen.php) return 0 ;;
        *) return 1 ;;
    esac
}

file_hash() {
    local output digest
    output="$(sha256sum -- "$1")" || return 1
    digest="${output%% *}"
    [[ "${digest}" =~ ^[0-9a-f]{64}$ ]] || return 1
    printf '%s\n' "${digest}"
}

expected_new_hash() {
    local relative="$1" digest manifest_path found=""
    while read -r digest manifest_path; do
        if [[ "${manifest_path}" == "./files/${relative#./}" ]]; then
            [[ "${digest}" =~ ^[0-9a-f]{64}$ && -z "${found}" ]] || return 1
            found="${digest}"
        fi
    done < "${overlay_root}/overlay-files.sha256"
    [[ -n "${found}" ]] || return 1
    printf '%s\n' "${found}"
}

verify_applied() {
    local expected relative destination actual
    while read -r expected relative; do
        [[ -n "${expected}" && -n "${relative}" ]] || continue
        is_allowed_path "${relative}" || return 1
        destination="${target_root}/${relative#./}"
        [[ -f "${destination}" && ! -L "${destination}" ]] || return 1
        actual="$(file_hash "${destination}")"
        [[ "${actual}" == "$(expected_new_hash "${relative}")" ]] || return 1
    done < "${overlay_root}/original-files.sha256"
}

if [[ -f "${state_file}" && "$(<"${state_file}")" == "APPLIED" ]]; then
    verify_applied || { echo "Overlay is APPLIED, but patched files no longer match." >&2; exit 1; }
    echo "Overlay ${overlay_id} is already applied and verified."
    exit 0
fi

resume=false
if [[ -f "${state_file}" && "$(<"${state_file}")" == "PREPARED" ]]; then
    resume=true
elif [[ -e "${record_root}" || -L "${record_root}" ]]; then
    echo "Overlay record exists in an unknown state: ${record_root}" >&2
    exit 1
fi

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    is_allowed_path "${relative}" || { echo "Disallowed overlay path: ${relative}" >&2; exit 1; }
    [[ "${expected}" =~ ^[0-9a-f]{64}$ ]] || { echo "Invalid expected checksum: ${relative}" >&2; exit 1; }
    destination="${target_root}/${relative#./}"
    new_hash="$(expected_new_hash "${relative}")"
    [[ ! -L "${destination}" && -f "${destination}" ]] || {
        echo "Expected application file is missing or is a symlink: ${relative}" >&2
        exit 1
    }
    current_hash="$(file_hash "${destination}")"
    if [[ "${current_hash}" != "${expected}" && ( "${resume}" != true || "${current_hash}" != "${new_hash}" ) ]]; then
        echo "Original checksum mismatch; application was not modified: ${relative}" >&2
        exit 1
    fi
done < "${overlay_root}/original-files.sha256"

if [[ "${resume}" != true ]]; then
    mkdir -p "${target_root}/.madlen-overlays"
    chmod 0700 "${target_root}/.madlen-overlays"
    mkdir "${record_root}"
    chmod 0700 "${record_root}"
    mkdir "${record_root}/originals"
    chmod 0700 "${record_root}/originals"
    cp -p "${overlay_root}/metadata.json" "${record_root}/metadata.json"
    cp -p "${overlay_root}/original-files.sha256" "${record_root}/original-files.sha256"
    cp -p "${overlay_root}/overlay-files.sha256" "${record_root}/overlay-files.sha256"
    printf '%s\n' PREPARED > "${record_root}/state.tmp"
    mv -f -- "${record_root}/state.tmp" "${state_file}"
fi

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    destination="${target_root}/${relative#./}"
    backup="${record_root}/originals/${relative#./}"
    if [[ -f "${backup}" && ! -L "${backup}" ]]; then
        [[ "$(file_hash "${backup}")" == "${expected}" ]] || {
            echo "Stored original backup checksum mismatch: ${relative}" >&2
            exit 1
        }
        continue
    fi
    [[ ! -e "${backup}" && ! -L "${backup}" && "$(file_hash "${destination}")" == "${expected}" ]] || {
        echo "Cannot create a verified original backup: ${relative}" >&2
        exit 1
    }
    mkdir -p "$(dirname "${backup}")"
    cp -p "${destination}" "${backup}"
    [[ "$(file_hash "${backup}")" == "${expected}" ]] || exit 1
done < "${overlay_root}/original-files.sha256"

: > "${record_root}/applied-files.sha256.tmp"
while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    source_file="${overlay_root}/files/${relative#./}"
    destination="${target_root}/${relative#./}"
    new_hash="$(expected_new_hash "${relative}")"
    [[ -f "${source_file}" && ! -L "${source_file}" && ! -L "${destination}" ]] || exit 1

    if [[ "$(file_hash "${destination}")" != "${new_hash}" ]]; then
        [[ "$(file_hash "${destination}")" == "${expected}" ]] || {
            echo "Target changed during apply: ${relative}" >&2
            exit 1
        }
        active_temp="$(dirname "${destination}")/.${relative##*/}.overlay-${overlay_id}-$$"
        [[ ! -e "${active_temp}" && ! -L "${active_temp}" ]] || exit 1
        cp -p "${source_file}" "${active_temp}"
        chmod 0644 "${active_temp}"
        [[ "$(file_hash "${active_temp}")" == "${new_hash}" ]] || exit 1
        mv -f -- "${active_temp}" "${destination}"
        active_temp=""
    fi
    [[ "$(file_hash "${destination}")" == "${new_hash}" ]] || exit 1
    printf '%s  ./%s\n' "${new_hash}" "${relative#./}" >> "${record_root}/applied-files.sha256.tmp"
done < "${overlay_root}/original-files.sha256"

mv -f -- "${record_root}/applied-files.sha256.tmp" "${record_root}/applied-files.sha256"
verify_applied || {
    echo "Final overlay verification failed; PREPARED state was preserved." >&2
    exit 1
}
printf '%s\n' APPLIED > "${record_root}/state.tmp"
mv -f -- "${record_root}/state.tmp" "${state_file}"

echo "Overlay ${overlay_id} applied and verified."
echo "No Composer autoload refresh is required: this overlay adds no PHP class."
