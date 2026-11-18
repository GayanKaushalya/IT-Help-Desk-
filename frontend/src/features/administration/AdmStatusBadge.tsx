import { AlarmClock } from 'lucide-react'

import { Badge } from '@/components/ui/Badge'
import type { AdmRequest, AdmStatus } from '@/types/models'

export function AdmStatusBadge({
  status,
  awaitingVerification,
}: {
  status?: AdmStatus | null
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

/** Small flags shown beside the reference, e.g. overdue. */
export function AdmRequestFlags({ request }: { request: Pick<AdmRequest, 'is_overdue'> }) {
  if (!request.is_overdue) return null

  return (
    <span className="inline-flex flex-wrap items-center gap-1.5">
      <Badge tone="warning" size="sm">
        <AlarmClock className="size-3" aria-hidden />
        Overdue
      </Badge>
    </span>
  )
}
