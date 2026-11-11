import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { adminStatusLabel } from '@/features/admin/adminLabels'
import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import { TicketMobileCard } from '@/features/tickets/TicketMobileCard'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { paths } from '@/routes/paths'
import type { Ticket } from '@/types/models'
import { formatDateTime, formatDuration, resolveTimeMinutes } from '@/utils/format'
import { Badge } from '@/components/ui/Badge'

export function AdminTicketStatusCell({ ticket }: { ticket: Ticket }) {
  if (!ticket.status) return <span className="text-sm text-surface-400">—</span>

  const label = adminStatusLabel(ticket.status, ticket.awaiting_verification)
  if (label === ticket.status.name) {
    return <TicketStatusBadge status={ticket.status} />
  }

  return (
    <Badge tone={label === 'Awaiting Verification' ? 'warning' : ticket.status.color_token} withDot>
      {label}
    </Badge>
  )
}

function columnsFor(onOpen: (ticket: Ticket) => void): DataTableColumn<Ticket>[] {
  return [
  {
    id: 'reference',
    header: 'Ticket #',
    cell: (ticket) => (
      <span className="font-mono text-xs font-medium text-primary-700">{ticket.reference}</span>
    ),
  },
  {
    id: 'subject',
    header: 'Subject',
    cell: (ticket) => <span className="font-medium text-surface-900">{ticket.subject}</span>,
    className: 'min-w-[14rem]',
  },
  {
    id: 'employee',
    header: 'Employee',
    cell: (ticket) => ticket.requester?.full_name ?? '—',
    className: 'hidden md:table-cell',
  },
  {
    id: 'branch',
    header: 'Branch',
    cell: (ticket) => ticket.branch?.name ?? '—',
    className: 'hidden xl:table-cell',
  },
  {
    id: 'priority',
    header: 'Priority',
    cell: (ticket) => <TicketPriorityBadge priority={ticket.priority} />,
    className: 'hidden sm:table-cell',
  },
  {
    id: 'status',
    header: 'Status',
    cell: (ticket) => <AdminTicketStatusCell ticket={ticket} />,
  },
  {
    id: 'assignee',
    header: 'Assigned To',
    cell: (ticket) => assigneeLabel(ticket),
    className: 'hidden lg:table-cell',
  },
  {
    id: 'created',
    header: 'Created',
    cell: (ticket) => formatDateTime(ticket.created_at),
    className: 'hidden lg:table-cell',
  },
  {
    id: 'resolve_time',
    header: 'Resolve time',
    cell: (ticket) => {
      const minutes = resolveTimeMinutes(ticket.created_at, ticket.resolved_at)
      return (
        <span className="text-sm text-surface-700" title={ticket.resolved_at ? formatDateTime(ticket.resolved_at) : undefined}>
          {formatDuration(minutes)}
        </span>
      )
    },
    className: 'hidden lg:table-cell whitespace-nowrap',
  },
  {
    id: 'actions',
    header: 'Actions',
    align: 'right',
    cell: (ticket) => (
      <Button
        size="sm"
        variant="outline"
        onClick={(event) => {
          event.stopPropagation()
          onOpen(ticket)
        }}
      >
        Open
      </Button>
    ),
  },
]
}

export function AdminTicketTable({
  rows,
  isLoading,
  errorMessage,
  onRetry,
  emptyTitle,
  emptyDescription,
}: {
  rows: Ticket[]
  isLoading: boolean
  errorMessage: string | null
  onRetry: () => void
  emptyTitle: string
  emptyDescription: string
}) {
  const navigate = useNavigate()
  const openTicket = (ticket: Ticket) => void navigate(paths.admin.ticketDetail(ticket.id))

  return (
    <DataTable
      columns={columnsFor(openTicket)}
      rows={rows}
      rowKey={(ticket) => ticket.id}
      isLoading={isLoading}
      errorMessage={errorMessage}
      onRetry={onRetry}
      onRowClick={openTicket}
      emptyTitle={emptyTitle}
      emptyDescription={emptyDescription}
      mobileCard={(ticket) => (
        <TicketMobileCard ticket={ticket} showRequester onOpen={() => openTicket(ticket)} />
      )}
    />
  )
}
