<?php

use App\Support\HostingPathResolver;

$hostingPaths = HostingPathResolver::resolve(base_path(), env('MADLEN_INSTALL_LAYOUT'));

return [
    'repository_root' => env('MADLEN_REPOSITORY_ROOT') ?: ($hostingPaths['repository_root'] ?? dirname(base_path())),
    'baseline_path' => env('MADLEN_BASELINE_PATH') ?: ($hostingPaths['baseline_path'] ?? dirname(base_path()).'/content/baseline.json'),
    'release_root' => env('MADLEN_RELEASE_ROOT') ?: ($hostingPaths['release_root'] ?? storage_path('app/releases')),
    'public_url' => env('MADLEN_PUBLIC_URL', 'http://localhost:8089'),
    'public_site_url' => env('MADLEN_PUBLIC_SITE_URL', 'https://madebymadlen.de'),
    'production_publisher' => env('MADLEN_PRODUCTION_PUBLISHER', 'unconfigured'),
    'production_connected' => filter_var(env('MADLEN_PRODUCTION_CONNECTED', false), FILTER_VALIDATE_BOOL),
    'publisher' => [
        'package_root' => env('MADLEN_PRODUCTION_PACKAGE_ROOT') ?: ($hostingPaths['production_package_root'] ?? storage_path('app/production-publications')),
        'incoming_root' => env('MADLEN_PRODUCTION_INCOMING_ROOT') ?: ($hostingPaths['production_incoming_root'] ?? null),
        'destination_root' => env('MADLEN_PRODUCTION_DESTINATION_ROOT') ?: ($hostingPaths['production_destination_root'] ?? null),
        'source_revision' => env('MADLEN_PRODUCTION_SOURCE_REVISION'),
        'api_token' => env('MADLEN_PUBLISHER_API_TOKEN'),
        'github_repository' => env('MADLEN_GITHUB_REPOSITORY'),
        'github_workflow' => env('MADLEN_GITHUB_WORKFLOW', 'madlen-production-publisher.yml'),
        'github_ref' => env('MADLEN_GITHUB_REF', 'main'),
        'github_token' => env('MADLEN_GITHUB_TOKEN'),
        'simulate_transfer_failure' => filter_var(env('MADLEN_SIMULATE_TRANSFER_FAILURE', false), FILTER_VALIDATE_BOOL),
    ],
    'public_exclude_paths' => [
        'design-reference',
        'images/start_seite.jpeg',
        'start_seite.jpeg',
        'images/Kukes1.jpg',
        'images/Grafik Elemente/Blaues_Element_Wolke.png',
        'images/Grafik Elemente/Linie_Blau_Klein.png',
        'images/Grafik Elemente/Linine_Blau_Gross.png',
        'images/Grafik Elemente/Rosa_Blau_Linie.png',
    ],
    'preview_ttl_minutes' => (int) env('MADLEN_PREVIEW_TTL', 120),
    'preview_execution' => env('MADLEN_PREVIEW_EXECUTION', 'local'),
    'external_preview_connected' => filter_var(env('MADLEN_EXTERNAL_PREVIEW_CONNECTED', false), FILTER_VALIDATE_BOOL),
    'preview_runner' => [
        'driver' => env('MADLEN_EXTERNAL_PREVIEW_DRIVER', 'unconfigured'),
        'package_root' => env('MADLEN_PREVIEW_PACKAGE_ROOT') ?: ($hostingPaths['preview_package_root'] ?? storage_path('app/external-previews/packages')),
        'incoming_root' => env('MADLEN_PREVIEW_INCOMING_ROOT') ?: ($hostingPaths['preview_incoming_root'] ?? storage_path('app/external-previews/incoming')),
        'result_root' => env('MADLEN_PREVIEW_RESULT_ROOT') ?: ($hostingPaths['preview_result_root'] ?? storage_path('app/external-previews/results')),
        'source_revision' => env('MADLEN_PREVIEW_SOURCE_REVISION'),
        'api_token' => env('MADLEN_PREVIEW_API_TOKEN'),
        'github_repository' => env('MADLEN_PREVIEW_GITHUB_REPOSITORY'),
        'github_workflow' => env('MADLEN_PREVIEW_GITHUB_WORKFLOW', 'madlen-external-preview.yml'),
        'github_ref' => env('MADLEN_PREVIEW_GITHUB_REF', 'main'),
        'github_token' => env('MADLEN_PREVIEW_GITHUB_TOKEN'),
    ],
    'media' => [
        'max_image_kb' => (int) env('MADLEN_MAX_IMAGE_KB', 20480),
        'max_video_kb' => (int) env('MADLEN_MAX_VIDEO_KB', 51200),
        'max_dimension' => (int) env('MADLEN_MAX_IMAGE_DIMENSION', 12000),
        'web_max_width' => (int) env('MADLEN_WEB_MAX_WIDTH', 2400),
    ],
];
