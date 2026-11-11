import { useQuery } from '@tanstack/react-query'

import { adminService, type AdminReportParams } from '@/services/adminService'

export const adminReportKeys = {
  all: ['admin', 'reports'] as const,
  summary: (params: AdminReportParams) => ['admin', 'reports', 'summary', params] as const,
}

export function useAdminReports(params: AdminReportParams) {
  return useQuery({
    queryKey: adminReportKeys.summary(params),
    queryFn: () => adminService.reports(params),
  })
}

export function triggerBrowserDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
