import { forwardRef, type InputHTMLAttributes, type ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  hasError?: boolean
  leadingIcon?: ReactNode
  trailingElement?: ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { hasError = false, leadingIcon, trailingElement, className, ...props },
  ref,
) {
  return (
    <div className="relative">
      {leadingIcon && (
        <span
          className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-surface-400"
          aria-hidden
        >
          {leadingIcon}
        </span>
      )}

      <input
        ref={ref}
        aria-invalid={hasError || undefined}
        className={cn(
          'h-10 w-full rounded-lg border bg-panel px-3 text-sm text-surface-900 shadow-sm',
          'placeholder:text-surface-400',
          'transition-colors duration-150',
          'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none',
          'disabled:cursor-not-allowed disabled:bg-surface-50 disabled:text-surface-400',
          hasError
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
            : 'border-surface-300',
          leadingIcon && 'pl-9',
          trailingElement && 'pr-10',
          className,
        )}
        {...props}
      />

      {trailingElement && (
        <span className="absolute inset-y-0 right-2 flex items-center">{trailingElement}</span>
      )}
    </div>
  )
})
