import { createContext } from 'react'

export type ToastTone = 'success' | 'error' | 'warning' | 'info'
export type ToastPlacement = 'top-right' | 'center'

export interface Toast {
  id: string
  tone: ToastTone
  title: string
  description?: string
  actionLabel?: string
  onAction?: () => void
  durationMs?: number
  placement?: ToastPlacement
}

export interface ToastContextValue {
  toasts: Toast[]
  notify: (toast: Omit<Toast, 'id'>) => void
  dismiss: (id: string) => void
}

/** Kept apart from ToastProvider so the provider file only exports a component. */
export const ToastContext = createContext<ToastContextValue | null>(null)
