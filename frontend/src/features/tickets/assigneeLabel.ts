import type { Ticket } from '@/types/models'

export function assigneeLabel(ticket: Ticket): string {
  if (ticket.assigned_to_team || (ticket.assignees?.length ?? 0) > 1) {
    return 'All IT staff'
  }

  return ticket.assignee?.full_name ?? ticket.assignees?.[0]?.full_name ?? 'Unassigned'
}
