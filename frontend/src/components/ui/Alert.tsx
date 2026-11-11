import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'
import type { ComponentType, ReactNode } from 'react'

import { cn } from '@/utils/cn'

export type AlertTone = 'info' | 'success' | 'warning' | 'danger'

const TONE_CLASSES: Record<AlertTone, string> = {
  info: 'border-info-500/25 bg-info-50 text-info-700',
  success: 'border-success-500/25 bg-success-50 text-success-700',
  warning: 'border-warning-500/30 bg-warning-50 text-warning-700',
  danger: 'border-danger-500/25 bg-danger-50 text-danger-700',
}

const TONE_ICONS: Record<AlertTone, ComponentType<{ className?: string }>> = {
  info: Info,
  success: CheckCircle2,
  warning: AlertTriangle,
  danger: XCircle,
}

export interface AlertProps {
  tone?: AlertTone
  title?: string
  children?: ReactNode
  className?: string
}

export function Alert({ tone = 'info', title, children, className }: AlertProps) {
  const Icon = TONE_ICONS[tone]

  return (
    <div
      role="alert"
      className={cn('flex gap-3 rounded-lg border px-4 py-3 text-sm', TONE_CLASSES[tone], className)}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
      <div className="min-w-0">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={cn(title && 'mt-0.5', 'opacity-90')}>{children}</div>}
      </div>
    </div>
  )
}
