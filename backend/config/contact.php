<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'MADLEN_CONTACT_ALLOWED_ORIGINS',
        'https://madebymadlen.de,https://www.madebymadlen.de,https://admin.madebymadlen.de',
    )),
)));

return [
    'enabled' => filter_var(env('MADLEN_CONTACT_ENABLED', false), FILTER_VALIDATE_BOOL),
    'require_origin' => filter_var(env('MADLEN_CONTACT_REQUIRE_ORIGIN', true), FILTER_VALIDATE_BOOL),
    'allowed_origins' => $origins,
    'mailer' => env('MADLEN_CONTACT_MAILER', 'smtp'),
    'from' => [
        'address' => env('MADLEN_CONTACT_FROM_ADDRESS', 'hi@madebymadlen.de'),
        'name' => env('MADLEN_CONTACT_FROM_NAME', 'Made by Madlen'),
    ],
    'recipient' => env('MADLEN_CONTACT_RECIPIENT', 'contact@madlenmedvedovskyy.de'),
    'rate_limit' => [
        'per_minute' => (int) env('MADLEN_CONTACT_RATE_PER_MINUTE', 3),
        'per_hour' => (int) env('MADLEN_CONTACT_RATE_PER_HOUR', 10),
    ],
    'form_time' => [
        'minimum_seconds' => (int) env('MADLEN_CONTACT_MIN_SECONDS', 3),
        'maximum_seconds' => (int) env('MADLEN_CONTACT_MAX_SECONDS', 7200),
    ],
    'subjects' => [
        'de' => 'Neue Anfrage über madebymadlen.de',
        'en' => 'New inquiry via madebymadlen.de',
    ],
    'request_types' => [
        'de' => [
            'Portraitshooting',
            'Paare & Familien',
            'Hochzeit',
            'Event',
            'Editorial & Commercial',
            'Videografie',
            'Videoschnitt',
            'Sonstiges',
        ],
        'en' => [
            'Portrait photography',
            'Couples & families',
            'Wedding',
            'Event',
            'Editorial & Commercial',
            'Videography',
            'Video editing',
            'Other',
        ],
    ],
];
