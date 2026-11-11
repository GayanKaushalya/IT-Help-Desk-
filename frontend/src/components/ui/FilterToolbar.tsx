import { useId, type ReactNode } from 'react'

import { cn } from '@/utils/cn'

export function FilterToolbar({
  children,
  className,
}: {
  children: ReactNode
  className?: string
}) {
  return (
    <div
      className={cn(
        'grid gap-3 border-b border-surface-200 px-4 py-4 sm:px-6',
        'md:grid-cols-2 xl:grid-cols-4',
        className,
      )}
    >
      {children}
    </div>
  )
}

export function FilterField({
  label,
  children,
}: {
  label: string
  children: (id: string) => ReactNode
}) {
  const id = useId()

  return (
    <div className="min-w-0 space-y-1.5">
      <label htmlFor={id} className="field-label">
        {label}
      </label>
      {children(id)}
    </div>
  )
}
