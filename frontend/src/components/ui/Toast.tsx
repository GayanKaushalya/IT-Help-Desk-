import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react'
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

export interface ToastViewportProps {
  toasts: Toast[]
  onDismiss: (id: string) => void
}

/** Fixed stack of notifications, rendered once by the ToastProvider. */
export function ToastViewport({ toasts, onDismiss }: ToastViewportProps) {
  if (toasts.length === 0) return null

  return (
    <div
      aria-live="polite"
      className="pointer-events-none fixed inset-x-4 top-4 z-[2147483000] flex max-w-sm flex-col gap-3 sm:inset-x-auto sm:right-4"
    >
      {toasts.map((toast) => {
        const Icon = TONE_ICONS[toast.tone]

        return (
          <div
            key={toast.id}
            className={cn(
              'pointer-events-auto flex animate-slide-up items-start gap-3',
              'rounded-lg border border-surface-200 bg-panel p-4 shadow-overlay',
            )}
          >
            <Icon className={cn('mt-0.5 size-5 shrink-0', TONE_ICON_CLASSES[toast.tone])} aria-hidden />

            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-surface-900">{toast.title}</p>
              {toast.description && (
                <p className="mt-0.5 text-sm text-surface-500">{toast.description}</p>
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
      })}
    </div>
  )
}
