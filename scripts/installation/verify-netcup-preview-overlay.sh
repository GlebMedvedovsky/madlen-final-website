#!/usr/bin/env bash

set -euo pipefail

original_archive="${1:?Usage: verify-netcup-preview-overlay.sh ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY PREVIEW_OVERLAY}"
compatibility_overlay="${2:?Usage: verify-netcup-preview-overlay.sh ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY PREVIEW_OVERLAY}"
preview_overlay="${3:?Usage: verify-netcup-preview-overlay.sh ORIGINAL_ARCHIVE COMPATIBILITY_OVERLAY PREVIEW_OVERLAY}"
php_bin="$(command -v php)"

[[ -f "${original_archive}" && -f "${original_archive}.sha256" ]] || exit 1
[[ -f "${compatibility_overlay}" ]] || exit 1
[[ -f "${preview_overlay}" && -f "${preview_overlay}.sha256" ]] || exit 1
(cd "$(dirname "${original_archive}")" && sha256sum -c "$(basename "${original_archive}").sha256" >/dev/null)
(cd "$(dirname "${preview_overlay}")" && sha256sum -c "$(basename "${preview_overlay}").sha256" >/dev/null)

test_root="$(mktemp -d)"
cleanup() { rm -rf -- "${test_root}"; }
trap cleanup EXIT HUP INT TERM

tar -xzf "${original_archive}" -C "${test_root}"
tar -xzf "${compatibility_overlay}" -C "${test_root}"
tar -xzf "${preview_overlay}" -C "${test_root}"
candidate="${test_root}/madlen-install"
compatibility="${test_root}/madlen-env-helper-overlay"
overlay="${test_root}/madlen-preview-runtime-overlay"

PHP_BIN="${php_bin}" bash "${compatibility}/apply-overlay.sh" "${candidate}" >/dev/null
install_manifest_hash="$(sha256sum "${candidate}/.madlen-install-files.sha256")"
install_manifest_hash="${install_manifest_hash%% *}"

read -r first_hash first_relative < "${overlay}/original-files.sha256"
first_target="${candidate}/${first_relative#./}"
cp -p "${first_target}" "${test_root}/first-original"
printf '\nUNEXPECTED-MODIFICATION\n' >> "${first_target}"
if PHP_BIN="${php_bin}" bash "${overlay}/apply-overlay.sh" "${candidate}" > "${test_root}/mismatch.log" 2>&1; then
    echo "Preview overlay unexpectedly accepted a checksum mismatch." >&2
    exit 1
fi
cp -p "${test_root}/first-original" "${first_target}"

read_count=0
second_relative=""
while read -r _ relative; do
    read_count=$((read_count + 1))
    if [[ "${read_count}" -eq 2 ]]; then second_relative="${relative}"; break; fi
done < "${overlay}/original-files.sha256"
[[ -n "${second_relative}" ]] || exit 1
second_target="${candidate}/${second_relative#./}"
mv "${second_target}" "${test_root}/second-original"
ln -s "${test_root}/second-original" "${second_target}"
if PHP_BIN="${php_bin}" bash "${overlay}/apply-overlay.sh" "${candidate}" > "${test_root}/symlink.log" 2>&1; then
    echo "Preview overlay unexpectedly accepted a target symlink." >&2
    exit 1
fi
rm -- "${second_target}"
mv "${test_root}/second-original" "${second_target}"

PHP_BIN="${php_bin}" bash "${overlay}/apply-overlay.sh" "${candidate}"
PHP_BIN="${php_bin}" bash "${overlay}/apply-overlay.sh" "${candidate}"

overlay_id="$("${php_bin}" -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["overlayId"]??"";' "${overlay}/metadata.json")"
record="${candidate}/.madlen-overlays/${overlay_id}"
[[ -f "${record}/state" && "$(<"${record}/state")" == "APPLIED" ]] || exit 1

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    replacement="${overlay}/files/${relative#./}"
    destination="${candidate}/${relative#./}"
    replacement_hash="$(sha256sum "${replacement}")"
    replacement_hash="${replacement_hash%% *}"
    destination_hash="$(sha256sum "${destination}")"
    destination_hash="${destination_hash%% *}"
    backup_hash="$(sha256sum "${record}/originals/${relative#./}")"
    backup_hash="${backup_hash%% *}"
    [[ "${replacement_hash}" == "${destination_hash}" && "${backup_hash}" == "${expected}" ]] || exit 1
    case "${relative}" in *.php) "${php_bin}" -l "${destination}" >/dev/null ;; esac
done < "${overlay}/original-files.sha256"

cp -p "${record}/originals/${first_relative#./}" "${first_target}"
printf '%s\n' PREPARED > "${record}/state"
PHP_BIN="${php_bin}" bash "${overlay}/apply-overlay.sh" "${candidate}"
[[ "$(<"${record}/state")" == "APPLIED" ]] || exit 1

final_install_manifest_hash="$(sha256sum "${candidate}/.madlen-install-files.sha256")"
final_install_manifest_hash="${final_install_manifest_hash%% *}"
[[ "${install_manifest_hash}" == "${final_install_manifest_hash}" ]] || {
    echo "The original full-package manifest was modified." >&2
    exit 1
}

echo "PASS: compatibility baseline, checksum refusal, symlink refusal, backups, apply, idempotency, resume, manifest preservation and PHP syntax."
