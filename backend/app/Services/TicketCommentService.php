<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Public conversation on a ticket. Internal notes cannot be created here.
 */
class TicketCommentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function addPublicComment(Ticket $ticket, User $author, string $body): TicketComment
    {
        $ticket->loadMissing('status');

        if ($ticket->status?->is_closed) {
            throw ValidationException::withMessages([
                'body' => ['This ticket is closed and can no longer receive replies.'],
            ]);
        }

        $comment = $ticket->comments()->create([
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => false,
            'is_system' => false,
        ]);

        $this->audit->log(
            event: 'ticket.commented',
            subject: $ticket,
            newValues: ['comment_id' => $comment->id],
            description: $author->username.' commented on '.$ticket->reference,
        );

        if ($author->isStaff()) {
            $this->notifications->staffCommented($ticket, $author);
        } else {
            $this->notifications->requesterReplied($ticket, $author);
        }

        return $comment->load(['author.role', 'attachments']);
    }

    public function addInternalNote(Ticket $ticket, User $author, string $body): TicketComment
    {
        $ticket->loadMissing('status');

        if ($ticket->status?->is_closed) {
            throw ValidationException::withMessages([
                'body' => ['This ticket is closed and can no longer receive notes.'],
            ]);
        }

        $comment = $ticket->comments()->create([
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => true,
            'is_system' => false,
        ]);

        $this->audit->log(
            event: 'ticket.internal_note',
            subject: $ticket,
            newValues: ['comment_id' => $comment->id],
            description: $author->username.' added an internal note on '.$ticket->reference,
        );

        return $comment->load(['author.role', 'attachments']);
    }
}
