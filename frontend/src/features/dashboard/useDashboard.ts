import { useQuery } from '@tanstack/react-query'

import { useTicket } from '@/features/tickets/useTickets'
import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { dashboardService } from '@/services/dashboardService'

export const dashboardKeys = {
  overview: ['dashboard', 'overview'] as const,
  ticket: (id: string) => ['tickets', id] as const,
}

export function useDashboardOverview() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: dashboardKeys.overview,
    queryFn: dashboardService.overview,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export { useTicket }
