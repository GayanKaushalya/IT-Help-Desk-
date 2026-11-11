<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample tickets for the local employee dashboard.
 *
 * Production deployments should leave SEED_DEMO_USERS unset so this seeder
 * is never invoked from DatabaseSeeder.
 */
class DemoTicketSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $employee = User::where('username', 'employee')->first();
        $colleague = User::where('username', 'itsupport')->first();

        if (! $employee) {
            $this->command?->warn('Demo tickets skipped: the employee account has not been seeded.');

            return;
        }

        $statuses = TicketStatus::pluck('id', 'slug');
        $priorities = TicketPriority::pluck('id', 'slug');
        $categories = TicketCategory::pluck('id', 'slug');

        $samples = [
            [
                'reference' => 'TKT-2026-100001',
                'subject' => 'Laptop will not start after the weekend',
                'description' => 'The docking station light is on but the laptop stays black after pressing the power button.',
                'category' => 'hardware',
                'priority' => 'high',
                'status' => 'new',
                'requester' => $employee,
                'assignee' => null,
            ],
            [
                'reference' => 'TKT-2026-100002',
                'subject' => 'Outlook cannot send mail',
                'description' => 'Messages sit in the Outbox and return "Cannot connect to the server".',
                'category' => 'email',
                'priority' => 'medium',
                'status' => 'in-progress',
                'requester' => $employee,
                'assignee' => $colleague,
            ],
            [
                'reference' => 'TKT-2026-100003',
                'subject' => 'VPN drops every few minutes',
                'description' => 'The corporate VPN connects, then disconnects after three to five minutes.',
                'category' => 'network',
                'priority' => 'high',
                'status' => 'assigned',
                'requester' => $employee,
                'assignee' => $colleague,
            ],
            [
                'reference' => 'TKT-2026-100004',
                'subject' => 'Access to the finance shared folder',
                'description' => 'IT granted access. Please confirm you can open the Q3 folder.',
                'category' => 'access-request',
                'priority' => 'medium',
                'status' => 'resolved',
                'requester' => $employee,
                'assignee' => $colleague,
            ],
            [
                'reference' => 'TKT-2026-100005',
                'subject' => 'Floor printer installed on the 3rd floor',
                'description' => 'The printer is installed and a test page printed successfully.',
                'category' => 'hardware',
                'priority' => 'low',
                'status' => 'closed',
                'requester' => $employee,
                'assignee' => $colleague,
            ],
        ];

        if ($colleague) {
            $samples[] = [
                'reference' => 'TKT-2026-199999',
                'subject' => 'Internal spare laptop request',
                'description' => 'This ticket belongs to another requester and must never appear on the employee dashboard.',
                'category' => 'hardware',
                'priority' => 'low',
                'status' => 'new',
                'requester' => $colleague,
                'assignee' => null,
            ];
        }

        foreach ($samples as $sample) {
            $ticket = Ticket::withTrashed()->firstOrNew(['reference' => $sample['reference']]);

            $ticket->fill([
                'subject' => $sample['subject'],
                'description' => $sample['description'],
                'requester_id' => $sample['requester']->id,
                'branch_id' => $sample['requester']->branch_id,
                'category_id' => $categories[$sample['category']] ?? $categories->first(),
                'priority_id' => $priorities[$sample['priority']] ?? $priorities->first(),
                'status_id' => $statuses[$sample['status']] ?? $statuses->first(),
                'assigned_to' => $sample['assignee']?->id,
                'assigned_at' => $sample['assignee'] ? now() : null,
                'source' => 'web',
            ]);

            $ticket->save();

            $this->applyLifecycle($ticket, $sample['status'], $sample['assignee'], $sample['requester']);
        }

        $this->command?->info('Demo tickets ready for the employee dashboard.');
    }

    private function applyLifecycle(Ticket $ticket, string $status, ?User $assignee, User $requester): void
    {
        $created = now()->subDays(match ($status) {
            'closed' => 10,
            'resolved' => 4,
            'in-progress' => 5,
            'assigned' => 3,
            default => 1,
        });

        $ticket->forceFill(['created_at' => $created])->save();

        if (! $assignee) {
            return;
        }

        TicketAssignment::query()->firstOrCreate(
            ['ticket_id' => $ticket->id, 'assigned_to' => $assignee->id, 'released_at' => null],
            [
                'assigned_by' => $assignee->id,
                'assigned_at' => $created->copy()->addHours(4),
            ],
        );

        $updates = [
            'assigned_at' => $created->copy()->addHours(4),
        ];

        if (in_array($status, ['in-progress', 'resolved', 'closed'], true)) {
            $updates['first_responded_at'] = $created->copy()->addDay();
        }

        if (in_array($status, ['resolved', 'closed'], true)) {
            $updates['resolved_at'] = $created->copy()->addDays(2);
            $updates['resolved_by'] = $assignee->id;
            $updates['resolution_summary'] = 'The reported issue has been addressed.';
        }

        if ($status === 'closed') {
            $updates['closed_at'] = $created->copy()->addDays(3);
            $updates['closed_by'] = $assignee->id;
            $updates['verified_at'] = $created->copy()->addDays(3);
        }

        $ticket->forceFill($updates)->save();

        if ($ticket->comments()->exists()) {
            return;
        }

        if ($status === 'in-progress') {
            $this->addComment($ticket, $assignee, 'We are checking the mail server logs and will update you shortly.', $created->copy()->addDay());
            $this->addComment($ticket, $requester, 'Thank you. I can still see messages stuck in the Outbox.', $created->copy()->addDay()->addHours(2));
        }

        if ($status === 'resolved') {
            $this->addComment($ticket, $assignee, 'Access to the finance share has been granted. Please try the Q3 folder and confirm.', $created->copy()->addDays(2));
            $this->addComment(
                $ticket,
                $assignee,
                'Internal: AD group FIN-SHARE-Q3 was added. Do not show this to the requester.',
                $created->copy()->addDays(2)->addMinutes(5),
                internal: true,
            );
        }
    }

    private function addComment(Ticket $ticket, User $author, string $body, $createdAt, bool $internal = false): void
    {
        $comment = new TicketComment;
        $comment->forceFill([
            'ticket_id' => $ticket->id,
            'author_id' => $author->id,
            'body' => $body,
            'is_internal' => $internal,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
