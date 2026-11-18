import { paths } from '@/routes/paths'
import type { NotificationDesk } from '@/services/notificationService'
import type { AppNotification } from '@/types/models'

export type NotificationAudience = 'staff' | 'employee'

type NotificationData = NonNullable<AppNotification['data']>

/** SQL Server / legacy rows may store payload JSON as a string. */
export function parseNotificationData(notification: AppNotification): NotificationData | null {
  const raw = notification.data as NotificationData | string | null | undefined

  if (!raw) return null
  if (typeof raw === 'string') {
    try {
      return JSON.parse(raw) as NotificationData
    } catch {
      return null
    }
  }

  return raw
}

export function hrefForNotification(
  notification: AppNotification,
  desk: NotificationDesk,
  audience: NotificationAudience,
): string | null {
  const data = parseNotificationData(notification)

  if (desk === 'administration' || notification.type.startsWith('adm.')) {
    const requestId = data?.adm_request_id
    if (requestId == null || Number(requestId) <= 0) return null

    return audience === 'staff'
      ? paths.administration.admin.requestDetail(requestId)
      : paths.administration.detail(requestId)
  }

  if (desk === 'hr' || notification.type.startsWith('hr.')) {
    const requestId = data?.hr_request_id
    if (requestId == null || Number(requestId) <= 0) return null

    return audience === 'staff'
      ? paths.hr.admin.requestDetail(requestId)
      : paths.hr.detail(requestId)
  }

  const ticketId = data?.ticket_id
  if (ticketId == null || Number(ticketId) <= 0) return null

  return audience === 'staff' ? paths.admin.ticketDetail(ticketId) : paths.tickets.detail(ticketId)
}
