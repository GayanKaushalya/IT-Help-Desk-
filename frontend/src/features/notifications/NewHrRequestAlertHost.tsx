import { UserPlus } from 'lucide-react'
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

const NEW_HR_REQUEST_TYPES = new Set(['hr.request.new'])

/**
 * While the HR admin shell is open, poll for new-request notifications and
 * show a Windows-style toast as soon as one arrives.
 */
export function NewHrRequestAlertHost() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const feed = useNotificationFeed(true, { refetchIntervalMs: 8_000, ignoreLivePause: true, desk: 'hr' })
  const markRead = useMarkNotificationRead('hr')

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
      (item) => NEW_HR_REQUEST_TYPES.has(item.type) && !item.is_read && !seenIds.current!.has(item.id),
    )

    if (fresh.length === 0) return

    fresh.forEach((item) => seenIds.current!.add(item.id))

    void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'requests'] })
    void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'dashboard'] })

    setQueue((current) => {
      const known = new Set(current.map((item) => item.id))
      const next = fresh.filter((item) => !known.has(item.id))
      return next.length > 0 ? [...current, ...next] : current
    })
  }, [feed.data?.notifications, queryClient])

  useEffect(() => {
    if (!active) return

    const reference = active.data?.reference ?? 'New HR request'
    playDeskAlertTone(740)
    flashDocumentTitle(`New HR request ${reference}`)
    showBrowserNotification('New HR request', `${reference} — ${active.body ?? active.title}`, `hr-${active.id}`)
  }, [active?.id])

  function dismissActive(markAsRead: boolean): void {
    if (!active) return

    if (markAsRead && !active.is_read) {
      markRead.mutate(active.id)
    }

    setQueue((current) => current.filter((item) => item.id !== active.id))
  }

  function openRequest(): void {
    if (!active) return

    const requestId = active.data?.hr_request_id
    dismissActive(true)

    if (requestId) {
      void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'requests', 'detail', String(requestId)] })
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
      onDismiss={() => dismissActive(true)}
      onOpen={openRequest}
      openLabel="Open request"
    />
  )
}
