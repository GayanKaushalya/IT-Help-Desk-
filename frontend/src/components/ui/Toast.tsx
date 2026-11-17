import { AlertTriangle, CheckCircle2, Info, TicketPlus, X, XCircle } from 'lucide-react'
import type { ComponentType } from 'react'

import type { Toast, ToastTone } from '@/contexts/ToastContext'
import { cn } from '@/utils/cn'

const TONE_ICONS: Record<ToastTone, ComponentType<{ className?: string }>> = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
}

const TONE_ICON_CLASSES: Record<ToastTone, string> = {
  success: 'text-success-600',
  error: 'text-danger-600',
  warning: 'text-warning-600',
  info: 'text-info-600',
}

const TONE_PANEL: Record<ToastTone, string> = {
  success: 'border-success-200 bg-panel',
  error: 'border-danger-200 bg-panel',
  warning: 'border-warning-200 bg-panel',
  info: 'border-primary-200 bg-panel',
}

export interface ToastViewportProps {
  toasts: Toast[]
  onDismiss: (id: string) => void
}

function ToastCard({ toast, onDismiss }: { toast: Toast; onDismiss: (id: string) => void }) {
  const isDeskAlert = toast.placement === 'center' || Boolean(toast.actionLabel && toast.onAction)
  const Icon = isDeskAlert ? TicketPlus : TONE_ICONS[toast.tone]

  return (
    <div
      className={cn(
        'pointer-events-auto flex animate-slide-up items-start gap-3',
        'rounded-xl border p-4 shadow-overlay',
        TONE_PANEL[toast.tone],
        isDeskAlert && 'w-[min(calc(100vw-2rem),24rem)] border-primary-300 bg-panel p-5 shadow-card',
      )}
    >
      <span
        className={cn(
          'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg',
          isDeskAlert ? 'bg-primary-600 text-white' : 'bg-surface-100',
        )}
      >
        <Icon
          className={cn('size-4', isDeskAlert ? 'text-white' : TONE_ICON_CLASSES[toast.tone])}
          aria-hidden
        />
      </span>

      <div className="min-w-0 flex-1">
        <p className="text-sm font-semibold text-surface-900">{toast.title}</p>
        {toast.description && (
          <p className="mt-0.5 text-sm leading-5 text-surface-600">{toast.description}</p>
        )}
        {toast.actionLabel && toast.onAction && (
          <button
            type="button"
            onClick={() => {
              toast.onAction?.()
              onDismiss(toast.id)
            }}
            className="mt-3 inline-flex rounded-md bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
          >
            {toast.actionLabel}
          </button>
        )}
      </div>

      <button
        type="button"
        onClick={() => onDismiss(toast.id)}
        aria-label="Dismiss notification"
        className="-mt-1 -mr-1 rounded p-1 text-surface-400 transition-colors hover:bg-surface-100 hover:text-surface-700"
      >
        <X className="size-4" />
      </button>
    </div>
  )
}

/** Fixed stack of notifications, rendered once by the ToastProvider. */
export function ToastViewport({ toasts, onDismiss }: ToastViewportProps) {
  if (toasts.length === 0) return null

  const cornerToasts = toasts.filter((toast) => toast.placement !== 'center')
  const centerToasts = toasts.filter((toast) => toast.placement === 'center')

  return (
    <>
      {cornerToasts.length > 0 && (
        <div
          aria-live="polite"
          className="pointer-events-none fixed inset-x-4 top-4 z-[2147483000] flex max-w-sm flex-col gap-3 sm:inset-x-auto sm:right-4"
        >
          {cornerToasts.map((toast) => (
            <ToastCard key={toast.id} toast={toast} onDismiss={onDismiss} />
          ))}
        </div>
      )}

      {centerToasts.length > 0 && (
        <div
          aria-live="assertive"
          className="fixed inset-0 z-[2147483000] flex items-center justify-center bg-surface-900/30 p-4 backdrop-blur-[1px]"
          onClick={() => centerToasts.forEach((toast) => onDismiss(toast.id))}
        >
          <div
            className="flex max-w-sm flex-col gap-3"
            onClick={(event) => event.stopPropagation()}
          >
            {centerToasts.map((toast) => (
              <ToastCard key={toast.id} toast={toast} onDismiss={onDismiss} />
            ))}
          </div>
        </div>
      )}
    </>
  )
}
