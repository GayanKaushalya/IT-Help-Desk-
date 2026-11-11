<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates employee-raised tickets and writes the matching audit trail.
 *
 * Ownership, status and the public ticket number are assigned here so the
 * request body cannot choose them.
 */
class TicketService
{
    public function __construct(
        private readonly TicketReferenceGenerator $references,
        private readonly TicketAttachmentService $attachments,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
        private readonly TicketWorkflowService $workflow,
    ) {}

    /**
     * @param  array{
     *     subject: string,
     *     category_id: int,
     *     priority_id: int,
     *     description: string,
     *     preferred_contact_method: string|null,
     *     contact_phone: string,
     *     branch_id: int,
     *     contact_location: string|null,
     *     additional_details: string|null,
     *     issue_type: string|null,
     *     item_type: string|null,
     *     item_other: string|null
     * }  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $user, array $data, array $files = []): Ticket
    {
        $category = TicketCategory::query()->active()->whereKey($data['category_id'])->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category is not available.'],
            ]);
        }

        $priority = TicketPriority::query()->active()->whereKey($data['priority_id'])->first();

        if (! $priority) {
            throw ValidationException::withMessages([
                'priority_id' => ['The selected priority is not available.'],
            ]);
        }

        $status = TicketStatus::query()->active()->default()->where('is_open', true)->first();

        if (! $status) {
            throw new RuntimeException('The default open ticket status has not been seeded.');
        }

        $ticket = DB::transaction(function () use ($user, $data, $files, $category, $priority, $status) {
            $ticket = $this->insertWithUniqueReference($user, $data, $category, $priority, $status);

            if ($files !== []) {
                $this->attachments->storeMany($ticket, $user, $files);
            }

            return $ticket;
        });

        $this->audit->log(
            event: 'ticket.created',
            subject: $ticket,
            newValues: [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'category_id' => $ticket->category_id,
                'priority_id' => $ticket->priority_id,
                'status_id' => $ticket->status_id,
            ],
            description: $user->username.' submitted ticket '.$ticket->reference,
        );

        $ticket = $this->workflow->assignToAllStaff($ticket, $user);

        $ticket->loadMissing('requester');
        $this->notifications->ticketCreated($ticket, $user);

        return $ticket->load(['category', 'priority', 'status', 'assignee', 'requester', 'branch', 'assignments.assignee', 'attachments']);
    }

    /**
     * @param  array{
     *     subject: string,
     *     category_id: int,
     *     priority_id: int,
     *     description: string,
     *     preferred_contact_method: string|null,
     *     contact_phone: string,
     *     branch_id: int,
     *     contact_location: string|null,
     *     additional_details: string|null,
     *     issue_type: string|null,
     *     item_type: string|null,
     *     item_other: string|null
     * }  $data
     */
    private function insertWithUniqueReference(
        User $user,
        array $data,
        TicketCategory $category,
        TicketPriority $priority,
        TicketStatus $status,
    ): Ticket {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return Ticket::query()->create([
                    'reference' => $this->references->next(),
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'requester_id' => $user->id,
                    'branch_id' => $data['branch_id'],
                    'department_id' => $user->getAttributes()['department_id'] ?? null,
                    'category_id' => $category->id,
                    'priority_id' => $priority->id,
                    'status_id' => $status->id,
                    'source' => 'web',
                    'contact_location' => $data['contact_location'],
                    'preferred_contact_method' => $data['preferred_contact_method'],
                    'contact_phone' => $data['contact_phone'],
                    'additional_details' => $data['additional_details'],
                    'issue_type' => $data['issue_type'],
                    'item_type' => $data['item_type'],
                    'item_other' => $data['item_other'],
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempts >= 3) {
                    throw $exception;
                }
            }
        }
    }
}
