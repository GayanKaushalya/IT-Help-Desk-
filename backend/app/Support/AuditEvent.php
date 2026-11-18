<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Branch;
use App\Models\AdmRequest;
use App\Models\HrRequest;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;

/**
 * Canonical audit event catalogue used by the admin browser.
 */
final class AuditEvent
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'auth.login' => 'Login',
            'auth.logout' => 'Logout',
            'auth.login_failed' => 'Failed login',
            'auth.login_blocked' => 'Blocked login',
            'auth.register' => 'Registration',
            'user.created' => 'User created',
            'user.updated' => 'User modified',
            'user.activated' => 'Account activated',
            'user.deactivated' => 'Account deactivated',
            'user.password_reset' => 'Password reset',
            'ticket.created' => 'Ticket created',
            'ticket.assigned' => 'Ticket assigned',
            'ticket.status_changed' => 'Ticket status changed',
            'ticket.priority_changed' => 'Priority changed',
            'ticket.commented' => 'Comment added',
            'ticket.internal_note' => 'Internal note added',
            'ticket.attachment_added' => 'Attachment added',
            'ticket.resolved' => 'Ticket resolved',
            'ticket.verification_issued' => 'Verification requested',
            'ticket.verification_failed' => 'Verification attempt failed',
            'ticket.verification_rejected' => 'Resolution rejected by requester',
            'ticket.verified' => 'Verification completed',
            'ticket.closed' => 'Ticket closed',
            'branch.created' => 'Branch created',
            'branch.updated' => 'Branch modified',
            'branch.activated' => 'Branch activated',
            'branch.deactivated' => 'Branch deactivated',
            'report.exported' => 'Report exported',
            'hr.report.exported' => 'HR report exported',
            'hr_request.created' => 'HR request created',
            'hr_request.assigned' => 'HR request assigned',
            'hr_request.status_changed' => 'HR request status changed',
            'hr_request.referred' => 'HR request referred',
            'hr_request.referral_responded' => 'HR referral responded',
            'adm_request.created' => 'Administration request created',
            'adm_request.assigned' => 'Administration request assigned',
            'adm_request.status_changed' => 'Administration request status changed',
            'adm_request.resolved' => 'Administration request resolved',
            'adm_request.verified' => 'Administration request verified',
            'adm_request.closed' => 'Administration request closed',
            'adm_request.verification_rejected' => 'Administration resolution rejected',
            'adm_request.comment_added' => 'Administration request comment added',
            'adm_request.internal_note_added' => 'Administration internal note added',
            'adm_request.attachments_added' => 'Administration attachments added',
            'user.profile_updated' => 'Profile updated',
            'user.password_changed' => 'Password changed',
            'category.created' => 'Category created',
            'category.updated' => 'Category modified',
            'priority.created' => 'Priority created',
            'priority.updated' => 'Priority modified',
            'status.updated' => 'Status modified',
            'settings.updated' => 'Settings updated',
            'knowledge.created' => 'Knowledge article created',
            'knowledge.updated' => 'Knowledge article modified',
        ];
    }

    /**
     * HR help-desk audit catalogue (subset shown in the HR admin browser).
     *
     * @return array<string, string>
     */
    public static function hrLabels(): array
    {
        return array_filter(
            self::labels(),
            fn (string $event): bool => str_starts_with($event, 'hr.') || str_starts_with($event, 'hr_request.'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function hrOptions(): array
    {
        $options = [];

        foreach (self::hrLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::labels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public static function label(string $event): string
    {
        return self::labels()[$event] ?? $event;
    }

    /**
     * @return array<string, class-string|null>
     */
    public static function entityTypes(): array
    {
        return [
            'user' => User::class,
            'ticket' => Ticket::class,
            'hr_request' => HrRequest::class,
            'adm_request' => AdmRequest::class,
            'branch' => Branch::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'knowledge' => KnowledgeArticle::class,
        ];
    }

    public static function entityKey(?string $auditableType, string $event): string
    {
        if (str_starts_with($event, 'auth.')) {
            return 'auth';
        }

        if (str_starts_with($event, 'report.') || str_starts_with($event, 'hr.report.')) {
            return 'report';
        }

        if (str_starts_with($event, 'hr_request.') || str_starts_with($event, 'hr.')) {
            return 'hr_request';
        }

        if (str_starts_with($event, 'adm_request.')) {
            return 'adm_request';
        }

        if (str_starts_with($event, 'settings.')) {
            return 'settings';
        }

        if (str_starts_with($event, 'knowledge.')) {
            return 'knowledge';
        }

        foreach (self::entityTypes() as $key => $class) {
            if ($class !== null && $auditableType === $class) {
                return $key;
            }
        }

        if ($auditableType) {
            $basename = class_basename($auditableType);

            return strtolower($basename);
        }

        return 'system';
    }
}
