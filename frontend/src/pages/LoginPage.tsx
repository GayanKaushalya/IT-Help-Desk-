import { LifeBuoy, LockKeyhole, UserRound } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Checkbox } from '@/components/ui/Checkbox'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { PasswordInput } from '@/components/ui/PasswordInput'
import { env } from '@/config/env'
import { usePublicLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { homePathFor, isDeepLinkPath } from '@/utils/roles'

interface LoginLocationState {
  from?: string
  reason?: 'expired'
}

export function LoginPage() {
  const { login } = useAuth()
  const { data: lookups } = usePublicLookups()
  const registrationOpen = lookups?.allow_self_registration !== false
  const navigate = useNavigate()
  const location = useLocation()
  const locationState = (location.state ?? {}) as LoginLocationState

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(
    locationState.reason === 'expired'
      ? 'Your session has expired. Please sign in again.'
      : null,
  )
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setError(null)
    setFieldErrors({})

    const nextErrors: Record<string, string> = {}
    if (!username.trim()) nextErrors.username = 'Enter your username.'
    if (!password) nextErrors.password = 'Enter your password.'

    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors)
      return
    }

    setIsSubmitting(true)

    try {
      const user = await login({
        username: username.trim(),
        password,
        remember,
      })

      const destination = isDeepLinkPath(locationState.from)
        ? (locationState.from as string)
        : homePathFor(user)

      navigate(destination, { replace: true })
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) {
          setFieldErrors(caught.fieldErrors)
        } else {
          setError(caught.message)
        }
      } else {
        setError('Unable to sign in right now. Please try again.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="animate-slide-up">
      <div className="mb-8 flex items-center gap-2.5 lg:hidden">
        <span className="flex size-9 items-center justify-center rounded-lg bg-primary-600">
          <LifeBuoy className="size-5 text-white" />
        </span>
        <span className="text-base font-semibold tracking-tight text-surface-900">{env.appName}</span>
      </div>

      <p className="text-xs font-semibold tracking-[0.18em] text-primary-600 uppercase">
        Secure access
      </p>
      <h1 className="mt-2 text-2xl font-semibold tracking-tight text-surface-900">
        Sign in to {env.appName}
      </h1>
      <p className="mt-2 text-sm text-surface-500">
        Use the username issued by your organisation. Access is limited to approved
        employees and IT staff.
      </p>

      <div className="mt-8 rounded-card border border-surface-200 bg-panel p-6 shadow-card sm:p-8">
        {error && (
          <Alert tone="danger" title="Sign-in failed" className="mb-5">
            {error}
          </Alert>
        )}

        <Form onSubmit={handleSubmit}>
          <FormField label="Username" required error={fieldErrors.username}>
            {({ id, hasError }) => (
              <Input
                id={id}
                name="username"
                autoComplete="username"
                autoFocus
                hasError={hasError}
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                placeholder="e.g. n.perera"
                leadingIcon={<UserRound className="size-4" />}
              />
            )}
          </FormField>

          <FormField label="Password" required error={fieldErrors.password}>
            {({ id, hasError }) => (
              <PasswordInput
                id={id}
                name="password"
                hasError={hasError}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Enter your password"
                leadingIcon={<LockKeyhole className="size-4" />}
              />
            )}
          </FormField>

          <div className="flex items-center justify-between gap-3">
            <Checkbox
              label="Remember me"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />

            <span className="cursor-not-allowed text-sm text-surface-400" title="Coming soon">
              Forgot password?
            </span>
          </div>

          <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>
            Sign in
          </Button>
        </Form>

        <div className="relative my-6">
          <div className="absolute inset-0 flex items-center" aria-hidden>
            <div className="w-full border-t border-surface-200" />
          </div>
          <p className="relative mx-auto w-fit bg-panel px-3 text-xs tracking-wide text-surface-400 uppercase">
            New to the service desk
          </p>
        </div>

        {registrationOpen ? (
          <>
            <Button variant="outline" fullWidth onClick={() => void navigate(paths.auth.register)}>
              Create account
            </Button>
            <p className="mt-4 text-center text-xs text-surface-400">
              New employees can register with their official details and are signed in immediately.{' '}
              <Link to={paths.auth.register} className="font-medium text-primary-600 hover:text-primary-700">
                Create an account
              </Link>
            </p>
          </>
        ) : (
          <p className="text-center text-xs text-surface-400">
            Self-service registration is closed. Contact IT support for an account.
          </p>
        )}
      </div>
    </div>
  )
}
