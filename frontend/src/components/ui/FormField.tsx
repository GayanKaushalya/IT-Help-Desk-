import { useId, type ReactElement, type ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface FormFieldProps {
  label: string
  /** Validation message from the API or client-side rules. */
  error?: string
  hint?: string
  required?: boolean
  className?: string
  /** Receives the generated id so the label and control stay associated. */
  children: (fieldProps: { id: string; hasError: boolean; describedBy?: string }) => ReactElement
}

/**
 * Wraps a control with its label, hint and error text.
 *
 * Using a render prop keeps the accessibility wiring in one place while still
 * working with any of the input components.
 */
export function FormField({
  label,
  error,
  hint,
  required = false,
  className,
  children,
}: FormFieldProps): ReactNode {
  const id = useId()
  const hasError = Boolean(error)

  const hintId = `${id}-hint`
  const errorId = `${id}-error`

  return (
    <div className={cn('space-y-1.5', className)}>
      <label htmlFor={id} className="block text-sm font-medium text-surface-700">
        {label}
        {required && (
          <span className="ml-0.5 text-danger-600" aria-hidden>
            *
          </span>
        )}
        {required && <span className="sr-only"> (required)</span>}
      </label>

      {children({
        id,
        hasError,
        describedBy: hasError ? errorId : hint ? hintId : undefined,
      })}

      {hasError ? (
        <p id={errorId} role="alert" className="text-xs text-danger-600">
          {error}
        </p>
      ) : (
        hint && (
          <p id={hintId} className="text-xs text-surface-500">
            {hint}
          </p>
        )
      )}
    </div>
  )
}
