import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useLiveRefreshPaused } from '@/hooks/useLiveRefreshGuard'
import { notificationService, type NotificationDesk } from '@/services/notificationService'

export const notificationKeys = {
  all: ['notifications'] as const,
  feed: (desk?: NotificationDesk) => ['notifications', 'feed', desk ?? 'all'] as const,
}

export function useNotificationFeed(
  enabled = true,
  options: { refetchIntervalMs?: number; ignoreLivePause?: boolean; desk?: NotificationDesk } = {},
) {
  const paused = useLiveRefreshPaused()
  const refetchIntervalMs = options.refetchIntervalMs ?? 15_000
  const livePaused = options.ignoreLivePause ? false : paused
  const desk = options.desk

  return useQuery({
    queryKey: notificationKeys.feed(desk),
    queryFn: () => notificationService.feed(desk),
    enabled,
    refetchInterval: livePaused ? false : refetchIntervalMs,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
  })
}

function useInvalidateFeed(desk?: NotificationDesk) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: notificationKeys.feed(desk) })
    void queryClient.invalidateQueries({ queryKey: notificationKeys.all })
  }
}

export function useMarkNotificationRead(desk?: NotificationDesk) {
  const invalidate = useInvalidateFeed(desk)

  return useMutation({
    mutationFn: (id: number) => notificationService.markRead(id),
    onSuccess: invalidate,
  })
}

export function useMarkAllNotificationsRead(desk?: NotificationDesk) {
  const invalidate = useInvalidateFeed(desk)

  return useMutation({
    mutationFn: () => notificationService.markAllRead(desk),
    onSuccess: invalidate,
  })
}
