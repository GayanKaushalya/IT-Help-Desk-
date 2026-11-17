import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'

import { useIncomingDeskAlerts } from '@/features/notifications/useIncomingDeskAlerts'
import { useMarkNotificationRead } from '@/features/notifications/useNotifications'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'

/**
 * Watches for newly arrived IT tickets and raises the standard app toast.
 */
export function NewTicketAlertHost() {
  const toast = useToast()
  const navigate = useNavigate()
  const { active, dismiss } = useIncomingDeskAlerts('it')
  const markRead = useMarkNotificationRead('it')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New ticket'
    const ticketId = active.data?.ticket_id

    toast.info('New IT ticket', active.body ?? reference, {
      placement: 'center',
      actionLabel: ticketId ? 'Open ticket' : undefined,
      onAction: ticketId
        ? () => {
            void navigate(paths.admin.ticketDetail(ticketId))
          }
        : undefined,
      durationMs: 12_000,
    })

    if (active.id > 0 && active.is_read !== true) {
      markRead.mutate(active.id, { onError: () => undefined })
    }
    dismiss(active.id)
  }, [active, dismiss, markRead, navigate, toast])

  return null
}
