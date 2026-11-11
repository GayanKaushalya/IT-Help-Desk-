import { apiClient } from '@/services/apiClient'
import type { DashboardOverview } from '@/types/models'

export const dashboardService = {
  overview: (): Promise<DashboardOverview> => apiClient.get<DashboardOverview>('/dashboard'),
}
