import { AlarmClock, Lock, TriangleAlert } from 'lucide-react'

import { Badge } from '@/components/ui/Badge'
import type { HrRequest, HrStatus } from '@/types/models'

export function HrStatusBadge({
  status,
  awaitingVerification,
}: {
  status?: HrStatus | null
  awaitingVerification?: boolean
}) {
  if (!status) return <span className="text-sm text-surface-400">&mdash;</span>

  const awaiting = Boolean(awaitingVerification)

  return (
    <Badge tone={awaiting ? 'warning' : status.color_token} withDot>
      {awaiting ? 'Awaiting Verification' : status.name}
    </Badge>
  )
}

/** Small flags shown beside the reference: confidential, overdue, escalated. */
export function HrRequestFlags({
  request,
}: {
  request: Pick<HrRequest, 'is_confidential' | 'is_overdue' | 'escalated_at'>
}) {
  if (!request.is_confidential && !request.is_overdue && !request.escalated_at) return null

  return (
    <span className="inline-flex flex-wrap items-center gap-1.5">
      {request.is_confidential && (
        <Badge tone="danger" size="sm">
          <Lock className="size-3" aria-hidden />
          Confidential
        </Badge>
      )}
      {request.is_overdue && (
        <Badge tone="warning" size="sm">
          <AlarmClock className="size-3" aria-hidden />
          Overdue
        </Badge>
      )}
      {request.escalated_at && (
        <Badge tone="danger" size="sm">
          <TriangleAlert className="size-3" aria-hidden />
          Escalated
        </Badge>
      )}
    </span>
  )
}
