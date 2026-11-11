import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { hrService, type HrAuditLogParams } from '@/services/hrService'

export const hrAuditKeys = {
  lookups: ['hr', 'staff', 'audit-logs', 'lookups'] as const,
  list: (params: HrAuditLogParams) => ['hr', 'staff', 'audit-logs', 'list', params] as const,
}

export function useHrAuditLookups() {
  return useQuery({
    queryKey: hrAuditKeys.lookups,
    queryFn: hrService.staff.auditLookups,
    staleTime: 10 * 60_000,
  })
}

export function useHrAuditLogs(params: HrAuditLogParams) {
  return useQuery({
    queryKey: hrAuditKeys.list(params),
    queryFn: () => hrService.staff.auditLogs(params),
    placeholderData: keepPreviousData,
  })
}
