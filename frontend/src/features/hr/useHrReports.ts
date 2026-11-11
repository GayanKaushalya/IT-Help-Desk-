import { useQuery } from '@tanstack/react-query'

import { hrService, type HrReportParams } from '@/services/hrService'

export const hrReportKeys = {
  all: ['hr', 'staff', 'reports'] as const,
  summary: (params: HrReportParams) => ['hr', 'staff', 'reports', 'summary', params] as const,
}

export function useHrReports(params: HrReportParams, enabled = true) {
  return useQuery({
    queryKey: hrReportKeys.summary(params),
    queryFn: () => hrService.staff.reports(params),
    enabled,
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
