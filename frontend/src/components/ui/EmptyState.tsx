import { Inbox } from 'lucide-react'
import type { ComponentType, ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface EmptyStateProps {
  title: string
  description?: string
  icon?: ComponentType<{ className?: string }>
  action?: ReactNode
  className?: string
}

export function EmptyState({
  title,
  description,
  icon: Icon = Inbox,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-16 text-center', className)}>
      <div className="flex size-12 items-center justify-center rounded-full bg-surface-100">
        <Icon className="size-6 text-surface-400" />
      </div>

      <h3 className="mt-4 text-sm font-semibold text-surface-900">{title}</h3>

      {description && <p className="mt-1 max-w-sm text-sm text-surface-500">{description}</p>}

      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}
