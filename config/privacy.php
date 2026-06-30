<?php

declare(strict_types=1);

return [
    'controller_name' => env('PRIVACY_CONTROLLER_NAME', ''),
    'controller_address' => env('PRIVACY_CONTROLLER_ADDRESS', ''),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL', ''),
    'dpo_email' => env('PRIVACY_DPO_EMAIL', ''),
    'account_legal_basis' => env('PRIVACY_ACCOUNT_LEGAL_BASIS', ''),
    'athlete_legal_basis' => env('PRIVACY_ATHLETE_LEGAL_BASIS', ''),
    'hosting_provider' => env('PRIVACY_HOSTING_PROVIDER', ''),
    'hosting_location' => env('PRIVACY_HOSTING_LOCATION', ''),
    'data_transfer_details' => env('PRIVACY_DATA_TRANSFER_DETAILS', ''),
    'additional_processors' => env('PRIVACY_ADDITIONAL_PROCESSORS', ''),
    'log_retention_days' => (int) env('PRIVACY_LOG_RETENTION_DAYS', 0),
    'backup_retention_days' => (int) env('PRIVACY_BACKUP_RETENTION_DAYS', 0),
];
