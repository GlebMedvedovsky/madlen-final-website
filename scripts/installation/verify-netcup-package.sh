#!/usr/bin/env bash

set -euo pipefail

archive_path="${1:?Usage: verify-netcup-package.sh ARCHIVE [SHA256_FILE]}"
checksum_path="${2:-${archive_path}.sha256}"
archive_path="$(cd "$(dirname "${archive_path}")" && pwd)/$(basename "${archive_path}")"
checksum_path="$(cd "$(dirname "${checksum_path}")" && pwd)/$(basename "${checksum_path}")"

(
    cd "$(dirname "${archive_path}")"
    sha256sum -c "$(basename "${checksum_path}")"
)

stage_root="$(mktemp -d)"
trap 'rm -rf "${stage_root}"' EXIT
tar -xzf "${archive_path}" -C "${stage_root}"
package_root="${stage_root}/madlen-install"
[[ -d "${package_root}" ]] || { echo 'Package root is missing.' >&2; exit 1; }

if find "${package_root}" -type l | grep -q .; then
    echo 'Package contains a symbolic link.' >&2
    exit 1
fi
if find "${package_root}" -type f \( -name '.env' -o -name '*.sql' -o -name '*.sqlite' -o -name '*.key' \) | grep -q .; then
    echo 'Package contains a forbidden private file.' >&2
    exit 1
fi
for forbidden in \
    node_modules \
    backend/node_modules \
    public/design-reference \
    public/images/start_seite.jpeg \
    public/images/Kukes1.jpg \
    "public/images/Grafik Elemente/Blaues_Element_Wolke.png" \
    "public/images/Grafik Elemente/Linie_Blau_Klein.png" \
    "public/images/Grafik Elemente/Linine_Blau_Gross.png" \
    "public/images/Grafik Elemente/Rosa_Blau_Linie.png"; do
    [[ ! -e "${package_root}/${forbidden}" ]] || { echo "Forbidden package path: ${forbidden}" >&2; exit 1; }
done

for required in \
    .madlen-install.json \
    content/baseline.json \
    public/images/hero/hero-video.mp4 \
    public/images/hero/memories-photo.webp \
    backend/artisan \
    backend/composer.lock \
    backend/vendor/autoload.php \
    backend/public/index.php; do
    [[ -f "${package_root}/${required}" ]] || { echo "Required package file is missing: ${required}" >&2; exit 1; }
done

(
    cd "${package_root}"
    sha256sum -c .madlen-install-files.sha256 >/dev/null
)

[[ -n "$(find "${package_root}/backend/public/css/filament" -type f -print -quit 2>/dev/null)" ]] || {
    echo 'Filament CSS assets are missing.' >&2
    exit 1
}
[[ -n "$(find "${package_root}/backend/public/js/filament" -type f -print -quit 2>/dev/null)" ]] || {
    echo 'Filament JavaScript assets are missing.' >&2
    exit 1
}

(
    cd "${package_root}/backend"
    composer check-platform-reqs --no-dev
    APP_ENV=testing \
    APP_DEBUG=false \
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
    DB_CONNECTION=sqlite \
    DB_DATABASE=':memory:' \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    MADLEN_CONTACT_ENABLED=false \
    php artisan route:list --path=api/contact --except-vendor | grep -q 'POST.*api/contact'
)

node "${package_root}/scripts/installation/verify-baseline-media.mjs" "${package_root}"
printf 'PACKAGE_SMOKE_TEST=OK\n'
