import { ChevronDown } from 'lucide-react'
import { forwardRef, type SelectHTMLAttributes } from 'react'

import { cn } from '@/utils/cn'

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  hasError?: boolean
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { hasError = false, className, children, ...props },
  ref,
) {
  return (
    <div className="relative">
      <select
        ref={ref}
        aria-invalid={hasError || undefined}
        className={cn(
          'h-10 w-full appearance-none rounded-lg border bg-panel pr-9 pl-3 text-sm text-surface-900 shadow-sm',
          'transition-colors duration-150',
          'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none',
          'disabled:cursor-not-allowed disabled:bg-surface-50 disabled:text-surface-400',
          hasError
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
            : 'border-surface-300',
          className,
        )}
        {...props}
      >
        {children}
      </select>

      <ChevronDown
        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-surface-400"
        aria-hidden
      />
    </div>
  )
})
