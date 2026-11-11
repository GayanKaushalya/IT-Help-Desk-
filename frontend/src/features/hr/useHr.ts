import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { hrService, type HrRequestListParams } from '@/services/hrService'

export const hrKeys = {
  all: ['hr'] as const,
  lookups: ['hr', 'lookups'] as const,
  dashboard: ['hr', 'dashboard'] as const,
  list: (params: HrRequestListParams) => ['hr', 'requests', 'list', params] as const,
  detail: (id: string) => ['hr', 'requests', 'detail', id] as const,
  track: (reference: string) => ['hr', 'requests', 'track', reference] as const,
}

export function useHrEmployeeDashboard() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrKeys.dashboard,
    queryFn: hrService.dashboard,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useHrLookups() {
  return useQuery({
    queryKey: hrKeys.lookups,
    queryFn: hrService.lookups,
    staleTime: 5 * 60_000,
  })
}

export function useHrRequestList(params: HrRequestListParams) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrKeys.list(params),
    queryFn: () => hrService.index(params),
    placeholderData: keepPreviousData,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useHrRequest(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrKeys.detail(id ?? ''),
    queryFn: () => hrService.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useInvalidateEmployeeHr() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: hrKeys.dashboard })
    void queryClient.invalidateQueries({ queryKey: ['hr', 'requests'] })
    void queryClient.invalidateQueries({ queryKey: ['hr', 'staff'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }
}

export function useAddHrComment(requestId: string) {
  const invalidate = useInvalidateEmployeeHr()

  return useMutation({
    mutationFn: (body: string) => hrService.comment(requestId, body),
    onSuccess: invalidate,
  })
}

export function useVerifyHrRequest(requestId: string) {
  const invalidate = useInvalidateEmployeeHr()

  return useMutation({
    mutationFn: (decision: 'confirmed' | 'rejected') => hrService.verify(requestId, decision),
    onSuccess: invalidate,
  })
}
