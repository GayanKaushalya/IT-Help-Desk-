import { useContext, useMemo } from 'react'

import { ToastContext } from '@/contexts/ToastContext'

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
      success: (title: string, description?: string) =>
        notify({ tone: 'success', title, ...(description ? { description } : {}) }),
      error: (title: string, description?: string) =>
        notify({ tone: 'error', title, ...(description ? { description } : {}) }),
      warning: (title: string, description?: string) =>
        notify({ tone: 'warning', title, ...(description ? { description } : {}) }),
      info: (title: string, description?: string) =>
        notify({ tone: 'info', title, ...(description ? { description } : {}) }),
    }),
    [notify, dismiss],
  )
}
