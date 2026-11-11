import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { staffService, type StaffTicketListParams } from '@/services/staffService'

export const staffKeys = {
  dashboard: ['staff', 'dashboard'] as const,
  agents: ['staff', 'agents'] as const,
  tickets: ['staff', 'tickets'] as const,
  queue: (params: StaffTicketListParams) => ['staff', 'tickets', 'queue', params] as const,
  assigned: (params: StaffTicketListParams) => ['staff', 'tickets', 'assigned', params] as const,
  detail: (id: string) => ['staff', 'tickets', id] as const,
}

export function useStaffDashboard() {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: staffKeys.dashboard,
    queryFn: staffService.dashboard,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useStaffAgents() {
  return useQuery({
    queryKey: staffKeys.agents,
    queryFn: staffService.agents,
    staleTime: 30_000,
  })
}

export function useStaffTicketQueue(params: StaffTicketListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: staffKeys.queue(params),
    queryFn: () => staffService.tickets(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useStaffAssignedTickets(params: StaffTicketListParams, enabled = true) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: staffKeys.assigned(params),
    queryFn: () => staffService.assigned(params),
    placeholderData: keepPreviousData,
    enabled,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useStaffTicket(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: staffKeys.detail(id ?? ''),
    queryFn: () => staffService.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

function useInvalidateStaffTicket(ticketId: string) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: staffKeys.detail(ticketId) })
    void queryClient.invalidateQueries({ queryKey: staffKeys.tickets })
    void queryClient.invalidateQueries({ queryKey: staffKeys.dashboard })
    void queryClient.invalidateQueries({ queryKey: staffKeys.agents })
    void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    void queryClient.invalidateQueries({ queryKey: ['tickets'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }
}

export function useAssignStaffTicket(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: ({ assignedTo, note }: { assignedTo: number; note?: string }) =>
      staffService.assign(ticketId, assignedTo, note),
    onSuccess: invalidate,
  })
}

export function useUpdateStaffTicketStatus(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: ({ statusId, resolutionSummary }: { statusId: number; resolutionSummary?: string }) =>
      staffService.updateStatus(ticketId, statusId, resolutionSummary),
    onSuccess: invalidate,
  })
}

export function useUpdateStaffTicketPriority(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: (priorityId: number) => staffService.updatePriority(ticketId, priorityId),
    onSuccess: invalidate,
  })
}

export function useResolveStaffTicket(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: (summary: string) => staffService.resolve(ticketId, summary),
    onSuccess: invalidate,
  })
}

export function useClearStaffTicketResolution(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: () => staffService.clearResolution(ticketId),
    onSuccess: invalidate,
  })
}

export function useStaffTicketComment(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: ({ body, isInternal }: { body: string; isInternal: boolean }) =>
      staffService.comment(ticketId, body, isInternal),
    onSuccess: invalidate,
  })
}

export function useStaffTicketAttachment(ticketId: string) {
  const invalidate = useInvalidateStaffTicket(ticketId)

  return useMutation({
    mutationFn: ({ files, onProgress }: { files: File[]; onProgress?: (percent: number) => void }) =>
      staffService.attach(ticketId, files, onProgress),
    onSuccess: invalidate,
  })
}
