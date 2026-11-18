import { apiClient } from '@/services/apiClient'
import type { AppNotification, NotificationFeed } from '@/types/models'

export type NotificationDesk = 'it' | 'hr' | 'administration'

function deskParams(desk?: NotificationDesk): { module?: NotificationDesk } {
  return desk ? { module: desk } : {}
}

export const notificationService = {
  feed: (desk?: NotificationDesk): Promise<NotificationFeed> =>
    apiClient.get<NotificationFeed>('/notifications', { params: deskParams(desk) }),

  unreadCount: (desk?: NotificationDesk): Promise<{ unread_count: number }> =>
    apiClient.get<{ unread_count: number }>('/notifications/unread-count', { params: deskParams(desk) }),

  markRead: (id: number): Promise<AppNotification> =>
    apiClient.post<AppNotification>(`/notifications/${id}/read`),

  markAllRead: (desk?: NotificationDesk): Promise<{ updated: number }> =>
    apiClient.post<{ updated: number }>('/notifications/read-all', undefined, { params: deskParams(desk) }),
}
