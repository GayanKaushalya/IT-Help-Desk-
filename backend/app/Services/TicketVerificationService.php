<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asks the requester to confirm or reject a resolution — no shared code.
 */
class TicketVerificationService
{
    public const INVALID_MESSAGE = 'This resolution can no longer be confirmed.';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Create a pending confirmation request for the requester.
     * Returns null — there is no code to share with the employee.
     */
    public function issue(Ticket $ticket, User $issuer): ?string
    {
        $ticket->loadMissing('requester');

        $ttl = (int) config('helpdesk.verification.ttl_minutes', 0);
        $method = (string) config('helpdesk.verification.method', 'employee_confirmation');

        $ticket->verifications()
            ->pending()
            ->update(['status' => VerificationStatus::Cancelled->value]);

        // code_hash stays required by the schema; it is unused for confirmation.
        // expires_at is required by the column; with ttl=0 use a far-future date (no real limit).
        $expiresAt = $ttl > 0 ? now()->addMinutes($ttl) : now()->addYears(50);

        $ticket->verifications()->create([
            'code_hash' => Hash::make(Str::random(40)),
            'code_last_four' => null,
            'code_ciphertext' => null,
            'issued_by' => $issuer->id,
            'issued_to' => $ticket->requester_id,
            'status' => VerificationStatus::Pending,
            'method' => $method,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ]);

        $this->audit->log(
            event: 'ticket.verification_issued',
            subject: $ticket,
            newValues: ['status' => VerificationStatus::Pending->value, 'method' => $method],
            description: $issuer->username.' asked the requester to confirm '.$ticket->reference,
        );

        return null;
    }

    public function respond(Ticket $ticket, User $actor, string $decision): Ticket
    {
        return $decision === 'rejected'
            ? $this->reject($ticket, $actor)
            : $this->confirm($ticket, $actor);
    }

    public function confirm(Ticket $ticket, User $actor): Ticket
    {
        $outcome = DB::transaction(function () use ($ticket, $actor) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing(['status', 'requester']);

            $latest = $ticket->verifications()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($this->alreadyVerifiedBy($ticket, $actor, $latest)) {
                return ['ticket' => $ticket, 'already_closed' => true];
            }

            $verification = $this->pendingVerification($ticket, $latest);

            if (! $verification instanceof TicketVerification
                || ! $this->ticketIsAwaitingVerification($ticket)
                || $this->confirmationHasExpired($verification)
            ) {
                return ['error' => 'invalid'];
            }

            $ticket->loadMissing('assignments');
            $ticket->assertIndividualAssigneeForClose();

            $closed = TicketStatus::query()->where('slug', 'closed')->first();

            if (! $closed) {
                return ['error' => 'invalid'];
            }

            $verification->forceFill([
                'status' => VerificationStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $actor->id,
                'method' => $verification->method ?: 'employee_confirmation',
                'blocked_until' => null,
            ])->save();

            $ticket->forceFill([
                'status_id' => $closed->id,
                'verified_at' => now(),
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ])->save();

            $this->audit->log(
                event: 'ticket.verified',
                subject: $ticket,
                newValues: [
                    'status' => 'closed',
                    'verified_by' => $actor->id,
                    'verification_method' => $verification->method,
                    'verified_at' => now()->toIso8601String(),
                ],
                description: $actor->username.' confirmed and closed '.$ticket->reference,
            );

            $this->audit->log(
                event: 'ticket.closed',
                subject: $ticket,
                newValues: ['status' => 'closed', 'closed_by' => $actor->id],
                description: $actor->username.' closed '.$ticket->reference,
            );

            return ['ticket' => $ticket->fresh() ?? $ticket];
        });

        if (($outcome['error'] ?? null) === 'invalid' || ! isset($outcome['ticket']) || ! $outcome['ticket'] instanceof Ticket) {
            throw ValidationException::withMessages([
                'decision' => [self::INVALID_MESSAGE],
            ]);
        }

        $closed = $outcome['ticket'];

