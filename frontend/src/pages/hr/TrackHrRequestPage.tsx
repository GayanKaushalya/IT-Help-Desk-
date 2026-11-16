import { Search } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/Card'
import { Form } from '@/components/ui/Form'
import { FormField } from '@/components/ui/FormField'
import { Input } from '@/components/ui/Input'
import { LoadingPanel } from '@/components/ui/Spinner'
import { hrAssigneeLabel } from '@/features/hr/hrAssigneeLabel'
import { HrRequestFlags, HrStatusBadge } from '@/features/hr/HrStatusBadge'
import { HrTimeline } from '@/features/hr/HrTimeline'
import { hrKeys } from '@/features/hr/useHr'
import { paths } from '@/routes/paths'
import { hrService } from '@/services/hrService'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

const REFERENCE_PATTERN = /^[A-Za-z]{2,10}-\d{4}-\d{3,}$/

export function TrackHrRequestPage() {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const submitted = (params.get('reference') ?? '').trim().toUpperCase()

  const [draft, setDraft] = useState(submitted)
  const [validationError, setValidationError] = useState<string | null>(null)

  const query = useQuery({
    queryKey: hrKeys.track(submitted),
    queryFn: () => hrService.track(submitted),
    enabled: submitted.length > 0,
    retry: false,
  })

  function handleSubmit(_event: FormEvent<HTMLFormElement>): void {
    const value = draft.trim().toUpperCase()

    if (!value) {
      setValidationError('Enter the reference number of your request.')
      return
    }

    if (!REFERENCE_PATTERN.test(value)) {
      setValidationError('References look like HR-2026-000001.')
      return
    }

    setValidationError(null)
    setParams({ reference: value })
  }

  const request = query.data
  const notFound = query.error instanceof ApiError && (query.error.status === 404 || query.error.isForbidden)

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader title="Track Request" description="Look up the status of an HR request using its reference number." />

      <Card>
        <CardContent>
          <Form onSubmit={handleSubmit} className="flex flex-col gap-4 sm:flex-row sm:items-end">
            <FormField label="Reference number" error={validationError ?? undefined} className="flex-1">
              {({ id, hasError }) => (
                <Input
                  id={id}
                  hasError={hasError}
                  value={draft}
                  onChange={(event) => {
                    setDraft(event.target.value)
                    setValidationError(null)
                  }}
                  placeholder="HR-2026-000001"
                  leadingIcon={<Search className="size-4" />}
                  autoComplete="off"
                />
              )}
            </FormField>
            <Button type="submit" isLoading={query.isFetching}>
              Track request
            </Button>
          </Form>
        </CardContent>
      </Card>

      {submitted && query.isPending && query.isFetching && <LoadingPanel label="Looking up your request..." />}

      {query.isError && (
        <Alert tone={notFound ? 'warning' : 'danger'} title={notFound ? 'Request not found' : 'Unable to track request'}>
          {notFound
            ? `No request with reference ${submitted} was found. Check the number and try again. You can only track requests you raised.`
            : query.error instanceof ApiError
              ? query.error.message
              : 'The request could not be looked up. Please try again.'}
        </Alert>
      )}

      {request && (
        <Card>
          <CardHeader
            title={request.request_type?.name ?? 'HR request'}
            description={request.reference}
            action={
              <div className="flex flex-wrap items-center gap-2">
                <HrRequestFlags request={request} />
                <HrStatusBadge status={request.status} awaitingVerification={request.awaiting_verification} />
              </div>
            }
          />
          <CardContent className="grid gap-6 md:grid-cols-2">
            <DescriptionList>
              <DescriptionItem label="Category">{request.category?.name}</DescriptionItem>
              <DescriptionItem label="Assigned to">{hrAssigneeLabel(request)}</DescriptionItem>
              <DescriptionItem label="Submitted">{formatDateTime(request.created_at)}</DescriptionItem>
              <DescriptionItem label="Target date">{formatDateTime(request.due_at)}</DescriptionItem>
              {request.resolution_summary && (
                <DescriptionItem label="Resolution">{request.resolution_summary}</DescriptionItem>
              )}
            </DescriptionList>
            <div>
              <p className="mb-3 text-xs font-medium tracking-wide text-surface-500 uppercase">History</p>
              <HrTimeline events={request.timeline ?? []} />
            </div>
          </CardContent>
          <CardFooter>
            <Button variant="outline" onClick={() => void navigate(paths.hr.detail(request.id))}>
              Open full request
            </Button>
          </CardFooter>
        </Card>
      )}
    </div>
  )
}
