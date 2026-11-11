import type { HrRequest } from '@/types/models'

export function hrAssigneeLabel(request: HrRequest): string {
  if (request.assigned_to_team || (request.assignments?.filter((row) => !row.released_at).length ?? 0) > 1) {
    return 'All HR staff'
  }

  return request.assignee?.full_name ?? 'Unassigned'
}
