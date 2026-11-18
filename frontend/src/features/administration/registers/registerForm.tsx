import { useState, type FormEvent, type ReactNode } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { Modal, type ModalProps } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Textarea } from '@/components/ui/Textarea'
import { ApiError } from '@/types/api'

export interface RegisterFormState<T extends Record<string, string>> {
  values: T
  set: (name: keyof T & string, value: string) => void
  error: string | null
  errors: Record<string, string>
  /** Records an API failure so field messages and the banner both show. */
  fail: (cause: unknown, fallback: string) => void
  reset: () => void
}

/**
 * Local state for a register form. Mount the form only while its modal is open so
 * `initial` is read once per opening.
 */
export function useRegisterForm<T extends Record<string, string>>(initial: T): RegisterFormState<T> {
  const [values, setValues] = useState<T>(initial)
  const [error, setError] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  return {
    values,
    set: (name, value) => {
      setValues((current) => ({ ...current, [name]: value }))
      setErrors((current) => {
        if (!current[name]) return current
        const next = { ...current }
        delete next[name]
        return next
      })
    },
    error,
    errors,
    fail: (cause, fallback) => {
      if (cause instanceof ApiError) {
        setError(cause.message)
        setErrors(cause.fieldErrors)
        return
      }
      setError(fallback)
    },
    reset: () => {
      setError(null)
      setErrors({})
    },
  }
}

/** Empty string to null, trimmed. */
export function nullable(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

export interface RegisterFormModalProps extends Pick<ModalProps, 'title' | 'description' | 'size'> {
  onClose: () => void
  formId: string
  submitLabel: string
  isPending: boolean
  error: string | null
  onSubmit: () => Promise<void> | void
  /** Extra disabled reason, e.g. a client-side overlap warning. */
  submitDisabled?: boolean
  children: ReactNode
}

export function RegisterFormModal({
  onClose,
  formId,
  submitLabel,
  isPending,
  error,
  onSubmit,
  submitDisabled = false,
  children,
  ...modal
}: RegisterFormModalProps) {
  return (
    <Modal
      isOpen
      onClose={onClose}
      size="lg"
      {...modal}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={isPending}>
            Cancel
          </Button>
          <Button type="submit" form={formId} isLoading={isPending} disabled={submitDisabled}>
            {submitLabel}
          </Button>
        </>
      }
    >
      <form
        id={formId}
        className="space-y-4"
        noValidate
        onSubmit={(event: FormEvent<HTMLFormElement>) => {
          event.preventDefault()
          void onSubmit()
        }}
      >
        {error && (
          <Alert tone="danger" title="Could not save">
            {error}
          </Alert>
        )}
        {children}
      </form>
    </Modal>
  )
}

/** Two-column grid used inside register forms. */
export function FormGrid({ children }: { children: ReactNode }) {
  return <div className="grid gap-4 sm:grid-cols-2">{children}</div>
}

interface FieldBase<T extends Record<string, string>> {
  form: RegisterFormState<T>
  name: keyof T & string
  label: string
  required?: boolean
  hint?: string
  disabled?: boolean
  className?: string
}

export function TextField<T extends Record<string, string>>({
  form,
  name,
  label,
  required,
  hint,
  disabled,
  className,
  type = 'text',
  placeholder,
  step,
  min,
  max,
  mono,
}: FieldBase<T> & {
  type?: 'text' | 'number' | 'date' | 'email' | 'datetime-local' | 'tel'
  placeholder?: string
  step?: string
  min?: string
  max?: string
  mono?: boolean
}) {
  return (
    <FormField label={label} required={required} hint={hint} error={form.errors[name]} className={className}>
      {({ id, hasError, describedBy }) => (
        <Input
          id={id}
          type={type}
          hasError={hasError}
          aria-describedby={describedBy}
          value={form.values[name]}
          onChange={(event) => form.set(name, event.target.value)}
          placeholder={placeholder}
          step={step}
          min={min}
          max={max}
          disabled={disabled}
          className={mono ? 'font-mono' : undefined}
        />
      )}
    </FormField>
  )
}

export interface SelectOption {
  value: string
  label: string
}

export function SelectField<T extends Record<string, string>>({
  form,
  name,
  label,
  required,
  hint,
  disabled,
  className,
  options,
  emptyLabel,
}: FieldBase<T> & { options: SelectOption[]; emptyLabel?: string }) {
  return (
    <FormField label={label} required={required} hint={hint} error={form.errors[name]} className={className}>
      {({ id, hasError, describedBy }) => (
        <Select
          id={id}
          hasError={hasError}
          aria-describedby={describedBy}
          value={form.values[name]}
          onChange={(event) => form.set(name, event.target.value)}
          disabled={disabled}
        >
          {emptyLabel !== undefined && <option value="">{emptyLabel}</option>}
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      )}
    </FormField>
  )
}

export function TextAreaField<T extends Record<string, string>>({
  form,
  name,
  label,
  required,
  hint,
  disabled,
  className,
  rows = 3,
}: FieldBase<T> & { rows?: number }) {
  return (
    <FormField label={label} required={required} hint={hint} error={form.errors[name]} className={className}>
      {({ id, hasError, describedBy }) => (
        <Textarea
          id={id}
          rows={rows}
          hasError={hasError}
          aria-describedby={describedBy}
          value={form.values[name]}
          onChange={(event) => form.set(name, event.target.value)}
          disabled={disabled}
        />
      )}
    </FormField>
  )
}

export function statusOptions(values: readonly string[] | undefined): SelectOption[] {
  return (values ?? []).map((value) => ({ value, label: humanize(value) }))
}

export function humanize(value: string): string {
  const text = value.replace(/_/g, ' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
}
