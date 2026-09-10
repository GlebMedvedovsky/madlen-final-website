#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
original_archive="${1:-${repository_root}/installation-artifacts/madlen-netcup-install-20260910T021858Z-ef2e3f4fe09c.tar.gz}"
overlay_archive="${2:?Usage: verify-netcup-env-helper-overlay.sh ORIGINAL_ARCHIVE OVERLAY_ARCHIVE}"
php_bin="${PHP_BIN:-/usr/local/php84/bin/php}"
if [[ ! -x "${php_bin}" ]] && [[ -x "$(command -v php 2>/dev/null || true)" ]]; then
    php_bin="$(command -v php)"
fi
export PHP_BIN="${php_bin}"

[[ -f "${original_archive}" && -f "${original_archive}.sha256" ]] || {
    echo "Original archive or external checksum is missing." >&2
    exit 1
}
[[ -f "${overlay_archive}" && -f "${overlay_archive}.sha256" ]] || {
    echo "Overlay archive or external checksum is missing." >&2
    exit 1
}

(cd "$(dirname "${original_archive}")" && sha256sum -c "$(basename "${original_archive}").sha256")
(cd "$(dirname "${overlay_archive}")" && sha256sum -c "$(basename "${overlay_archive}").sha256")

test_root="$(mktemp -d)"
trap 'rm -rf "${test_root}"' EXIT
candidate="${test_root}/candidate"
mkdir "${candidate}"
tar -xzf "${original_archive}" -C "${candidate}" --strip-components=1
tar -xzf "${overlay_archive}" -C "${test_root}"
overlay="${test_root}/madlen-env-helper-overlay"

(cd "${candidate}" && sha256sum -c .madlen-install-files.sha256 >/dev/null)
original_manifest_hash="$(sha256sum "${candidate}/.madlen-install-files.sha256" | awk '{print $1}')"

cp -p "${candidate}/docs/NETCUP_INSTALLATION_RU.md" "${test_root}/runbook.original"
printf '\nchecksum-refusal-control\n' >> "${candidate}/docs/NETCUP_INSTALLATION_RU.md"
if bash "${overlay}/apply-overlay.sh" "${candidate}" > "${test_root}/mismatch.log" 2>&1; then
    echo "Overlay unexpectedly accepted a modified original file." >&2
    exit 1
fi
grep -F 'Original checksum mismatch' "${test_root}/mismatch.log" >/dev/null || {
    cat "${test_root}/mismatch.log" >&2
    exit 1
}
mv "${test_root}/runbook.original" "${candidate}/docs/NETCUP_INSTALLATION_RU.md"

ln -s /dev/null "${candidate}/backend/app/Support/HostingPathResolver.php"
if bash "${overlay}/apply-overlay.sh" "${candidate}" > "${test_root}/symlink.log" 2>&1; then
    echo "Overlay unexpectedly accepted a target symlink." >&2
    exit 1
fi
grep -F 'Refusing candidate symlink' "${test_root}/symlink.log" >/dev/null || {
    cat "${test_root}/symlink.log" >&2
    exit 1
}
unlink "${candidate}/backend/app/Support/HostingPathResolver.php"

bash "${overlay}/apply-overlay.sh" "${candidate}" | tee "${test_root}/first-apply.log"
bash "${overlay}/apply-overlay.sh" "${candidate}" | tee "${test_root}/second-apply.log"
grep -F 'already applied and verified' "${test_root}/second-apply.log" >/dev/null

overlay_id="$(${php_bin} -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["overlayId"]??"";' "${overlay}/metadata.json")"
record="${candidate}/.madlen-overlays/${overlay_id}"
[[ "$(<"${record}/state")" == "APPLIED" ]]
[[ "$(sha256sum "${candidate}/.madlen-install-files.sha256" | awk '{print $1}')" == "${original_manifest_hash}" ]]

while read -r expected relative; do
    [[ -n "${expected}" && -n "${relative}" ]] || continue
    new_hash="$(awk -v wanted="./files/${relative#./}" '$2 == wanted { print $1 }' "${overlay}/overlay-files.sha256")"
    [[ "$(sha256sum "${candidate}/${relative#./}" | awk '{print $1}')" == "${new_hash}" ]]

    if [[ "${expected}" != "MISSING" ]]; then
        [[ "$(sha256sum "${record}/originals/${relative#./}" | awk '{print $1}')" == "${expected}" ]]
    fi
done < "${overlay}/original-files.sha256"

cp -p "${record}/originals/backend/config/filesystems.php" "${candidate}/backend/config/filesystems.php"
unlink "${candidate}/backend/app/Support/HostingPathResolver.php"
printf '%s\n' PREPARED > "${record}/state"
bash "${overlay}/apply-overlay.sh" "${candidate}" | tee "${test_root}/resume.log"
[[ "$(<"${record}/state")" == "APPLIED" ]]

${php_bin} -l "${candidate}/scripts/installation/write-private-env.php" >/dev/null
${php_bin} -l "${candidate}/scripts/installation/netcup-fpm-path-probe.php" >/dev/null
${php_bin} -l "${candidate}/backend/app/Support/HostingPathResolver.php" >/dev/null
${php_bin} -l "${candidate}/backend/config/filesystems.php" >/dev/null
${php_bin} -l "${candidate}/backend/config/madlen.php" >/dev/null

echo "PASS: overlay checksum refusal, symlink refusal, apply, idempotency, backup, resume, manifest preservation and PHP syntax."
