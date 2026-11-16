import { UserPlus } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import {
  IncomingDeskAlert,
  ensureNotificationPermission,
  flashDocumentTitle,
  playDeskAlertTone,
  showBrowserNotification,
} from '@/features/notifications/IncomingDeskAlert'
import { useIncomingDeskAlerts } from '@/features/notifications/useIncomingDeskAlerts'
import { useMarkNotificationRead } from '@/features/notifications/useNotifications'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'

export function NewHrRequestAlertHost() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const toast = useToast()
  const { queue, active, dismiss } = useIncomingDeskAlerts('hr')
  const markRead = useMarkNotificationRead('hr')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    ensureNotificationPermission()
  }, [])

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New HR request'
    playDeskAlertTone(740)
    flashDocumentTitle(`New HR request ${reference}`)
    showBrowserNotification('New HR request', `${reference} — ${active.body ?? active.title}`, `hr-${active.id}`)
    toast.info('New HR request', active.body ?? reference)
  }, [active, toast])

  function dismissActive(): void {
    if (!active) return
    // List-derived alerts use negative ids and are not stored notification rows.
    if (active.id > 0 && active.is_read !== true) {
      markRead.mutate(active.id)
    }
    dismiss(active.id)
  }

  function openRequest(): void {
    if (!active) return
    const requestId = active.data?.hr_request_id
    dismissActive()
    if (requestId) {
      void queryClient.invalidateQueries({
        queryKey: ['hr', 'staff', 'requests', 'detail', String(requestId)],
      })
      void navigate(paths.hr.admin.requestDetail(requestId))
    }
  }

  return (
    <IncomingDeskAlert
      isOpen={Boolean(active)}
      title="New HR request"
      subtitle="Indra Help Desk"
      icon={<UserPlus className="size-4 text-[#5ec8ff]" />}
      body={
        active ? (
          <>
            <p className="font-mono text-xs font-semibold tracking-wide text-[#5ec8ff]">
              {active.data?.reference ?? 'New HR request'}
            </p>
            <p className="mt-1">{active.body ?? active.title}</p>
          </>
        ) : null
      }
      meta={
        queue.length > 1
          ? `${queue.length - 1} more new request${queue.length > 2 ? 's' : ''} waiting`
          : undefined
      }
      onDismiss={dismissActive}
      onOpen={openRequest}
      openLabel="Open request"
    />
  )
}
