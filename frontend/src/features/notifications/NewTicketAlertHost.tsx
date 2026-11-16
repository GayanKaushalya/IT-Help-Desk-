import { useEffect, useRef } from 'react'

import { useIncomingDeskAlerts } from '@/features/notifications/useIncomingDeskAlerts'
import { useMarkNotificationRead } from '@/features/notifications/useNotifications'
import { useToast } from '@/hooks/useToast'

/**
 * Watches for newly arrived IT tickets and raises the standard app toast.
 */
export function NewTicketAlertHost() {
  const toast = useToast()
  const { active, dismiss } = useIncomingDeskAlerts('it')
  const markRead = useMarkNotificationRead('it')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New ticket'
    toast.info('New IT ticket', active.body ?? reference)

    if (active.id > 0 && active.is_read !== true) {
      markRead.mutate(active.id)
    }
    dismiss(active.id)
  }, [active, dismiss, markRead, toast])

  return null
}
