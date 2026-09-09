#!/bin/sh

set -eu

umask 0002

runtime_paths="
/workspace/backend/storage
/workspace/backend/bootstrap/cache
/workspace/.astro
/workspace/node_modules/.vite
/workspace/dist
"

mkdir -p \
    /workspace/backend/storage/framework/cache \
    /workspace/backend/storage/framework/sessions \
    /workspace/backend/storage/framework/views \
    /workspace/backend/storage/logs \
    /workspace/backend/storage/app/private/media \
    /workspace/backend/storage/app/releases/previews/manifests \
    /workspace/backend/storage/app/releases/previews/builds \
    /workspace/backend/storage/app/releases/manifests \
    /workspace/backend/storage/app/releases/builds \
    /workspace/backend/storage/app/releases/backups \
    /workspace/backend/storage/app/releases/exports \
    /workspace/backend/storage/app/releases/restore-tests \
    /workspace/backend/storage/app/runtime-home/.config/psysh \
    /workspace/backend/bootstrap/cache \
    /workspace/.astro \
    /workspace/node_modules/.vite \
    /workspace/dist

for path in $runtime_paths; do
    chown -R www-data:www-data "$path"
    find "$path" -type d -exec chmod 2775 {} +
    find "$path" -type f -exec chmod 0664 {} +
done
