<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Competizioni Judo'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'favicon' => 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥋</text></svg>',
    'events_upcoming_limit' => (int) env('EVENTS_UPCOMING_LIMIT', 12),
];
