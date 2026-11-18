import type { AdmRequest } from '@/types/models'

export function admAssigneeLabel(request: AdmRequest): string {
  if (request.assigned_to_team || (request.assignments?.filter((row) => !row.released_at).length ?? 0) > 1) {
    return 'All Administration staff'
  }

  return request.assignee?.full_name ?? 'Unassigned'
}
