import { ArrowLeft, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import { HrAttachments } from '@/features/hr/HrAttachments'
import { HrComments } from '@/features/hr/HrComments'
import { hrAssigneeLabel } from '@/features/hr/hrAssigneeLabel'
import { HrRequestFlags, HrStatusBadge } from '@/features/hr/HrStatusBadge'
import { HrTimeline } from '@/features/hr/HrTimeline'
import { VerifyHrResolutionModal } from '@/features/hr/VerifyHrResolutionModal'
import { useHrRequest } from '@/features/hr/useHr'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

export function HrRequestDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: request, isPending, isError, error, refetch, isRefetching } = useHrRequest(id)
  const [verifyOpen, setVerifyOpen] = useState(false)

  if (isPending) {
    return <LoadingPanel label="Loading HR request..." />
  }

  if (isError || !request) {
    const missing = error instanceof ApiError && (error.status === 404 || error.isForbidden)

    return (
      <div className="space-y-6">
        <PageHeader title="HR request" description="This request could not be opened." />
        <Card>
          <ErrorState
            title={missing ? 'Request not found' : 'Unable to load request'}
            description={
              missing
                ? 'This request does not exist or you do not have access to it.'
                : error instanceof ApiError
                  ? error.message
                  : 'The request could not be loaded. Please try again.'
            }
            onRetry={missing ? undefined : () => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
        <Button variant="outline" leadingIcon={<ArrowLeft className="size-4" />} onClick={() => void navigate(paths.hr.requests)}>
          Back to My HR Requests
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.hr.requests)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            &larr; My HR Requests
          </button>
        }
        title={request.request_type?.name ?? 'HR request'}
        description={request.reference}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <HrRequestFlags request={request} />
            <HrStatusBadge status={request.status} awaitingVerification={request.awaiting_verification} />
          </div>
        }
      />

      {(request.awaiting_verification || request.resolution_summary || request.verification?.status === 'verified') && (
        <Card
          className={
            request.awaiting_verification
              ? 'border-warning-200 bg-warning-50/70'
              : request.verification?.status === 'verified' || request.status?.slug === 'closed'
                ? 'border-success-200 bg-success-50/70'
                : undefined
          }
        >
          <CardContent className="space-y-4 py-6">
            <div className="flex gap-3">
              <span
                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${
                  request.awaiting_verification
                    ? 'bg-warning-100 text-warning-700'
                    : 'bg-success-100 text-success-700'
                }`}
              >
                <ShieldCheck className="size-5" />
              </span>
              <div className="min-w-0 flex-1">
                <h2 className="text-sm font-semibold text-surface-900">
                  {request.awaiting_verification
                    ? 'HR has marked this request as resolved. Please confirm whether it is complete.'
                    : request.verification?.status === 'verified' || request.status?.slug === 'closed'
                      ? 'You confirmed this resolution. The request is closed.'
                      : 'This request was resolved.'}
                </h2>
                <dl className="mt-3 grid gap-3 sm:grid-cols-3">
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Resolution Summary</dt>
                    <dd className="mt-1 text-sm text-surface-800">{request.resolution_summary || '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Resolved By</dt>
                    <dd className="mt-1 text-sm text-surface-800">{request.resolved_by?.full_name || '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">
                      {request.verification?.status === 'verified' ? 'Confirmed' : 'Resolved Date'}
                    </dt>
                    <dd className="mt-1 text-sm text-surface-800">
                      {formatDateTime(
                        request.verification?.status === 'verified'
                          ? (request.verification.completed_at ?? request.resolved_at)
                          : request.resolved_at,
                      )}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>

            {request.awaiting_verification && (
              <div className="flex flex-col gap-3 border-t border-warning-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-surface-700">
                  Confirm if the request is complete, or tell HR if you still need help.
                </p>
                <Button onClick={() => setVerifyOpen(true)}>Confirm resolution</Button>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card>
            <CardHeader title="Request" description="What you asked the HR team." />
            <CardContent>
              <p className="text-sm leading-6 whitespace-pre-wrap text-surface-700">
                {request.description || 'No description was provided.'}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Conversation" description="Messages between you and the HR team. Internal HR notes are never shown." />
            <CardContent>
              <HrComments request={request} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Attachments" description="Files uploaded with this request." />
            <CardContent>
              <HrAttachments attachments={request.attachments ?? []} />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Request details" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Reference" mono>
                  {request.reference}
                </DescriptionItem>
                <DescriptionItem label="Category">{request.category?.name}</DescriptionItem>
                <DescriptionItem label="Request type">{request.request_type?.name}</DescriptionItem>
                <DescriptionItem label="Assigned to">{hrAssigneeLabel(request)}</DescriptionItem>
                <DescriptionItem label="Submitted">{formatDateTime(request.created_at)}</DescriptionItem>
                <DescriptionItem label="Target date">{formatDateTime(request.due_at)}</DescriptionItem>
                {(request.resolved_by || request.resolved_at) && (
                  <DescriptionItem label="Resolved by">{request.resolved_by?.full_name ?? '—'}</DescriptionItem>
                )}
                <DescriptionItem label="Last updated">{formatDateTime(request.updated_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="History" description="Status changes on this request." />
            <CardContent>
              <HrTimeline events={request.timeline ?? []} />
            </CardContent>
          </Card>
        </div>
      </div>

      <VerifyHrResolutionModal request={request} isOpen={verifyOpen} onClose={() => setVerifyOpen(false)} />
    </div>
  )
}
