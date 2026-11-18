import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'

import { useIncomingDeskAlerts } from '@/features/notifications/useIncomingDeskAlerts'
import { useMarkNotificationRead } from '@/features/notifications/useNotifications'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'

/**
 * Watches for newly arrived Administration requests and raises the standard app toast.
 */
export function NewAdmRequestAlertHost() {
  const toast = useToast()
  const navigate = useNavigate()
  const { active, dismiss } = useIncomingDeskAlerts('administration')
  const markRead = useMarkNotificationRead('administration')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New Administration request'
    const requestId = active.data?.adm_request_id

    toast.info('New Administration request', active.body ?? reference, {
      placement: 'center',
      actionLabel: requestId ? 'Open request' : undefined,
      onAction: requestId
        ? () => {
            void navigate(paths.administration.admin.requestDetail(requestId))
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
