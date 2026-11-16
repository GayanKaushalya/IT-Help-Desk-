import { TicketPlus } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import {
  IncomingDeskAlert,
  ensureNotificationPermission,
  flashDocumentTitle,
  playDeskAlertTone,
  showBrowserNotification,
} from '@/features/notifications/IncomingDeskAlert'
import { useMarkNotificationRead, useNotificationFeed } from '@/features/notifications/useNotifications'
import { paths } from '@/routes/paths'
import type { AppNotification } from '@/types/models'

const NEW_TICKET_TYPES = new Set(['ticket.new'])

/**
 * While the IT admin shell is open, poll for new-ticket notifications and
 * show a Windows-style toast as soon as one arrives.
 */
export function NewTicketAlertHost() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const feed = useNotificationFeed(true, { refetchIntervalMs: 8_000, ignoreLivePause: true, desk: 'it' })
  const markRead = useMarkNotificationRead('it')

  const seenIds = useRef<Set<number> | null>(null)
  const [queue, setQueue] = useState<AppNotification[]>([])
  const active = queue[0] ?? null

  useEffect(() => {
    ensureNotificationPermission()
  }, [])

  useEffect(() => {
    const items = feed.data?.notifications
    if (!items) return

    if (seenIds.current === null) {
      seenIds.current = new Set(items.map((item) => item.id))
      return
    }

    const fresh = items.filter(
      (item) => NEW_TICKET_TYPES.has(item.type) && !item.is_read && !seenIds.current!.has(item.id),
    )

    if (fresh.length === 0) return

    fresh.forEach((item) => seenIds.current!.add(item.id))

    void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets'] })
    void queryClient.invalidateQueries({ queryKey: ['staff', 'dashboard'] })

    setQueue((current) => {
      const known = new Set(current.map((item) => item.id))
      const next = fresh.filter((item) => !known.has(item.id))
      return next.length > 0 ? [...current, ...next] : current
    })
  }, [feed.data?.notifications, queryClient])

  useEffect(() => {
    if (!active) return

    const reference = active.data?.reference ?? 'New ticket'
    playDeskAlertTone(880)
    flashDocumentTitle(`New ticket ${reference}`)
    showBrowserNotification('New IT ticket', `${reference} — ${active.body ?? active.title}`, `ticket-${active.id}`)
  }, [active?.id])

  function dismissActive(markAsRead: boolean): void {
    if (!active) return

    if (markAsRead && !active.is_read) {
      markRead.mutate(active.id)
    }

    setQueue((current) => current.filter((item) => item.id !== active.id))
  }

  function openTicket(): void {
    if (!active) return

    const ticketId = active.data?.ticket_id
    dismissActive(true)

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
      onDismiss={() => dismissActive(true)}
      onOpen={openTicket}
      openLabel="Open ticket"
    />
  )
}
