import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { hrService, type HrStaffRequestListParams } from '@/services/hrService'

export const hrStaffKeys = {
  dashboard: ['hr', 'staff', 'dashboard'] as const,
  agents: ['hr', 'staff', 'agents'] as const,
  requests: ['hr', 'staff', 'requests'] as const,
  queue: (params: HrStaffRequestListParams) => ['hr', 'staff', 'requests', 'queue', params] as const,
  assigned: (params: HrStaffRequestListParams) => ['hr', 'staff', 'requests', 'assigned', params] as const,
  detail: (id: string) => ['hr', 'staff', 'requests', 'detail', id] as const,
}

export function useHrStaffDashboard() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrStaffKeys.dashboard,
    queryFn: hrService.staff.dashboard,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useHrStaffAgents() {
  return useQuery({
    queryKey: hrStaffKeys.agents,
    queryFn: hrService.staff.agents,
    staleTime: 30_000,
  })
}

export function useHrStaffQueue(params: HrStaffRequestListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrStaffKeys.queue(params),
    queryFn: () => hrService.staff.requests(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useHrStaffAssigned(params: HrStaffRequestListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrStaffKeys.assigned(params),
    queryFn: () => hrService.staff.assigned(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useHrStaffRequest(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: hrStaffKeys.detail(id ?? ''),
    queryFn: () => hrService.staff.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

function useInvalidateHrStaffRequest(requestId: string) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: hrStaffKeys.detail(requestId) })
    void queryClient.invalidateQueries({ queryKey: hrStaffKeys.requests })
    void queryClient.invalidateQueries({ queryKey: hrStaffKeys.dashboard })
    void queryClient.invalidateQueries({ queryKey: ['hr', 'requests'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }
}

export function useAssignHrRequest(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ assignedTo, note }: { assignedTo: number; note?: string }) =>
      hrService.staff.assign(requestId, assignedTo, note),
    onSuccess: invalidate,
  })
}

export function useUpdateHrRequestStatus(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ statusId, remarks }: { statusId: number; remarks?: string }) =>
      hrService.staff.updateStatus(requestId, statusId, remarks),
    onSuccess: invalidate,
  })
}

export function useResolveHrRequest(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ summary, close }: { summary: string; close: boolean }) =>
      hrService.staff.resolve(requestId, summary, close),
    onSuccess: invalidate,
  })
}

export function useCloseHrRequestAsVerified(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: () => hrService.staff.closeAsVerified(requestId),
    onSuccess: invalidate,
  })
}

export function useReferHrRequest(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: (payload: { department: string; brief?: string; assigned_to?: number }) =>
      hrService.staff.refer(requestId, payload),
    onSuccess: invalidate,
  })
}

export function useRespondToHrReferral(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ referralId, response }: { referralId: number; response: string }) =>
      hrService.staff.respondToReferral(requestId, referralId, response),
    onSuccess: invalidate,
  })
}

export function useHrStaffComment(requestId: string) {
  const invalidate = useInvalidateHrStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ body, isInternal }: { body: string; isInternal: boolean }) =>
      hrService.staff.comment(requestId, body, isInternal),
    onSuccess: invalidate,
  })
}