        if (! ($outcome['already_closed'] ?? false)) {
            try {
                $this->notifications->ticketClosed($closed, $actor);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $closed;
    }

    public function reject(Ticket $ticket, User $actor): Ticket
    {
        $outcome = DB::transaction(function () use ($ticket, $actor) {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->loadMissing(['status', 'requester']);

            $latest = $ticket->verifications()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $verification = $this->pendingVerification($ticket, $latest);

            if (! $verification instanceof TicketVerification
                || ! $this->ticketIsAwaitingVerification($ticket)
            ) {
                return ['error' => 'invalid'];
            }

            $awaitingUser = TicketStatus::query()->where('slug', 'awaiting-user')->first();

            if (! $awaitingUser) {
                return ['error' => 'invalid'];
            }

            $verification->forceFill([
                'status' => VerificationStatus::Cancelled,
                'blocked_until' => null,
            ])->save();

            $ticket->forceFill([
                'status_id' => $awaitingUser->id,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution_summary' => $ticket->resolution_summary,
                'is_reopened' => true,
                'reopen_count' => (int) $ticket->reopen_count + 1,
            ])->save();

            $this->audit->log(
                event: 'ticket.verification_rejected',
                subject: $ticket,
                newValues: [
                    'status' => 'awaiting-user',
                    'rejected_by' => $actor->id,
                ],
                description: $actor->username.' rejected the resolution for '.$ticket->reference,
            );

            return ['ticket' => $ticket->fresh() ?? $ticket];
        });

        if (($outcome['error'] ?? null) === 'invalid' || ! isset($outcome['ticket']) || ! $outcome['ticket'] instanceof Ticket) {
            throw ValidationException::withMessages([
                'decision' => [self::INVALID_MESSAGE],
            ]);
        }

        $reopened = $outcome['ticket'];

        try {
            $this->notifications->informationRequested($reopened, $actor);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $reopened;
    }

    /**
     * @deprecated Use respond() with a decision instead.
     */
    public function verify(Ticket $ticket, User $actor, string $code = ''): Ticket
    {
        return $this->confirm($ticket, $actor);
    }

    /**
     * @return array{
     *     status: string,
     *     requested_at: string|null,
     *     completed_at: string|null,
     *     attempts: int,
     *     expires_at: string|null,
     *     is_blocked: bool,
     *     method: string|null,
     *     code: string|null
     * }|null
     */
    public function metadata(Ticket $ticket): ?array
    {
        $verification = $ticket->verifications
            ->sortByDesc('id')
            ->first();

        if (! $verification instanceof TicketVerification) {
            $verification = $ticket->verifications()->orderByDesc('id')->first();
        }

        if (! $verification instanceof TicketVerification) {
            return null;
        }

        $status = $verification->status instanceof VerificationStatus
            ? $verification->status
            : VerificationStatus::tryFrom((string) $verification->status);

        if ($status === VerificationStatus::Pending && $this->confirmationHasExpired($verification)) {
            $status = VerificationStatus::Expired;
        }

        return [
            'status' => $status?->value ?? 'pending',
            'requested_at' => $verification->issued_at?->toIso8601String(),
            'completed_at' => $verification->verified_at?->toIso8601String(),
            'attempts' => (int) $verification->attempts,
            'expires_at' => $verification->expires_at?->toIso8601String(),
            'is_blocked' => false,
            'method' => $verification->method,
            'code' => null,
        ];
    }

    /**
     * Confirmation never expires when ttl_minutes is 0 (or unset).
     */
    private function confirmationHasExpired(TicketVerification $verification): bool
    {
        $ttl = (int) config('helpdesk.verification.ttl_minutes', 0);

        if ($ttl <= 0) {
            return false;
        }

        return $verification->isExpired();
    }

    private function pendingVerification(Ticket $ticket, mixed $latest): ?TicketVerification
    {
        if ($latest instanceof TicketVerification && $latest->status === VerificationStatus::Pending) {
            return $latest;
        }

        return $ticket->verifications()
            ->pending()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function alreadyVerifiedBy(Ticket $ticket, User $actor, mixed $latest): bool
    {
        if (! $this->ticketIsAlreadyClosed($ticket, $latest)) {
            return false;
        }

        $isRequester = (int) $ticket->requester_id === (int) $actor->id;
        $verifiedByActor = $latest instanceof TicketVerification
            && $this->verificationIsVerified($latest)
            && (int) $latest->verified_by === (int) $actor->id;

        return $isRequester || $verifiedByActor;
    }

    private function ticketIsAlreadyClosed(Ticket $ticket, mixed $latest): bool
    {
        $slug = strtolower(trim((string) ($ticket->status?->slug ?? '')));

        if (in_array($slug, ['closed', 'cancelled'], true)) {
            return true;
        }

        if ($ticket->status?->is_closed) {
            return true;
        }

        if ($ticket->closed_at !== null || $ticket->verified_at !== null) {
            return true;
        }

        return $this->verificationIsVerified($latest);
    }

    private function verificationIsVerified(mixed $verification): bool
    {
        if (! $verification instanceof TicketVerification) {
            return false;
        }

        if ($verification->status === VerificationStatus::Verified) {
            return true;
        }

        $raw = $verification->getRawOriginal('status') ?? $verification->status;
        $value = $raw instanceof VerificationStatus
            ? $raw->value
            : strtolower(trim((string) $raw));

        return $value === VerificationStatus::Verified->value;
    }

    private function ticketIsAwaitingVerification(Ticket $ticket): bool
    {
        $status = $ticket->status;

        if ($status === null || $status->is_closed) {
            return false;
        }

        $slug = strtolower(trim((string) $status->slug));

        if (in_array($slug, ['closed', 'cancelled'], true)) {
            return false;
        }

        if ($slug === 'resolved') {
            return true;
        }

        return (bool) $status->is_resolved
            && (bool) $status->requires_verification
            && ! $status->is_closed;
    }
}
