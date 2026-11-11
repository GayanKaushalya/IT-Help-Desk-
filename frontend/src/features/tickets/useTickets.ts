import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { ticketService, type TicketListParams } from '@/services/ticketService'

export const ticketKeys = {
  all: ['tickets'] as const,
  list: (params: TicketListParams) => ['tickets', 'list', params] as const,
  detail: (id: string) => ['tickets', id] as const,
}

export function useTicketList(params: TicketListParams) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: ticketKeys.list(params),
    queryFn: () => ticketService.index(params),
    placeholderData: keepPreviousData,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

export function useTicket(id: string | undefined) {
  const paused = useLiveRefreshPaused()

  return useQuery({
    queryKey: ticketKeys.detail(id ?? ''),
    queryFn: () => ticketService.show(id as string),
    enabled: Boolean(id),
    retry: false,
    refetchInterval: paused ? false : 30_000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: !paused,
  })
}

/** Refresh employee ticket screens after a create / update. */
export function useInvalidateEmployeeTickets() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: ticketKeys.all })
    void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    void queryClient.invalidateQueries({ queryKey: ['notifications'] })
    void queryClient.invalidateQueries({ queryKey: ['staff'] })
  }
}

export function useVerifyTicket(ticketId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (decision: 'confirmed' | 'rejected') => ticketService.verify(ticketId, decision),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ticketKeys.detail(ticketId) })
      void queryClient.invalidateQueries({ queryKey: ticketKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
      void queryClient.invalidateQueries({ queryKey: ['staff'] })
    },
  })
}

export function useAddTicketAttachment(ticketId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({
      files,
      onProgress,
    }: {
      files: File[]
      onProgress?: (percent: number) => void
    }) => ticketService.attach(ticketId, files, onProgress),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ticketKeys.detail(ticketId) })
      void queryClient.invalidateQueries({ queryKey: ticketKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
      void queryClient.invalidateQueries({ queryKey: ['staff'] })
    },
  })
}

export function useAddTicketComment(ticketId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (body: string) => ticketService.comment(ticketId, body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ticketKeys.detail(ticketId) })
      void queryClient.invalidateQueries({ queryKey: ticketKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
      void queryClient.invalidateQueries({ queryKey: ['staff'] })
    },
  })
}
