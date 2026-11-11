import { Badge } from '@/components/ui/Badge'
import type { AccountStatus } from '@/types/models'

export function accountStatusLabel(status?: AccountStatus, isActive?: boolean, isApproved?: boolean): string {
  if (status === 'pending' || isApproved === false) return 'Pending'
  if (status === 'inactive' || isActive === false) return 'Inactive'
  return 'Active'
}

export function AccountStatusBadge({
  status,
  isActive,
  isApproved,
}: {
  status?: AccountStatus
  isActive?: boolean
  isApproved?: boolean
}) {
  const label = accountStatusLabel(status, isActive, isApproved)
  const tone = label === 'Active' ? 'success' : label === 'Pending' ? 'warning' : 'danger'

  return (
    <Badge tone={tone} withDot>
      {label}
    </Badge>
  )
}
