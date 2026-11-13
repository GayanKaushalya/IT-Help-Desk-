import { Info, LockKeyhole, UserRound, UserPlus } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Form } from '@/components/ui/Form'
import { Input } from '@/components/ui/Input'
import { PasswordInput } from '@/components/ui/PasswordInput'
import { usePublicLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { cn } from '@/utils/cn'
import { homePathFor, isDeepLinkPath } from '@/utils/roles'

interface LoginLocationState {
  from?: string
  reason?: 'expired'
}

const fieldClass =
  'h-11 border-[#2a6ea8]/55 bg-[#071526]/75 text-white shadow-none placeholder:text-white/40 focus:border-[#5ec8ff] focus:ring-[#5ec8ff]/25'

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
    <div className="auth-glass rounded-[1.35rem] border border-[#5ec8ff]/35 bg-[#071526]/72 p-6 shadow-[0_0_0_1px_rgba(94,200,255,0.12),0_24px_60px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-8">
      <div className="flex flex-col items-center text-center">
        <img
          src="/images/indra-mark.png"
          alt="Indra"
          className="size-16 drop-shadow-[0_8px_24px_rgba(196,16,28,0.5)]"
          draggable={false}
        />
        <p className="mt-4 text-[11px] font-semibold tracking-[0.22em] text-white/70 uppercase">
          Secure access
        </p>
        <h2 className="mt-2 text-xl font-semibold tracking-tight text-white sm:text-2xl">
          Sign in to <span className="text-[#e11d2e]">Indra Help Desk</span>
        </h2>
        <p className="mt-1.5 text-sm text-white/55">Use your account to continue</p>
      </div>

      {error && (
        <Alert tone="danger" title="Sign-in failed" className="mt-6 border-danger-400/40 bg-danger-950/50 text-danger-100">
          {error}
        </Alert>
      )}

      <Form onSubmit={handleSubmit} className="mt-6 space-y-4">
        <div className="space-y-1.5">
          <label htmlFor="login-username" className="sr-only">
            Username
          </label>
          <Input
            id="login-username"
            name="username"
            autoComplete="username"
            autoFocus
            hasError={Boolean(fieldErrors.username)}
            value={username}
            onChange={(event) => setUsername(event.target.value)}
            placeholder="Username"
            leadingIcon={<UserRound className="size-4 text-white/55" />}
            className={cn(fieldClass, '[&+span]:text-white/55')}
          />
          {fieldErrors.username && (
            <p className="text-xs text-danger-300">{fieldErrors.username}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="login-password" className="sr-only">
            Password
          </label>
          <PasswordInput
            id="login-password"
            name="password"
            autoComplete="current-password"
            hasError={Boolean(fieldErrors.password)}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            placeholder="Password"
            leadingIcon={<LockKeyhole className="size-4 text-white/55" />}
            className={fieldClass}
          />
          {fieldErrors.password && (
            <p className="text-xs text-danger-300">{fieldErrors.password}</p>
          )}
        </div>

        <div className="flex items-center justify-between gap-3 pt-1">
          <label className="inline-flex cursor-pointer items-center gap-2 select-none">
            <input
              type="checkbox"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
              className="size-4 rounded border-[#2a6ea8]/70 bg-[#071526] text-[#1f7ae0] focus:ring-[#5ec8ff]/30 focus:ring-offset-0"
            />
            <span className="text-sm text-white/75">Remember me</span>
          </label>

          <span className="cursor-not-allowed text-sm text-[#5ec8ff]/70" title="Coming soon">
            Forgot password?
          </span>
        </div>

        <Button
          type="submit"
          fullWidth
          size="lg"
          isLoading={isSubmitting}
          className="mt-1 h-11 border-0 bg-gradient-to-b from-[#f04343] to-[#c4101c] text-white shadow-[0_10px_24px_rgba(196,16,28,0.4)] hover:brightness-110"
        >
          Sign in
        </Button>
      </Form>

      {registrationOpen && (
        <>
          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center" aria-hidden>
              <div className="w-full border-t border-white/15" />
            </div>
            <p className="relative mx-auto w-fit bg-transparent px-3 text-xs tracking-[0.18em] text-white/45 uppercase">
              Or
            </p>
          </div>

          <Button
            variant="outline"
            fullWidth
            leadingIcon={<UserPlus className="size-4" />}
            onClick={() => void navigate(paths.auth.register)}
            className="h-11 border-[#5ec8ff]/40 bg-transparent text-white hover:bg-white/5 hover:text-white"
          >
            Create account
          </Button>
        </>
      )}

      <p className="mt-6 flex items-start gap-2 text-center text-xs leading-5 text-[#5ec8ff]/90 sm:items-center sm:justify-center">
        <Info className="mt-0.5 size-3.5 shrink-0 sm:mt-0" aria-hidden />
        <span>
          For assistance, contact the HR Department or IT Help Desk.
          {!registrationOpen && (
            <>
              {' '}
              Self-service registration is closed — ask your administrator for an account.
            </>
          )}
        </span>
      </p>

      {registrationOpen && (
        <p className="mt-3 text-center text-xs text-white/40">
          New employee?{' '}
          <Link to={paths.auth.register} className="font-medium text-[#5ec8ff] hover:text-[#8ad8ff]">
            Create an account
          </Link>
        </p>
      )}
    </div>
  )
}
