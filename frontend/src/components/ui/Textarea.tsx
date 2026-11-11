import { forwardRef, type TextareaHTMLAttributes } from 'react'

import { cn } from '@/utils/cn'

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  hasError?: boolean
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { hasError = false, className, rows = 4, ...props },
  ref,
) {
  return (
    <textarea
      ref={ref}
      rows={rows}
      aria-invalid={hasError || undefined}
      className={cn(
        'w-full rounded-lg border bg-panel px-3 py-2 text-sm text-surface-900 shadow-sm',
        'placeholder:text-surface-400',
        'transition-colors duration-150',
        'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none',
        'disabled:cursor-not-allowed disabled:bg-surface-50 disabled:text-surface-400',
        hasError
          ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
          : 'border-surface-300',
        className,
      )}
      {...props}
    />
  )
})
