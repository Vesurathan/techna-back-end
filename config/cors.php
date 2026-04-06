<?php

$defaultOrigins = [
    'https://technatechnicalinstitute.com',
    'https://admin.technatechnicalinstitute.com',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

$fromEnv = env('CORS_ALLOWED_ORIGINS');

if (is_string($fromEnv) && trim($fromEnv) !== '') {
    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $fromEnv))));
} else {
    $allowedOrigins = $defaultOrigins;
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
