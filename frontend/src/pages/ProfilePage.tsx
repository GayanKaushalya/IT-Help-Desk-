import { useState, type FormEvent } from 'react'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/Card'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { PasswordInput } from '@/components/ui/PasswordInput'
import { PasswordStrength } from '@/components/ui/PasswordStrength'
import { useChangePassword, useUpdateProfile } from '@/features/profile/useProfile'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'

export type ProfileDesk = 'it' | 'hr'

export interface ProfilePageProps {
  /** Which help desk shell this profile belongs to. */
  desk?: ProfileDesk
}

export function ProfilePage({ desk = 'it' }: ProfilePageProps) {
  const { user } = useAuth()
  const toast = useToast()
  const updateProfile = useUpdateProfile()
  const changePassword = useChangePassword()
  const isHr = desk === 'hr'

  const [contact, setContact] = useState({
    email: user?.email ?? '',
    phone: user?.phone ?? '',
    job_title: user?.job_title ?? '',
  })
  const [contactError, setContactError] = useState<string | null>(null)
  const [contactFieldErrors, setContactFieldErrors] = useState<Record<string, string>>({})

  const [passwords, setPasswords] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [passwordFieldErrors, setPasswordFieldErrors] = useState<Record<string, string>>({})

  if (!user) return null

  async function handleContact(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setContactError(null)
    setContactFieldErrors({})

    try {
      await updateProfile.mutateAsync({
        email: contact.email.trim() || null,
        phone: contact.phone.trim() || null,
        job_title: contact.job_title.trim() || null,
      })
      toast.success('Profile updated', 'Your contact details have been saved.')
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setContactFieldErrors(caught.fieldErrors)
        setContactError(caught.message)
      } else {
        setContactError('Unable to update your profile right now.')
      }
    }
  }

  async function handlePassword(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setPasswordError(null)
    setPasswordFieldErrors({})

    if (passwords.password !== passwords.password_confirmation) {
      setPasswordFieldErrors({ password_confirmation: 'The confirmation does not match.' })
      return
    }

    try {
      await changePassword.mutateAsync(passwords)
      setPasswords({ current_password: '', password: '', password_confirmation: '' })
      toast.success('Password changed', 'Other sessions have been signed out.')
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setPasswordFieldErrors(caught.fieldErrors)
        setPasswordError(caught.message)
      } else {
        setPasswordError('Unable to change your password right now.')
      }
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={isHr ? 'HR profile' : 'IT Helpdesk profile'}
        description={
          isHr
            ? 'Your HR Help Desk account details and password.'
            : 'Your IT Helpdesk account details and password.'
        }
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader
            title="Account"
            description={isHr ? 'These identifiers are managed by HR / IT administration.' : 'These identifiers are managed by IT.'}
          />
          <CardContent>
            <DescriptionList>
              <DescriptionItem label="Full name">{user.full_name}</DescriptionItem>
              <DescriptionItem label="Username" mono>
                {user.username}
              </DescriptionItem>
              <DescriptionItem label="Employee ID" mono>
                {user.employee_id}
              </DescriptionItem>
              <DescriptionItem label="EPF number" mono>
                {user.epf_number}
              </DescriptionItem>
              <DescriptionItem label="Role">{user.role?.name ?? '—'}</DescriptionItem>
              <DescriptionItem label="Branch">{user.branch?.name ?? '—'}</DescriptionItem>
              <DescriptionItem label="Department">{user.department?.name ?? '—'}</DescriptionItem>
              <DescriptionItem label="Help desk">{isHr ? 'HR Help Desk' : 'IT Helpdesk'}</DescriptionItem>
            </DescriptionList>
          </CardContent>
        </Card>

        <Card>
          <CardHeader
            title="Contact details"
            description={isHr ? 'Used when HR needs to reach you about a request.' : 'Used when IT needs to reach you.'}
          />
          <Form onSubmit={handleContact}>
            <CardContent className="space-y-4">
              {contactError && (
                <Alert tone="danger" title="Update failed">
                  {contactError}
                </Alert>
              )}
              <FormField label="Email" error={contactFieldErrors.email}>
                {({ id, hasError }) => (
                  <Input
                    id={id}
                    type="email"
                    autoComplete="email"
                    hasError={hasError}
                    value={contact.email}
                    onChange={(event) => setContact((current) => ({ ...current, email: event.target.value }))}
                  />
                )}
              </FormField>
              <FormField label="Phone" error={contactFieldErrors.phone}>
                {({ id, hasError }) => (
                  <Input
                    id={id}
                    type="tel"
                    autoComplete="tel"
                    hasError={hasError}
                    value={contact.phone}
                    onChange={(event) => setContact((current) => ({ ...current, phone: event.target.value }))}
                  />
                )}
              </FormField>
              <FormField label="Job title" error={contactFieldErrors.job_title}>
                {({ id, hasError }) => (
                  <Input
                    id={id}
                    hasError={hasError}
                    value={contact.job_title}
                    onChange={(event) => setContact((current) => ({ ...current, job_title: event.target.value }))}
                  />
                )}
              </FormField>
            </CardContent>
            <CardFooter>
              <Button type="submit" isLoading={updateProfile.isPending}>
                Save contact details
              </Button>
            </CardFooter>
          </Form>
        </Card>
      </div>

      <Card>
        <CardHeader title="Password" description="Changing your password signs out every other session." />
        <Form onSubmit={handlePassword}>
          <CardContent className="grid gap-4 md:max-w-xl">
            {passwordError && (
              <Alert tone="danger" title="Password not changed">
                {passwordError}
              </Alert>
            )}
            <FormField label="Current password" required error={passwordFieldErrors.current_password}>
              {({ id, hasError }) => (
                <PasswordInput
                  id={id}
                  autoComplete="current-password"
                  hasError={hasError}
                  value={passwords.current_password}
                  onChange={(event) =>
                    setPasswords((current) => ({ ...current, current_password: event.target.value }))
                  }
                />
              )}
            </FormField>
            <FormField label="New password" required error={passwordFieldErrors.password}>
              {({ id, hasError }) => (
                <PasswordInput
                  id={id}
                  autoComplete="new-password"
                  hasError={hasError}
                  value={passwords.password}
                  onChange={(event) => setPasswords((current) => ({ ...current, password: event.target.value }))}
                />
              )}
            </FormField>
            <PasswordStrength password={passwords.password} />
            <FormField label="Confirm new password" required error={passwordFieldErrors.password_confirmation}>
              {({ id, hasError }) => (
                <PasswordInput
                  id={id}
                  autoComplete="new-password"
                  hasError={hasError}
                  value={passwords.password_confirmation}
                  onChange={(event) =>
                    setPasswords((current) => ({ ...current, password_confirmation: event.target.value }))
                  }
                />
              )}
            </FormField>
          </CardContent>
          <CardFooter>
            <Button type="submit" isLoading={changePassword.isPending}>
              Change password
            </Button>
          </CardFooter>
        </Form>
      </Card>
    </div>
  )
}
