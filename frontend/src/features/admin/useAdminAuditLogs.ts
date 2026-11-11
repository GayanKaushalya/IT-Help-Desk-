import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { adminService, type AdminAuditLogParams } from '@/services/adminService'

export const adminAuditKeys = {
  lookups: ['admin', 'audit-logs', 'lookups'] as const,
  list: (params: AdminAuditLogParams) => ['admin', 'audit-logs', 'list', params] as const,
}

export function useAdminAuditLookups() {
  return useQuery({
    queryKey: adminAuditKeys.lookups,
    queryFn: adminService.auditLookups,
    staleTime: 10 * 60_000,
  })
}

export function useAdminAuditLogs(params: AdminAuditLogParams) {
  return useQuery({
    queryKey: adminAuditKeys.list(params),
    queryFn: () => adminService.auditLogs(params),
    placeholderData: keepPreviousData,
  })
}
