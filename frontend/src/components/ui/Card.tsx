import type { HTMLAttributes, ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
  /** Adds a subtle lift on hover; use for cards that navigate somewhere. */
  interactive?: boolean
}

export function Card({ interactive = false, className, ...props }: CardProps) {
  return (
    <div
      className={cn(
        'rounded-card border border-surface-200 bg-panel shadow-card',
        interactive && 'transition-shadow duration-200 hover:shadow-card-hover',
        className,
      )}
      {...props}
    />
  )
}

export interface CardHeaderProps extends Omit<HTMLAttributes<HTMLDivElement>, 'title'> {
  title: ReactNode
  description?: ReactNode
  /** Rendered on the right, typically actions or a filter control. */
  action?: ReactNode
}

export function CardHeader({ title, description, action, className, ...props }: CardHeaderProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-start justify-between gap-3 border-b border-surface-200 px-4 py-4 sm:flex-row sm:px-6',
        className,
      )}
      {...props}
    >
      <div className="min-w-0">
        <h3 className="truncate text-sm font-semibold text-surface-900">{title}</h3>
        {description && <p className="mt-1 text-sm text-surface-500">{description}</p>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

export function CardContent({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('px-4 py-5 sm:px-6', className)} {...props} />
}

export function CardFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        'flex flex-col-reverse items-stretch gap-2 border-t border-surface-200 bg-surface-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-end sm:gap-3 sm:px-6',
        className,
      )}
      {...props}
    />
  )
}
