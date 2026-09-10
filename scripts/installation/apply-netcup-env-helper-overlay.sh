#!/usr/bin/env bash

set -euo pipefail

overlay_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
target_root="${1:-/madebymadlen.de/app-candidate}"
php_bin="${PHP_BIN:-/usr/local/php84/bin/php}"
active_temp=""

cleanup() {
    if [[ -n "${active_temp}" && -e "${active_temp}" && ! -L "${active_temp}" ]]; then
        rm -f -- "${active_temp}"
    fi
}

trap cleanup EXIT HUP INT TERM

[[ -x "${php_bin}" ]] || {
    echo "Required PHP binary is unavailable: ${php_bin}" >&2
    exit 1
}

[[ -d "${target_root}" && ! -L "${target_root}" ]] || {
    echo "Candidate root is missing or is a symlink: ${target_root}" >&2
    exit 1
}

cd "${overlay_root}"
sha256sum -c overlay-files.sha256 >/dev/null

overlay_id="$(${php_bin} -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $v=$m["overlayId"]??""; if(!is_string($v)||!preg_match("/^[a-z0-9-]+$/",$v)){exit(1);} echo $v;' metadata.json)"
base_revision="$(${php_bin} -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["baseRevision"]??"";' metadata.json)"
candidate_revision="$(${php_bin} -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["baseRevision"]??"";' "${target_root}/.madlen-install.json")"

[[ -n "${overlay_id}" && "${candidate_revision}" == "${base_revision}" ]] || {
    echo "Candidate base revision does not match this overlay." >&2
    exit 1
}

record_root="${target_root}/.madlen-overlays/${overlay_id}"
state_file="${record_root}/state"

is_allowed_path() {
    case "$1" in
        ./backend/.env.example|\
        ./backend/app/Support/HostingPathResolver.php|\
        ./backend/config/filesystems.php|\
        ./backend/config/madlen.php|\
        ./docs/NETCUP_INSTALLATION_RU.md|\
        ./scripts/installation/netcup-fpm-path-probe.php|\
        ./scripts/installation/write-private-env.php) return 0 ;;
        *) return 1 ;;
    esac
}

file_hash() {
    sha256sum "$1" | awk '{print $1}'
}

expected_new_hash() {
    local relative="$1"
    awk -v wanted="./files/${relative#./}" '$2 == wanted { print $1 }' "${overlay_root}/overlay-files.sha256"
}

verify_applied() {
    local expected relative destination actual

    while read -r expected relative; do
        [[ -n "${expected}" && -n "${relative}" ]] || continue
        is_allowed_path "${relative}" || {
            echo "Overlay manifest contains a disallowed path: ${relative}" >&2
            return 1
        }
        destination="${target_root}/${relative#./}"
        [[ -f "${destination}" && ! -L "${destination}" ]] || return 1
        actual="$(file_hash "${destination}")"
        [[ "${actual}" == "$(expected_new_hash "${relative}")" ]] || return 1
    done < "${overlay_root}/original-files.sha256"
}

if [[ -f "${state_file}" ]] && [[ "$(<"${state_file}")" == "APPLIED" ]]; then
    verify_applied || {
        echo "Overlay is marked APPLIED, but patched files no longer match." >&2
        exit 1
    }
    echo "Overlay ${overlay_id} is already applied and verified."
    exit 0
fi

resume=false
if [[ -f "${state_file}" ]] && [[ "$(<"${state_file}")" == "PREPARED" ]]; then
    resume=true
elif [[ -e "${record_root}" || -L "${record_root}" ]]; then
    echo "Overlay record exists in an unknown state: ${record_root}" >&2
    exit 1
