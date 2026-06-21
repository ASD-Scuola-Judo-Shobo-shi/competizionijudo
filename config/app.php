<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Competizioni Judo'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'events_upcoming_limit' => (int) env('EVENTS_UPCOMING_LIMIT', 12),
];
