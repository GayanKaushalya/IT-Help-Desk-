import { useState, type FormEvent, type ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { ErrorState } from '@/components/ui/ErrorState'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { PasswordInput } from '@/components/ui/PasswordInput'
import { PasswordStrength } from '@/components/ui/PasswordStrength'
import { Select } from '@/components/ui/Select'
import { LoadingPanel } from '@/components/ui/Spinner'
import { usePublicLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import {
  validateRegistration,
  type RegistrationFormValues,
} from '@/features/auth/registrationValidation'

const EMPTY_FORM: RegistrationFormValues = {
  full_name: '',
  branch_id: '',
  employee_id: '',
  username: '',
  password: '',
  password_confirmation: '',
}

export function RegisterPage() {
  const { register } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const { data: lookups, isPending, isError, refetch, isRefetching } = usePublicLookups()

  const [values, setValues] = useState<RegistrationFormValues>(EMPTY_FORM)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function update<K extends keyof RegistrationFormValues>(field: K, value: RegistrationFormValues[K]) {
    setValues((current) => ({ ...current, [field]: value }))
    setFieldErrors((current) => {
      if (!current[field]) return current
      const next = { ...current }
      delete next[field]
      return next
    })
  }

  function validateField(field: keyof RegistrationFormValues) {
    const result = validateRegistration(values)
    setFieldErrors((current) => {
      const next = { ...current }
      if (result[field]) {
        next[field] = result[field]
      } else {
        delete next[field]
      }
      return next
    })
  }

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setFormError(null)

    const clientErrors = validateRegistration(values)
    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      return
    }

    setIsSubmitting(true)

    try {
      const user = await register({
        full_name: values.full_name.trim(),
        branch_id: Number(values.branch_id),
        employee_id: values.employee_id.trim(),
        username: values.username.trim(),
        password: values.password,
        password_confirmation: values.password_confirmation,
      })

      toast.success('Account created', `Welcome, ${user.full_name}.`)
      navigate(paths.dashboard, { replace: true })
    } catch (caught) {
      setIsSubmitting(false)

      if (caught instanceof ApiError) {
        if (caught.isValidationError) {
          setFieldErrors(caught.fieldErrors)
          setFormError('Please correct the highlighted fields and try again.')
        } else {
          setFormError(caught.message)
        }
      } else {
        setFormError('Unable to create your account right now. Please try again.')
      }
    }
  }

  if (isPending) {
    return <LoadingPanel label="Loading branches…" />
  }

  if (isError || !lookups) {
    return (
      <ErrorState
        title="Unable to load registration"
        description="Branches could not be loaded. Check that the API is running and try again."
        onRetry={() => void refetch()}
        isRetrying={isRefetching}
      />
    )
  }

  if (lookups.allow_self_registration === false) {
    return (
      <div className="rounded-[1.35rem] border border-white/20 bg-white/95 p-6 shadow-[0_24px_60px_rgba(0,0,0,0.35)] backdrop-blur-xl sm:p-8">
        <h1 className="text-2xl font-semibold tracking-tight text-surface-900">Registration is closed</h1>
        <p className="mt-2 text-sm text-surface-500">
          Self-service registration is currently disabled. Contact IT or HR for an Indra Help Desk account.
        </p>
        <div className="mt-6">
          <Alert tone="warning" title="Ask for access">
            {lookups.support_email
              ? `Reach the service desk at ${lookups.support_email}.`
              : 'Your administrator has turned off new employee sign-up.'}
          </Alert>
          <p className="mt-6 text-center text-sm text-surface-500">
            Already have an account?{' '}
            <Link to={paths.auth.login} className="font-medium text-primary-600 hover:text-primary-700">
              Sign in
            </Link>
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="rounded-[1.35rem] border border-white/20 bg-white/95 p-6 shadow-[0_24px_60px_rgba(0,0,0,0.35)] backdrop-blur-xl sm:p-8">
      <p className="text-xs font-semibold tracking-[0.18em] text-[#e11d2e] uppercase">New employee</p>
      <h1 className="mt-2 text-2xl font-semibold tracking-tight text-surface-900">Create your account</h1>
      <p className="mt-2 text-sm text-surface-500">
        Register once for Indra Help Desk. You will be signed in and can open IT or HR support from the
        desk chooser.
      </p>

      <div className="mt-8">
        {formError && (
          <Alert tone="danger" title="Registration failed" className="mb-6">
            {formError}
          </Alert>
        )}

        <Form onSubmit={handleSubmit} className="space-y-8">
          <FormSection title="Personal information" description="How you appear on tickets and in the directory.">
            <FormField label="Full name" required error={fieldErrors.full_name}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  name="full_name"
                  autoComplete="name"
                  autoFocus
                  hasError={hasError}
                  value={values.full_name}
                  disabled={isSubmitting}
                  onChange={(event) => update('full_name', event.target.value)}
                  onBlur={() => validateField('full_name')}
                  placeholder="Nimal Perera"
                />
              )}
            </FormField>

            <FormField label="Branch" required error={fieldErrors.branch_id} hint="Select the location you work from.">
              {({ id, hasError }) => (
                <Select
                  id={id}
                  name="branch_id"
                  hasError={hasError}
                  value={values.branch_id}
                  disabled={isSubmitting}
                  onChange={(event) => update('branch_id', event.target.value)}
                  onBlur={() => validateField('branch_id')}
                >
                  <option value="">Select a branch</option>
                  {lookups.branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                      {branch.city ? ` · ${branch.city}` : ''}
                    </option>
                  ))}
                </Select>
              )}
            </FormField>
          </FormSection>

          <FormSection title="Employee information" description="Must match your HR records.">
            <FormField label="Employee ID" required error={fieldErrors.employee_id}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  name="employee_id"
                  autoComplete="off"
                  hasError={hasError}
                  value={values.employee_id}
                  disabled={isSubmitting}
                  onChange={(event) => update('employee_id', event.target.value)}
                  onBlur={() => validateField('employee_id')}
                  placeholder="EMP-1001"
                />
              )}
            </FormField>
          </FormSection>

          <FormSection title="Login information" description="Used to sign in to the helpdesk.">
            <FormField
              label="Username"
              required
              error={fieldErrors.username}
              hint="Letters, numbers, dots, hyphens and underscores. Must start with a letter."
            >
              {({ id, hasError }) => (
                <Input
                  id={id}
                  name="username"
                  autoComplete="username"
                  hasError={hasError}
                  value={values.username}
                  disabled={isSubmitting}
                  onChange={(event) => update('username', event.target.value)}
                  onBlur={() => validateField('username')}
                  placeholder="n.perera"
                />
              )}
            </FormField>

            <FormField label="Password" required error={fieldErrors.password}>
              {({ id, hasError }) => (
                <PasswordInput
                  id={id}
                  name="password"
                  autoComplete="new-password"
                  hasError={hasError}
                  value={values.password}
                  disabled={isSubmitting}
                  onChange={(event) => update('password', event.target.value)}
                  onBlur={() => validateField('password')}
                  placeholder="Create a strong password"
                />
              )}
            </FormField>

            <PasswordStrength password={values.password} />

            <FormField label="Confirm password" required error={fieldErrors.password_confirmation}>
              {({ id, hasError }) => (
                <PasswordInput
                  id={id}
                  name="password_confirmation"
                  autoComplete="new-password"
                  hasError={hasError}
                  value={values.password_confirmation}
                  disabled={isSubmitting}
                  onChange={(event) => update('password_confirmation', event.target.value)}
                  onBlur={() => validateField('password_confirmation')}
                  placeholder="Re-enter your password"
                />
              )}
            </FormField>
          </FormSection>

          <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>
            Create account
          </Button>
        </Form>

        <p className="mt-6 text-center text-sm text-surface-500">
          Already have an account?{' '}
          <Link to={paths.auth.login} className="font-medium text-primary-600 hover:text-primary-700">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  )
}

function FormSection({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: ReactNode
}) {
  return (
    <section className="space-y-4 border-b border-surface-100 pb-8 last:border-0 last:pb-0">
      <div>
        <h2 className="text-sm font-semibold tracking-wide text-surface-900 uppercase">{title}</h2>
        <p className="mt-1 text-xs text-surface-500">{description}</p>
      </div>
      {children}
    </section>
  )
}
