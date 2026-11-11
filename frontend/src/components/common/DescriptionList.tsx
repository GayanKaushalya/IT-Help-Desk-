import type { ReactNode } from 'react'

import { cn } from '@/utils/cn'

export function DescriptionList({ children, className }: { children: ReactNode; className?: string }) {
  return <dl className={cn('space-y-4', className)}>{children}</dl>
}

export function DescriptionItem({
  label,
  children,
  mono = false,
  stacked = true,
}: {
  label: string
  children: ReactNode
  mono?: boolean
  stacked?: boolean
}) {
  return (
    <div className={cn(!stacked && 'flex items-start justify-between gap-4')}>
      <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">{label}</dt>
      <dd
        className={cn(
          stacked ? 'mt-1 text-sm font-medium text-surface-900' : 'text-right text-sm font-medium text-surface-900',
          mono && 'font-mono text-xs font-medium',
        )}
      >
        {children ?? '—'}
      </dd>
    </div>
  )
}
