<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Assignable Administration queue names
    |--------------------------------------------------------------------------
    |
    | Optional. When non-empty, only desk staff whose full name contains one of
    | these keywords appear in the assign picker (and the automatic team
    | assignment). Leave empty to make every active Administration desk account
    | assignable.
    |
    */
    'assignable_name_keywords' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMINISTRATION_ASSIGNABLE_NAMES', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Category routing
    |--------------------------------------------------------------------------
    |
    | New requests are always routed to the general desk (officers and
    | managers). Specialist roles are added to the team when the request falls
    | in a category they own.
    |
    */
    'insurance_reminder_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('ADMINISTRATION_INSURANCE_REMINDER_DAYS', '30,14,7,1,0')),
    ), fn (int $days): bool => $days >= 0)),

    'category_roles' => [
        'insurance-management' => ['insurance-officer'],
        'fleet-vehicle-management' => ['fleet-officer'],
        'fuel-card-management' => ['fleet-officer'],
        'cctv-surveillance' => ['cctv-approver', 'security-officer'],
        'security-management' => ['security-officer'],
    ],

];
