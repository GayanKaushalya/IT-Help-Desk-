import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

import { cn } from '@/utils/cn'

/**
 * Windows-style floating toast for newly arrived desk work.
 * Portaled to document.body so layout stacking cannot hide it.
 */
export function IncomingDeskAlert({
  isOpen,
  title,
  subtitle,
  icon,
  body,
  meta,
  onOpen,
  onDismiss,
  openLabel = 'Open',
  className,
}: {
  isOpen: boolean
  title: string
  subtitle?: string
  icon: ReactNode
  body: ReactNode
  meta?: ReactNode
  onOpen: () => void
  onDismiss: () => void
  openLabel?: string
  className?: string
}) {
  useEffect(() => {
    if (!isOpen) return
    ensureNotificationPermission()
  }, [isOpen])

  if (!isOpen || typeof document === 'undefined') return null

  return createPortal(
    <div
      role="alertdialog"
      aria-live="assertive"
      aria-label={title}
      className={cn(
        'pointer-events-auto fixed right-4 bottom-4 z-[2147483000] w-[min(calc(100vw-2rem),22rem)]',
        className,
      )}
    >
      <div className="overflow-hidden rounded-2xl border border-white/15 bg-[#1c1c1e] text-white shadow-[0_18px_50px_rgba(0,0,0,0.55)] ring-1 ring-black/40">
        <div className="flex items-start gap-3 px-4 pt-3.5 pb-2">
          <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10">
            {icon}
          </span>
          <div className="min-w-0 flex-1">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="text-sm font-semibold tracking-tight text-white">{title}</p>
                {subtitle && <p className="mt-0.5 text-xs text-white/55">{subtitle}</p>}
              </div>
              <button
                type="button"
                onClick={onDismiss}
                className="rounded-md p-1 text-white/50 transition-colors hover:bg-white/10 hover:text-white"
                aria-label="Dismiss"
              >
                <X className="size-4" />
              </button>
            </div>
            <div className="mt-2 text-sm leading-5 text-white/85">{body}</div>
            {meta && <div className="mt-2 text-xs text-white/50">{meta}</div>}
          </div>
        </div>

        <div className="mt-1 flex border-t border-white/10">
          <button
            type="button"
            onClick={onDismiss}
            className="flex-1 px-4 py-3 text-sm font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white"
          >
            Dismiss
          </button>
          <div className="w-px bg-white/10" aria-hidden />
          <button
            type="button"
            onClick={onOpen}
            className="flex-1 px-4 py-3 text-sm font-semibold text-[#5ec8ff] transition-colors hover:bg-white/5"
          >
            {openLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  )
}

export function playDeskAlertTone(frequency = 880): void {
  try {
    const AudioCtx =
      window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!AudioCtx) return

    const ctx = new AudioCtx()
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()
    oscillator.type = 'sine'
    oscillator.frequency.value = frequency
    gain.gain.value = 0.05
    oscillator.connect(gain)
    gain.connect(ctx.destination)
    oscillator.start()
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4)
    oscillator.stop(ctx.currentTime + 0.4)
    window.setTimeout(() => void ctx.close(), 600)
  } catch {
    // Audio may be blocked until a gesture — toast still shows.
  }
}

export function flashDocumentTitle(label: string): void {
  const original = document.title
  document.title = label
  window.setTimeout(() => {
    document.title = original
  }, 6000)
}

export function showBrowserNotification(title: string, body: string, tag?: string): void {
  if (typeof window === 'undefined' || !('Notification' in window)) return

  if (Notification.permission === 'default') {
    void Notification.requestPermission()
  }

  if (Notification.permission !== 'granted') return

  try {
    const notification = new Notification(title, {
      body,
      tag,
      icon: '/favicon.png',
      requireInteraction: false,
    })
    window.setTimeout(() => notification.close(), 10_000)
  } catch {
    // Ignore Notification API failures.
  }
}

export function ensureNotificationPermission(): void {
  if (typeof window === 'undefined' || !('Notification' in window)) return
  if (Notification.permission === 'default') {
    void Notification.requestPermission()
  }
}
