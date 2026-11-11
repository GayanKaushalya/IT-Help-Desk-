import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: ReactNode
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(function Checkbox(
  { label, className, id, ...props },
  ref,
) {
  const generatedId = useId()
  const fieldId = id ?? generatedId

  return (
    <label htmlFor={fieldId} className="inline-flex cursor-pointer items-center gap-2 select-none">
      <input
        ref={ref}
        id={fieldId}
        type="checkbox"
        className={cn(
          'size-4 rounded border-surface-300 text-primary-600',
          'focus:ring-2 focus:ring-primary-500/20 focus:ring-offset-0',
          'disabled:cursor-not-allowed disabled:opacity-60',
          className,
        )}
        {...props}
      />
      <span className="text-sm text-surface-600">{label}</span>
    </label>
  )
})
