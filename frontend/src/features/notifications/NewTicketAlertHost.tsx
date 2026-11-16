import { TicketPlus } from 'lucide-react'
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

export function NewTicketAlertHost() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const toast = useToast()
  const { queue, active, dismiss } = useIncomingDeskAlerts('it')
  const markRead = useMarkNotificationRead('it')
  const announced = useRef<Set<number>>(new Set())

  useEffect(() => {
    ensureNotificationPermission()
  }, [])

  useEffect(() => {
    if (!active || announced.current.has(active.id)) return
    announced.current.add(active.id)

    const reference = active.data?.reference ?? 'New ticket'
    playDeskAlertTone(880)
    flashDocumentTitle(`New ticket ${reference}`)
    showBrowserNotification('New IT ticket', `${reference} — ${active.body ?? active.title}`, `ticket-${active.id}`)
    toast.info('New IT ticket', active.body ?? reference)
  }, [active, toast])

  function dismissActive(): void {
    if (!active) return
    // List-derived alerts use negative ids and are not stored notification rows.
    if (active.id > 0 && active.is_read !== true) {
      markRead.mutate(active.id)
    }
    dismiss(active.id)
  }

  function openTicket(): void {
    if (!active) return
    const ticketId = active.data?.ticket_id
    dismissActive()
    if (ticketId) {
      void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets', String(ticketId)] })
      void navigate(paths.admin.ticketDetail(ticketId))
    }
  }

  return (
    <IncomingDeskAlert
      isOpen={Boolean(active)}
      title="New IT ticket"
      subtitle="Indra Help Desk"
      icon={<TicketPlus className="size-4 text-[#5ec8ff]" />}
      body={
        active ? (
          <>
            <p className="font-mono text-xs font-semibold tracking-wide text-[#5ec8ff]">
              {active.data?.reference ?? 'New ticket'}
            </p>
            <p className="mt-1">{active.body ?? active.title}</p>
          </>
        ) : null
      }
      meta={
        queue.length > 1
          ? `${queue.length - 1} more new ticket${queue.length > 2 ? 's' : ''} waiting`
          : undefined
      }
      onDismiss={dismissActive}
      onOpen={openTicket}
      openLabel="Open ticket"
    />
  )
}
