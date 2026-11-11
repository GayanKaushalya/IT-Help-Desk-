import { Badge } from '@/components/ui/Badge'
import type { TicketPriority, TicketStatus } from '@/types/models'

export function TicketStatusBadge({
  status,
  awaitingVerification,
}: {
  status?: TicketStatus
  awaitingVerification?: boolean
}) {
  if (!status) return <span className="text-sm text-surface-400">—</span>

  const awaiting =
    awaitingVerification ??
    Boolean(status.is_resolved && status.requires_verification && !status.is_closed)

  return (
    <Badge tone={awaiting ? 'warning' : status.color_token} withDot>
      {awaiting ? 'Awaiting Verification' : status.name}
    </Badge>
  )
}

export function TicketPriorityBadge({ priority }: { priority?: TicketPriority }) {
  if (!priority) return <span className="text-sm text-surface-400">—</span>

  return <Badge tone={priority.color_token}>{priority.name}</Badge>
}
