<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;

class SettingService
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'organisation_name' => (string) config('app.name', 'IT Helpdesk'),
            'support_email' => 'it.support@example.com',
            'support_phone' => '',
            'support_hours' => 'Monday–Friday, 08:30–17:00',
            'allow_self_registration' => '1',
        ];
    }

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $stored = SystemSetting::query()->pluck('setting_value', 'setting_key')->all();
        $merged = self::defaults();

        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $merged)) {
                $merged[$key] = (string) ($value ?? '');
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function update(User $actor, array $values): array
    {
        $allowed = array_keys(self::defaults());
        $before = $this->all();

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $values[$key]],
            );
        }

        $after = $this->all();

        $this->audit->log(
            event: 'settings.updated',
            subject: $actor,
            oldValues: $before,
            newValues: $after,
            description: $actor->username.' updated system settings',
        );

        return $after;
    }
}
