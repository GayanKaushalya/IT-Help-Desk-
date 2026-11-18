import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { administrationService, type AdmRequestListParams } from '@/services/administrationService'

export const admKeys = {
  all: ['administration'] as const,
  lookups: ['administration', 'lookups'] as const,
  dashboard: ['administration', 'dashboard'] as const,
  list: (params: AdmRequestListParams) => ['administration', 'requests', 'list', params] as const,
  detail: (id: string) => ['administration', 'requests', 'detail', id] as const,
  track: (reference: string) => ['administration', 'requests', 'track', reference] as const,
}

export function useAdmEmployeeDashboard() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admKeys.dashboard,
    queryFn: administrationService.dashboard,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useAdmLookups() {
  return useQuery({
    queryKey: admKeys.lookups,
    queryFn: administrationService.lookups,
    staleTime: 5 * 60_000,
  })
}

export function useAdmRequestList(params: AdmRequestListParams) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admKeys.list(params),
    queryFn: () => administrationService.index(params),
    placeholderData: keepPreviousData,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useAdmRequest(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admKeys.detail(id ?? ''),
    queryFn: () => administrationService.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useInvalidateEmployeeAdm() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: admKeys.dashboard })
    void queryClient.invalidateQueries({ queryKey: ['administration', 'requests'] })
    void queryClient.invalidateQueries({ queryKey: ['administration', 'staff'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }
}

export function useAddAdmComment(requestId: string) {
  const invalidate = useInvalidateEmployeeAdm()

  return useMutation({
    mutationFn: (body: string) => administrationService.comment(requestId, body),
    onSuccess: invalidate,
  })
}

export function useVerifyAdmRequest(requestId: string) {
  const invalidate = useInvalidateEmployeeAdm()

  return useMutation({
    mutationFn: (decision: 'confirmed' | 'rejected') => administrationService.verify(requestId, decision),
    onSuccess: invalidate,
  })
}
