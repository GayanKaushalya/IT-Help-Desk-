import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import type { Ticket } from '@/types/models'
import { formatDateTime, formatDuration, resolveTimeMinutes } from '@/utils/format'

export function TicketMobileCard({
  ticket,
  onOpen,
  showRequester = false,
}: {
  ticket: Ticket
  onOpen: () => void
  showRequester?: boolean
}) {
  return (
    <button
      type="button"
      onClick={onOpen}
      className="w-full rounded-xl border border-surface-200 bg-panel p-4 text-left shadow-sm transition-colors hover:border-primary-200"
    >
      <div className="flex items-start justify-between gap-3">
        <span className="font-mono text-xs font-medium text-primary-700">{ticket.reference}</span>
        <TicketStatusBadge status={ticket.status} />
      </div>
      <p className="mt-2 text-sm font-medium text-surface-900">{ticket.subject}</p>
      <p className="mt-2 text-xs text-surface-500">
        {showRequester ? (ticket.requester?.full_name ?? '—') : (ticket.category?.name ?? '—')}
        {' · '}
        {ticket.priority?.name ?? '—'}
        {' · '}
        {assigneeLabel(ticket)}
      </p>
      <div className="mt-2 flex items-center justify-between gap-2">
        <TicketPriorityBadge priority={ticket.priority} />
        <p className="text-xs text-surface-400">
          {ticket.resolved_at
            ? `Resolved in ${formatDuration(resolveTimeMinutes(ticket.created_at, ticket.resolved_at))}`
            : `Updated ${formatDateTime(ticket.updated_at)}`}
        </p>
      </div>
    </button>
  )
}