fi

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    is_allowed_path "${relative}" || {
        echo "Overlay manifest contains a disallowed path: ${relative}" >&2
        exit 1
    }

    destination="${target_root}/${relative#./}"
    new_hash="$(expected_new_hash "${relative}")"
    [[ "${new_hash}" =~ ^[0-9a-f]{64}$ ]] || {
        echo "Missing replacement checksum for ${relative}" >&2
        exit 1
    }

    [[ ! -L "${destination}" ]] || {
        echo "Refusing candidate symlink: ${relative}" >&2
        exit 1
    }

    if [[ "${expected}" == "MISSING" ]]; then
        if [[ -e "${destination}" ]] && [[ "$(file_hash "${destination}")" != "${new_hash}" ]]; then
            echo "Unexpected pre-existing file: ${relative}" >&2
            exit 1
        fi
    else
        [[ "${expected}" =~ ^[0-9a-f]{64}$ ]] || {
            echo "Invalid original checksum for ${relative}" >&2
            exit 1
        }
        [[ -f "${destination}" ]] || {
            echo "Expected original file is missing: ${relative}" >&2
            exit 1
        }
        current_hash="$(file_hash "${destination}")"
        if [[ "${current_hash}" != "${expected}" && ( "${resume}" != true || "${current_hash}" != "${new_hash}" ) ]]; then
            echo "Original checksum mismatch; candidate was not modified: ${relative}" >&2
            exit 1
        fi
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
    [[ "${expected}" != "MISSING" ]] || continue
    destination="${target_root}/${relative#./}"
    backup="${record_root}/originals/${relative#./}"

    if [[ -f "${backup}" && ! -L "${backup}" ]]; then
        [[ "$(file_hash "${backup}")" == "${expected}" ]] || {
            echo "Stored original backup checksum mismatch: ${relative}" >&2
            exit 1
        }
        continue
    fi

    [[ ! -e "${backup}" && ! -L "${backup}" ]] || {
        echo "Invalid stored original backup: ${relative}" >&2
        exit 1
    }
    [[ "$(file_hash "${destination}")" == "${expected}" ]] || {
        echo "Cannot resume safely because the original backup is absent: ${relative}" >&2
        exit 1
    }
    mkdir -p "$(dirname "${backup}")"
    cp -p "${destination}" "${backup}"
    [[ "$(file_hash "${backup}")" == "${expected}" ]] || {
        echo "Original backup verification failed: ${relative}" >&2
        exit 1
    }
done < "${overlay_root}/original-files.sha256"

: > "${record_root}/applied-files.sha256.tmp"

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    source_file="${overlay_root}/files/${relative#./}"
    destination="${target_root}/${relative#./}"
    new_hash="$(expected_new_hash "${relative}")"

    [[ -f "${source_file}" && ! -L "${source_file}" ]] || {
        echo "Replacement source is missing or is a symlink: ${relative}" >&2
        exit 1
    }
    [[ ! -L "${destination}" ]] || {
        echo "Refusing candidate symlink during apply: ${relative}" >&2
        exit 1
    }

    if [[ -f "${destination}" ]] && [[ "$(file_hash "${destination}")" == "${new_hash}" ]]; then
        printf '%s  ./%s\n' "${new_hash}" "${relative#./}" >> "${record_root}/applied-files.sha256.tmp"
        continue
    fi

    if [[ "${expected}" == "MISSING" ]]; then
        [[ ! -e "${destination}" ]] || {
            echo "New target appeared during apply: ${relative}" >&2
            exit 1
        }
    else
        [[ -f "${destination}" && "$(file_hash "${destination}")" == "${expected}" ]] || {
            echo "Target changed during apply: ${relative}" >&2
            exit 1
        }
    fi

    mkdir -p "$(dirname "${destination}")"
    active_temp="$(dirname "${destination}")/.${relative##*/}.overlay-${overlay_id}-$$"
    [[ ! -e "${active_temp}" && ! -L "${active_temp}" ]] || {
        echo "Temporary apply path already exists: ${active_temp}" >&2
        exit 1
    }
    cp -p "${source_file}" "${active_temp}"
    chmod 0644 "${active_temp}"
    [[ "$(file_hash "${active_temp}")" == "${new_hash}" ]] || {
        echo "Temporary replacement verification failed: ${relative}" >&2
        exit 1
    }
    mv -f -- "${active_temp}" "${destination}"
    active_temp=""
    [[ "$(file_hash "${destination}")" == "${new_hash}" ]] || {
        echo "Applied replacement verification failed: ${relative}" >&2
        exit 1
    }
    printf '%s  ./%s\n' "${new_hash}" "${relative#./}" >> "${record_root}/applied-files.sha256.tmp"
done < "${overlay_root}/original-files.sha256"

mv -f -- "${record_root}/applied-files.sha256.tmp" "${record_root}/applied-files.sha256"
verify_applied || {
    echo "Final overlay verification failed; PREPARED state was preserved for diagnosis." >&2
    exit 1
}

printf '%s\n' APPLIED > "${record_root}/state.tmp"
mv -f -- "${record_root}/state.tmp" "${state_file}"

echo "Overlay ${overlay_id} applied and verified."
echo "The original full-package manifest remains unchanged by design."
