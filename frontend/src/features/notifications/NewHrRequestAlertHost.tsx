import { useEffect, useRef } from 'react'

import { useIncomingDeskAlerts } from '@/features/notifications/useIncomingDeskAlerts'
import { useMarkNotificationRead } from '@/features/notifications/useNotifications'
import { useToast } from '@/hooks/useToast'

/**
 * Watches for newly arrived HR requests and raises the standard app toast.
 */
export function NewHrRequestAlertHost() {
  const toast = useToast()
  const { active, dismiss } = useIncomingDeskAlerts('hr')
  const markRead = useMarkNotificationRead('hr')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New HR request'
    toast.info('New HR request', active.body ?? reference)

    if (active.id > 0 && active.is_read !== true) {
      markRead.mutate(active.id)
    }
    dismiss(active.id)
  }, [active, dismiss, markRead, toast])

  return null
}
