import { useContext, useMemo } from 'react'

import { ToastContext, type Toast } from '@/contexts/ToastContext'

type ToastOptions = Pick<Toast, 'actionLabel' | 'onAction' | 'durationMs' | 'placement'>

/**
 * Raises application notifications.
 *
 *   const toast = useToast()
 *   toast.success('Ticket created', 'Reference TKT-2026-000123')
 */
export function useToast() {
  const context = useContext(ToastContext)

  if (!context) {
    throw new Error('useToast must be used inside a ToastProvider.')
  }

  const { notify, dismiss } = context

  return useMemo(
    () => ({
      dismiss,
      success: (title: string, description?: string, options?: ToastOptions) =>
        notify({ tone: 'success', title, ...(description ? { description } : {}), ...options }),
      error: (title: string, description?: string, options?: ToastOptions) =>
        notify({ tone: 'error', title, ...(description ? { description } : {}), ...options }),
      warning: (title: string, description?: string, options?: ToastOptions) =>
        notify({ tone: 'warning', title, ...(description ? { description } : {}), ...options }),
      info: (title: string, description?: string, options?: ToastOptions) =>
        notify({ tone: 'info', title, ...(description ? { description } : {}), ...options }),
    }),
    [notify, dismiss],
  )
}
