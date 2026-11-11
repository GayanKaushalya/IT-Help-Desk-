import { TicketPlus } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import {
  useMarkNotificationRead,
  useNotificationFeed,
} from '@/features/notifications/useNotifications'
import { paths } from '@/routes/paths'
import type { AppNotification } from '@/types/models'

const NEW_TICKET_TYPES = new Set(['ticket.new'])

function playAlertTone(): void {
  try {
    const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!AudioCtx) return

    const ctx = new AudioCtx()
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()
    oscillator.type = 'sine'
    oscillator.frequency.value = 880
    gain.gain.value = 0.04
    oscillator.connect(gain)
    gain.connect(ctx.destination)
    oscillator.start()
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35)
    oscillator.stop(ctx.currentTime + 0.35)
    window.setTimeout(() => void ctx.close(), 500)
  } catch {
    // Browsers may block audio until a user gesture — popup still shows.
  }
}

function flashDocumentTitle(reference: string): void {
  const original = document.title
  document.title = `New ticket ${reference}`
  window.setTimeout(() => {
    document.title = original
  }, 5000)
}

/**
 * While the IT admin shell is open, poll for new-ticket notifications and
 * open a screen popup as soon as one arrives.
 */
export function NewTicketAlertHost() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const feed = useNotificationFeed(true, { refetchIntervalMs: 10_000, ignoreLivePause: true, desk: 'it' })
  const markRead = useMarkNotificationRead('it')

  const seenIds = useRef<Set<number> | null>(null)
  const [queue, setQueue] = useState<AppNotification[]>([])
  const active = queue[0] ?? null

  useEffect(() => {
    const items = feed.data?.notifications
    if (!items) return

    if (seenIds.current === null) {
      // First successful load: baseline only — do not popup historical unread.
      seenIds.current = new Set(items.map((item) => item.id))
      return
    }

    const fresh = items.filter(
      (item) =>
        NEW_TICKET_TYPES.has(item.type) &&
        !item.is_read &&
        !seenIds.current!.has(item.id),
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

    playAlertTone()
    flashDocumentTitle(active.data?.reference ?? '')
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
    <Modal
      isOpen={Boolean(active)}
      onClose={() => dismissActive(true)}
      title="New ticket received"
      description="A new IT support request just arrived."
      size="md"
      footer={
        <>
          <Button variant="outline" onClick={() => dismissActive(true)}>
            Dismiss
          </Button>
          <Button onClick={openTicket} disabled={!active?.data?.ticket_id}>
            Open ticket
          </Button>
        </>
      }
    >
      {active && (
        <div className="flex gap-3">
          <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700">
            <TicketPlus className="size-5" />
          </span>
          <div className="min-w-0 space-y-2">
            <p className="font-mono text-sm font-semibold text-primary-700">
              {active.data?.reference ?? 'New ticket'}
            </p>
            <p className="text-sm leading-6 text-surface-800">
              {active.body ?? active.title}
            </p>
            {queue.length > 1 && (
              <p className="text-xs text-surface-500">
                {queue.length - 1} more new ticket{queue.length > 2 ? 's' : ''} waiting
              </p>
            )}
          </div>
        </div>
      )}
    </Modal>
  )
}
