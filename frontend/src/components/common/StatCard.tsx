import type { ComponentType } from 'react'

import type { ColorToken } from '@/types/models'
import { cn } from '@/utils/cn'

const ICON_CLASSES: Record<ColorToken, string> = {
  neutral: 'bg-surface-100 text-surface-600',
  primary: 'bg-primary-50 text-primary-500',
  success: 'bg-success-50 text-success-500',
  warning: 'bg-warning-50 text-warning-500',
  danger: 'bg-danger-50 text-danger-500',
  info: 'bg-info-50 text-info-500',
}

export interface StatCardProps {
  label: string
  value: string | number
  hint?: string
  icon?: ComponentType<{ className?: string }>
  tone?: ColorToken
}

export function StatCard({ label, value, hint, icon: Icon, tone = 'primary' }: StatCardProps) {
  return (
    <div className="rounded-card border border-surface-200 bg-panel p-5 shadow-card transition-shadow duration-200 hover:shadow-card-hover">
      <div className="flex items-start justify-between gap-3">
        <p className="text-xs font-medium tracking-wide text-surface-500 uppercase">{label}</p>

        {Icon && (
          <span
            className={cn('flex size-8 items-center justify-center rounded-lg', ICON_CLASSES[tone])}
          >
            <Icon className="size-4" />
          </span>
        )}
      </div>

      <p className="mt-3 text-2xl font-semibold tracking-tight text-surface-900">{value}</p>
      {hint && <p className="mt-1 text-xs text-surface-500">{hint}</p>}
    </div>
  )
}
