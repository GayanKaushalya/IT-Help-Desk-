import type { ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface PageHeaderProps {
  title: string
  description?: string
  /** Buttons or filters aligned to the right of the title. */
  actions?: ReactNode
  /** Breadcrumb or back-link slot rendered above the title. */
  eyebrow?: ReactNode
  className?: string
}

export function PageHeader({ title, description, actions, eyebrow, className }: PageHeaderProps) {
  return (
    <header className={cn('flex flex-wrap items-start justify-between gap-4', className)}>
      <div className="min-w-0">
        {eyebrow && <div className="mb-1.5">{eyebrow}</div>}
        <h1 className="text-xl font-semibold tracking-tight text-surface-900">{title}</h1>
        {description && <p className="mt-1 text-sm text-surface-500">{description}</p>}
      </div>

      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </header>
  )
}
