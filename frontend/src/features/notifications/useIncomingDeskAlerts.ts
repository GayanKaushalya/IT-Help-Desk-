import { useCallback, useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'

import { hrService } from '@/services/hrService'
import { notificationService, type NotificationDesk } from '@/services/notificationService'
import { staffService } from '@/services/staffService'
import type { AppNotification, HrRequest, Ticket } from '@/types/models'

const POLL_MS = 2_500

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

function ticketToAlert(ticket: Ticket): AppNotification {
  return {
    // Negative id = list-derived; hosts skip mark-read for these.
    id: -ticket.id,
    type: 'ticket.new',
    title: 'New ticket',
    body: `${ticket.reference}: ${ticket.subject}`,
    data: {
      module: 'it',
      ticket_id: ticket.id,
      reference: ticket.reference,
    },
    is_read: false,
    read_at: null,
    created_at: ticket.created_at,
  }
}

function hrRequestToAlert(request: HrRequest): AppNotification {
  return {
    id: -request.id,
    type: 'hr.request.new',
    title: 'New HR request',
    body: request.is_confidential
      ? `${request.reference}: new confidential complaint.`
      : `${request.reference} from ${request.employee_name ?? 'employee'}.`,
    data: {
      module: 'hr',
      hr_request_id: request.id,
      reference: request.reference,
    },
    is_read: false,
    read_at: null,
    created_at: request.created_at,
  }
}

/**
 * Watches the desk for newly arrived work via both the notification feed and
 * the live ticket/request lists. Uses a direct timer so React Query focus /
 * staleTime settings cannot suppress the poll.
 */
export function useIncomingDeskAlerts(desk: NotificationDesk) {
  const queryClient = useQueryClient()
  const seenKeys = useRef<Set<string> | null>(null)
  const [queue, setQueue] = useState<AppNotification[]>([])
  const types = ALERT_TYPES[desk]

  const enqueue = useCallback(
    (incoming: AppNotification[]) => {
      if (incoming.length === 0) return

      if (desk === 'it') {
        void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets'] })
        void queryClient.invalidateQueries({ queryKey: ['staff', 'dashboard'] })
      } else {
        void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'requests'] })
        void queryClient.invalidateQueries({ queryKey: ['hr', 'staff', 'dashboard'] })
      }
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })

      setQueue((current) => {
        const known = new Set(current.map((item) => workKey(desk, item)))
        const next = incoming.filter((item) => !known.has(workKey(desk, item)))
        return next.length > 0 ? [...current, ...next] : current
      })
    },
    [desk, queryClient],
  )

  const poll = useCallback(async () => {
    try {
      const feed = await notificationService.feed(desk)
      const byKey = new Map<string, AppNotification>()

      for (const item of feed.notifications ?? []) {
        if (!types.has(item.type) || item.is_read === true) continue
        byKey.set(workKey(desk, item), item)
      }

      if (desk === 'it') {
        const tickets = await staffService.tickets({ page: 1, per_page: 8 })
        for (const ticket of tickets.data) {
          const alert = ticketToAlert(ticket)
          const key = workKey(desk, alert)
          if (!byKey.has(key)) byKey.set(key, alert)
        }
      } else {
        const requests = await hrService.staff.requests({ page: 1, per_page: 8 })
        for (const request of requests.data) {
          const alert = hrRequestToAlert(request)
          const key = workKey(desk, alert)
          if (!byKey.has(key)) byKey.set(key, alert)
        }
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
    window.addEventListener('focus', onVisible)

    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisible)
      window.removeEventListener('focus', onVisible)
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
