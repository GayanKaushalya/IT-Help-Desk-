<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes entries to the audit trail.
 *
 * Injected into services rather than called statically so the trail can be
 * faked in tests and so request context is resolved in exactly one place.
 */
class AuditLogger
{
    /**
     * Sensitive attributes are stripped before anything is persisted.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'password_confirmation',
        'remember_token',
        'code_hash',
        'code_last_four',
        'code_ciphertext',
        'verification_code',
        'temporary_password',
        'code',
        'token',
    ];

    public function log(
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): AuditLog {
        $actorId = Auth::id();

        if ($actorId === null && $subject instanceof User) {
            $actorId = $subject->getKey();
        }

        return AuditLog::create([
            'user_id' => $actorId,
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $this->redact($oldValues) ?: null,
            'new_values' => $this->redact($newValues) ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
            'method' => Request::method(),
            'url' => mb_substr(Request::fullUrl(), 0, 255),
        ]);
    }

    /**
     * Records a change by diffing a model's dirty attributes.
     */
    public function logModelChange(string $event, Model $subject, ?string $description = null): AuditLog
    {
        return $this->log(
            event: $event,
            subject: $subject,
            oldValues: array_intersect_key($subject->getOriginal(), $subject->getDirty()),
            newValues: $subject->getDirty(),
            description: $description,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
