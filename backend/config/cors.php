<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'MADLEN_CONTACT_ALLOWED_ORIGINS',
        'https://madebymadlen.de,https://www.madebymadlen.de,https://admin.madebymadlen.de',
    )),
)));

return [
    'paths' => ['api/contact'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
