import { useCallback, useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'

import { notificationService, type NotificationDesk } from '@/services/notificationService'
import type { AppNotification } from '@/types/models'

/** Light poll — heavy ticket-list fetches every few seconds were freezing the desk. */
const POLL_MS = 12_000

const ALERT_TYPES: Record<NotificationDesk, Set<string>> = {
  it: new Set(['ticket.new']),
  hr: new Set(['hr.request.new']),
}

function workKey(desk: NotificationDesk, item: AppNotification): string {
  if (desk === 'it') {
    return `it:${String(item.data?.ticket_id ?? item.id)}`
  }

  return `hr:${String(item.data?.hr_request_id ?? item.id)}`
}

/**
 * Watches the notification feed for newly arrived desk work.
 * Uses a single lightweight poll so the admin UI stays responsive.
 */
export function useIncomingDeskAlerts(desk: NotificationDesk) {
  const queryClient = useQueryClient()
  const seenKeys = useRef<Set<string> | null>(null)
  const inFlight = useRef(false)
  const [queue, setQueue] = useState<AppNotification[]>([])
  const types = ALERT_TYPES[desk]

  const enqueue = useCallback(
    (incoming: AppNotification[]) => {
      if (incoming.length === 0) return

      // Soft refresh — do not clear every staff query on each toast.
      if (desk === 'it') {
        void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets'], exact: false })
      } else {
        void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'requests'], exact: false })
      }
      void queryClient.invalidateQueries({ queryKey: ['notifications', 'feed', desk] })

      setQueue((current) => {
        const known = new Set(current.map((item) => workKey(desk, item)))
        const next = incoming.filter((item) => !known.has(workKey(desk, item)))
        return next.length > 0 ? [...current, ...next] : current
      })
    },
    [desk, queryClient],
  )

  const poll = useCallback(async () => {
    if (inFlight.current) return
    if (document.visibilityState === 'hidden') return

    inFlight.current = true
    try {
      const feed = await notificationService.feed(desk)
      const byKey = new Map<string, AppNotification>()

      for (const item of feed.notifications ?? []) {
        if (!types.has(item.type) || item.is_read === true) continue
        byKey.set(workKey(desk, item), item)
      }

      const keys = [...byKey.keys()]

      if (seenKeys.current === null) {
        seenKeys.current = new Set(keys)
        return
      }

      const fresh: AppNotification[] = []
      for (const [key, item] of byKey) {
        if (seenKeys.current.has(key)) continue
        seenKeys.current.add(key)
        fresh.push(item)
      }

      enqueue(fresh)
    } catch {
      // Keep polling; a single failed tick must not stop desk alerts.
    } finally {
      inFlight.current = false
    }
  }, [desk, enqueue, types])

  useEffect(() => {
    seenKeys.current = null
    void poll()

    const timer = window.setInterval(() => {
      void poll()
    }, POLL_MS)

    function onVisible(): void {
      if (document.visibilityState === 'visible') {
        void poll()
      }
    }

    document.addEventListener('visibilitychange', onVisible)

    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [poll])

  function dismiss(id: number): void {
    setQueue((current) => current.filter((item) => item.id !== id))
  }

  return {
    queue,
    active: queue[0] ?? null,
    dismiss,
  }
}
