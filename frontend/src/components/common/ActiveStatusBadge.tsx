import { Badge } from '@/components/ui/Badge'

export function ActiveStatusBadge({ isActive }: { isActive: boolean }) {
  return (
    <Badge tone={isActive ? 'success' : 'danger'} withDot>
      {isActive ? 'Active' : 'Inactive'}
    </Badge>
  )
}
