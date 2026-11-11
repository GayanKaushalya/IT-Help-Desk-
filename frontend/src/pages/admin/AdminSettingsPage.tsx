import { useEffect, useState, type FormEvent } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/Card'
import { Checkbox } from '@/components/ui/Checkbox'
import { ErrorState } from '@/components/ui/ErrorState'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { LoadingPanel } from '@/components/ui/Spinner'
import { useAdminSettings, useUpdateAdminSettings } from '@/features/admin/useAdminSettings'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'

export function AdminSettingsPage() {
  const toast = useToast()
  const query = useAdminSettings()
  const update = useUpdateAdminSettings()
  const [values, setValues] = useState({
    organisation_name: '',
    support_email: '',
    support_phone: '',
    support_hours: '',
    allow_self_registration: true,
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!query.data) return
    setValues({
      organisation_name: query.data.organisation_name,
      support_email: query.data.support_email,
      support_phone: query.data.support_phone,
      support_hours: query.data.support_hours,
      allow_self_registration: query.data.allow_self_registration === '1',
    })
  }, [query.data])

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    setError(null)
    setFieldErrors({})

    try {
      await update.mutateAsync({
        organisation_name: values.organisation_name.trim(),
        support_email: values.support_email.trim(),
        support_phone: values.support_phone.trim(),
        support_hours: values.support_hours.trim(),
        allow_self_registration: values.allow_self_registration,
      })
      toast.success('Settings saved', 'Organisation-wide configuration has been updated.')
    } catch (caught) {
      if (caught instanceof ApiError) {
        if (caught.isValidationError) setFieldErrors(caught.fieldErrors)
        setError(caught.message)
      } else {
        setError('Unable to save settings right now.')
      }
    }
  }

  if (query.isPending) {
    return <LoadingPanel label="Loading settings…" />
  }

  if (query.isError || !query.data) {
    return (
      <ErrorState
        title="Unable to load settings"
        description={query.error instanceof ApiError ? query.error.message : 'Settings could not be loaded.'}
        onRetry={() => void query.refetch()}
        isRetrying={query.isRefetching}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="System settings"
        description="Organisation-wide configuration for the service desk."
      />

      <Card>
        <CardHeader
          title="Organisation"
          description="These details appear on registration, contact cards and employee-facing pages."
        />
        <Form onSubmit={handleSubmit}>
          <CardContent className="grid gap-4 md:max-w-xl">
            {error && (
              <Alert tone="danger" title="Save failed">
                {error}
              </Alert>
            )}
            <FormField label="Organisation name" required error={fieldErrors.organisation_name}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={values.organisation_name}
                  onChange={(event) =>
                    setValues((current) => ({ ...current, organisation_name: event.target.value }))
                  }
                />
              )}
            </FormField>
            <FormField label="Support email" error={fieldErrors.support_email}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  type="email"
                  hasError={hasError}
                  value={values.support_email}
                  onChange={(event) => setValues((current) => ({ ...current, support_email: event.target.value }))}
                />
              )}
            </FormField>
            <FormField label="Support phone" error={fieldErrors.support_phone}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={values.support_phone}
                  onChange={(event) => setValues((current) => ({ ...current, support_phone: event.target.value }))}
                />
              )}
            </FormField>
            <FormField label="Support hours" error={fieldErrors.support_hours}>
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={values.support_hours}
                  onChange={(event) => setValues((current) => ({ ...current, support_hours: event.target.value }))}
                />
              )}
            </FormField>
            <Checkbox
              label="Allow employees to register their own accounts"
              checked={values.allow_self_registration}
              onChange={(event) =>
                setValues((current) => ({ ...current, allow_self_registration: event.target.checked }))
              }
            />
          </CardContent>
          <CardFooter>
            <Button type="submit" isLoading={update.isPending}>
              Save settings
            </Button>
          </CardFooter>
        </Form>
      </Card>
    </div>
  )
}
