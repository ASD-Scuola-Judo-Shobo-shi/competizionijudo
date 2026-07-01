<?php

declare(strict_types=1);

return [
    'controller_name' => env('APP_OWNER', ''),
    'controller_address' => env('APP_OWNER_ADDRESS', ''),
    'controller_fiscal_code' => env('APP_OWNER_FISCAL_CODE', ''),
    'contact_email' => env('APP_OWNER_EMAIL', ''),
    'hosting_provider' => env('APP_WEBHOST', ''),
    'hosting_location' => env('APP_WEBHOST_LOCATION', ''),
    'log_retention_days' => (int) env('APP_LOG_RETENTION_DAYS', 0),
    'backup_retention_days' => (int) env('APP_BACKUP_RETENTION_DAYS', 0),
];
