<?php

declare(strict_types=1);

return [
    'request_reference' => [
        'prefix' => env('HR_HELPDESK_PREFIX', 'HR'),
        'padding' => 6,
    ],

    'attachments' => [
        'max_kilobytes' => (int) env('HR_ATTACHMENT_MAX_KB', 10240),
        'max_per_request' => (int) env('HR_ATTACHMENT_MAX_PER_REQUEST', 5),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'disk' => env('HR_ATTACHMENT_DISK', 'local'),
        'directory' => 'hr-attachments',
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 50,
    ],

    'referral_departments' => [
        ['slug' => 'hr', 'name' => 'HR'],
        ['slug' => 'finance', 'name' => 'Finance'],
        ['slug' => 'it', 'name' => 'IT'],
    ],

    'support' => [
        'email' => env('HR_SUPPORT_EMAIL', 'hr@company.local'),
        'phone' => env('HR_SUPPORT_PHONE', ''),
        'hours' => env('HR_SUPPORT_HOURS', 'Weekdays 08:00–17:00'),
    ],
];
