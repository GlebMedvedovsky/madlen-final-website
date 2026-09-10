<?php

namespace App\Support;

use InvalidArgumentException;

final class HostingPathResolver
{
    public const NETCUP_SIBLING_PRIVATE = 'netcup-sibling-private-v1';

    /**
     * Resolve the Netcup layout from Laravel's real runtime base path.
     *
     * The same relative layout works with the SSH jail path and with a
     * different physical prefix exposed to PHP-FPM. Configuration caching is
     * intentionally deferred until both contexts have been compared.
     *
     * @return array<string, string>
     */
    public static function resolve(string $backendBasePath, ?string $layout): array
    {
        if ($layout !== self::NETCUP_SIBLING_PRIVATE) {
            return [];
        }

        $backend = rtrim(str_replace('\\', '/', $backendBasePath), '/');
        if ($backend === '' || basename($backend) !== 'backend') {
            throw new InvalidArgumentException('The Netcup layout requires a backend base directory.');
        }

        $repository = dirname($backend);
        $site = dirname($repository);
        if ($repository === '.' || $site === '.' || $site === $repository) {
            throw new InvalidArgumentException('The Netcup hosting root could not be derived safely.');
        }

        $sitePrefix = rtrim($site, '/');
        $private = $sitePrefix.'/private';

        return [
            'repository_root' => $repository,
            'baseline_path' => $repository.'/content/baseline.json',
            'private_root' => $private,
            'storage_root' => $private.'/storage',
            'release_root' => $private.'/runtime/releases',
            'production_package_root' => $private.'/packages/production',
            'production_incoming_root' => $private.'/incoming/production',
            'production_destination_root' => $sitePrefix.'/releases/static',
            'preview_package_root' => $private.'/packages/preview',
            'preview_incoming_root' => $private.'/incoming/preview',
            'preview_result_root' => $private.'/previews/results',
        ];
    }
}
