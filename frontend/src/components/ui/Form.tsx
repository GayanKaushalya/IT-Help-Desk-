import type { FormEvent, FormHTMLAttributes, ReactNode } from 'react'

import { cn } from '@/utils/cn'

export interface FormProps extends Omit<FormHTMLAttributes<HTMLFormElement>, 'onSubmit'> {
  children: ReactNode
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}

/**
 * Standard form wrapper: prevents a native navigation and applies the
 * spacing used across every screen that collects input.
 */
export function Form({ children, onSubmit, className, ...props }: FormProps) {
  return (
    <form
      noValidate
      className={cn('space-y-5', className)}
      onSubmit={(event) => {
        event.preventDefault()
        onSubmit(event)
      }}
      {...props}
    >
      {children}
    </form>
  )
}
