import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { administrationService, type AdmStaffRequestListParams } from '@/services/administrationService'

export const admStaffKeys = {
  dashboard: ['administration', 'staff', 'dashboard'] as const,
  agents: ['administration', 'staff', 'agents'] as const,
  requests: ['administration', 'staff', 'requests'] as const,
  queue: (params: AdmStaffRequestListParams) => ['administration', 'staff', 'requests', 'queue', params] as const,
  assigned: (params: AdmStaffRequestListParams) => ['administration', 'staff', 'requests', 'assigned', params] as const,
  detail: (id: string) => ['administration', 'staff', 'requests', 'detail', id] as const,
}

export function useAdmStaffDashboard() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admStaffKeys.dashboard,
    queryFn: administrationService.staff.dashboard,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useAdmStaffAgents() {
  return useQuery({
    queryKey: admStaffKeys.agents,
    queryFn: administrationService.staff.agents,
    staleTime: 30_000,
  })
}

export function useAdmStaffQueue(params: AdmStaffRequestListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admStaffKeys.queue(params),
    queryFn: () => administrationService.staff.requests(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useAdmStaffAssigned(params: AdmStaffRequestListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admStaffKeys.assigned(params),
    queryFn: () => administrationService.staff.assigned(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useAdmStaffRequest(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: admStaffKeys.detail(id ?? ''),
    queryFn: () => administrationService.staff.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

function useInvalidateAdmStaffRequest(requestId: string) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: admStaffKeys.detail(requestId) })
    void queryClient.invalidateQueries({ queryKey: admStaffKeys.requests })
    void queryClient.invalidateQueries({ queryKey: admStaffKeys.dashboard })
    void queryClient.invalidateQueries({ queryKey: ['administration', 'requests'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }
}

export function useAssignAdmRequest(requestId: string) {
  const queryClient = useQueryClient()
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ assignedTo, note }: { assignedTo: number; note?: string }) =>
      administrationService.staff.assign(requestId, assignedTo, note),
    onSuccess: () => {
      invalidate()
      void queryClient.invalidateQueries({ queryKey: admStaffKeys.agents })
    },
  })
}

export function useUpdateAdmRequestStatus(requestId: string) {
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ statusId, remarks }: { statusId: number; remarks?: string }) =>
      administrationService.staff.updateStatus(requestId, statusId, remarks),
    onSuccess: invalidate,
  })
}

export function useResolveAdmRequest(requestId: string) {
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ summary, close }: { summary: string; close: boolean }) =>
      administrationService.staff.resolve(requestId, summary, close),
    onSuccess: invalidate,
  })
}

export function useCloseAdmRequestAsVerified(requestId: string) {
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: () => administrationService.staff.closeAsVerified(requestId),
    onSuccess: invalidate,
  })
}

export function useAdmStaffComment(requestId: string) {
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: ({ body, isInternal }: { body: string; isInternal: boolean }) =>
      administrationService.staff.comment(requestId, body, isInternal),
    onSuccess: invalidate,
  })
}

export function useAttachAdmFiles(requestId: string) {
  const invalidate = useInvalidateAdmStaffRequest(requestId)

  return useMutation({
    mutationFn: (files: File[]) => administrationService.staff.attach(requestId, files),
    onSuccess: invalidate,
  })
}
