<?php

declare(strict_types=1);

return [
    'request_reference' => [
        'prefix' => env('ADMINISTRATION_HELPDESK_PREFIX', 'ADM'),
        'padding' => 6,
    ],

    'attachments' => [
        'max_kilobytes' => (int) env('ADM_ATTACHMENT_MAX_KB', 10240),
        'max_per_request' => (int) env('ADM_ATTACHMENT_MAX_PER_REQUEST', 5),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'disk' => env('ADM_ATTACHMENT_DISK', 'local'),
        'directory' => 'administration-attachments',
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 50,
    ],

    'support' => [
        'email' => env('ADMINISTRATION_SUPPORT_EMAIL', 'administration@company.local'),
        'phone' => env('ADMINISTRATION_SUPPORT_PHONE', ''),
        'hours' => env('ADMINISTRATION_SUPPORT_HOURS', 'Weekdays 08:00-17:00'),
    ],
];
