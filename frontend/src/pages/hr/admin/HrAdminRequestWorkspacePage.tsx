import { ArrowLeft, CheckCircle2, Share2, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import {
  AssignHrRequestModal,
  ChangeHrStatusModal,
  ReferHrRequestModal,
  RespondHrReferralModal,
  ResolveHrRequestModal,
} from '@/features/hr/HrActionModals'
import { HrAttachments } from '@/features/hr/HrAttachments'
import { HrStaffConversation } from '@/features/hr/HrComments'
import { hrAssigneeLabel } from '@/features/hr/hrAssigneeLabel'
import { HrRequestFlags, HrStatusBadge } from '@/features/hr/HrStatusBadge'
import { HrTimeline } from '@/features/hr/HrTimeline'
import { useAssignHrRequest, useCloseHrRequestAsVerified, useHrStaffRequest } from '@/features/hr/useHrStaff'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import type { HrReferral } from '@/types/models'
import { formatDateTime } from '@/utils/format'

function departmentName(slug: string): string {
  const names: Record<string, string> = { hr: 'HR', finance: 'Finance', it: 'IT' }
  return names[slug] ?? slug
}

export function HrAdminRequestWorkspacePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const { user, hasPermission } = useAuth()
  const { data: request, isPending, isError, error, refetch, isRefetching } = useHrStaffRequest(id)
  const takeRequest = useAssignHrRequest(id ?? '')
  const closeAsVerified = useCloseHrRequestAsVerified(id ?? '')

  const [assignOpen, setAssignOpen] = useState(false)
  const [statusOpen, setStatusOpen] = useState(false)
  const [resolveOpen, setResolveOpen] = useState(false)
  const [referOpen, setReferOpen] = useState(false)
  const [respondTo, setRespondTo] = useState<HrReferral | null>(null)

  if (isPending) {
    return <LoadingPanel label="Loading HR request workspace..." />
  }

  if (isError || !request) {
    const missing = error instanceof ApiError && (error.status === 404 || error.isForbidden)

    return (
      <div className="space-y-6">
        <PageHeader title="Request workspace" description="This request could not be opened." />
        <Card>
          <ErrorState
            title={missing ? 'Request not found' : 'Unable to load request'}
            description={
              missing
                ? 'This request does not exist or you are not cleared to see it.'
                : error instanceof ApiError
                  ? error.message
                  : 'The request could not be loaded.'
            }
            onRetry={missing ? undefined : () => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
        <Button
          variant="outline"
          leadingIcon={<ArrowLeft className="size-4" />}
          onClick={() => void navigate(paths.hr.admin.requests)}
        >
          Back to requests
        </Button>
      </div>
    )
  }

  const finished = Boolean(request.status?.is_terminal)
  const resolved = request.status?.slug === 'resolved' || Boolean(request.awaiting_verification)
  const assignedToMe = Boolean(user?.id) && request.assignee?.id === user?.id
  const teamAssigned = Boolean(request.assigned_to_team)

  const canAssign = hasPermission('hr.requests.assign')
  const canStatus = (hasPermission('hr.requests.update_status') || assignedToMe) && !finished && !resolved
  const canResolve = (hasPermission('hr.requests.resolve') || assignedToMe) && !finished && !resolved
  const canCloseAsVerified =
    (hasPermission('hr.requests.resolve') || assignedToMe) &&
    !finished &&
    (resolved || Boolean(request.resolution_summary))
  const canRefer = hasPermission('hr.requests.refer') && !finished && !resolved
  const canTake =
    !canAssign &&
    !finished &&
    (!request.assignee || teamAssigned) &&
    !assignedToMe &&
    (hasPermission('hr.requests.update_status') || hasPermission('hr.requests.resolve')) &&
    Boolean(user?.id)

  const referrals = request.referrals ?? []
  const canRespondTo = (referral: HrReferral): boolean => {
    if (referral.status !== 'pending') return false
    if (referral.assignee?.id === user?.id) return true
    if (hasPermission('hr.referrals.manage')) return true
    if (referral.department_slug === 'finance') return hasPermission('hr.referrals.finance')
    if (referral.department_slug === 'it') return hasPermission('hr.referrals.it')
    return false
  }

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.hr.admin.requests)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            &larr; HR Requests
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

      <div className="flex flex-wrap gap-2">
        {canAssign && (
          <Button variant="secondary" leadingIcon={<UserPlus className="size-4" />} onClick={() => setAssignOpen(true)}>
            {request.assignee && !teamAssigned ? 'Reassign request' : 'Assign request'}
          </Button>
        )}
        {canTake && (
          <Button
            variant="secondary"
            leadingIcon={<UserPlus className="size-4" />}
            isLoading={takeRequest.isPending}
            onClick={() => {
              if (!user) return
              void takeRequest
                .mutateAsync({ assignedTo: user.id })
                .then(() => toast.success('Request taken', `${request.reference} is now assigned to you.`))
                .catch((cause: unknown) => {
                  toast.error('Could not take request', cause instanceof ApiError ? cause.message : 'Please try again.')
                })
            }}
          >
            Take request
          </Button>
        )}
        {canStatus && (
          <Button variant="outline" onClick={() => setStatusOpen(true)}>
            Change status
          </Button>
        )}
        {canRefer && (
          <Button variant="outline" leadingIcon={<Share2 className="size-4" />} onClick={() => setReferOpen(true)}>
            Refer to department
          </Button>
        )}
        {canResolve && (
          <Button variant="success" leadingIcon={<CheckCircle2 className="size-4" />} onClick={() => setResolveOpen(true)}>
            Resolve request
          </Button>
        )}
        {canCloseAsVerified && (
          <Button
            variant="outline"
            isLoading={closeAsVerified.isPending}
            onClick={() => {
              void closeAsVerified
                .mutateAsync()
                .then(() => toast.success('Request closed', `${request.reference} was closed as verified.`))
                .catch((cause: unknown) => {
                  toast.error(
                    'Could not close request',
                    cause instanceof ApiError ? cause.message : 'Assign a specific HR member first, then try again.',
                  )
                })
            }}
          >
            Close as verified
          </Button>
        )}
      </div>

      {request.awaiting_verification && (
        <Card className="border-warning-200 bg-warning-50/70">
          <CardContent className="py-4 text-sm text-surface-700">
            Waiting for the employee to confirm this resolution. You can close as verified if they cannot respond.
          </CardContent>
        </Card>
      )}

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card>
            <CardHeader title="Request" description="What the employee reported." />
            <CardContent className="space-y-4">
              <p className="text-sm leading-6 whitespace-pre-wrap text-surface-800">{request.description}</p>
              {request.resolution_summary && (
                <div className="rounded-xl border border-success-200 bg-success-50 px-4 py-3">
                  <p className="text-xs font-semibold tracking-wide text-success-700 uppercase">Resolution</p>
                  <p className="mt-1 text-sm whitespace-pre-wrap text-success-700">{request.resolution_summary}</p>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader
              title="Conversation"
              description="Public replies and internal notes. Internal notes never leave the HR team."
            />
            <CardContent>
              <HrStaffConversation request={request} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Attachments" description="Files supplied by the employee." />
            <CardContent>
              <HrAttachments attachments={request.attachments ?? []} />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Employee" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Name">{request.employee_name ?? request.requester?.full_name}</DescriptionItem>
                <DescriptionItem label="Employee ID" mono>
                  {request.employee_id}
                </DescriptionItem>
                <DescriptionItem label="Department">{request.department_name}</DescriptionItem>
                <DescriptionItem label="Designation">{request.designation}</DescriptionItem>
                <DescriptionItem label="Branch">{request.branch?.name}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Request details" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Category">{request.category?.name}</DescriptionItem>
                <DescriptionItem label="Request type">{request.request_type?.name}</DescriptionItem>
                <DescriptionItem label="Assigned to">{hrAssigneeLabel(request)}</DescriptionItem>
                <DescriptionItem label="Submitted">{formatDateTime(request.created_at)}</DescriptionItem>
                <DescriptionItem label="Due">{formatDateTime(request.due_at)}</DescriptionItem>
                <DescriptionItem label="Assigned at">{formatDateTime(request.assigned_at)}</DescriptionItem>
                <DescriptionItem label="Escalated">
                  {request.escalated_at
                    ? `${formatDateTime(request.escalated_at)}${request.escalated_to?.full_name ? ` → ${request.escalated_to.full_name}` : ''}`
                    : '—'}
                </DescriptionItem>
                <DescriptionItem label="Resolved">{formatDateTime(request.resolved_at)}</DescriptionItem>
                <DescriptionItem label="Closed">{formatDateTime(request.closed_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Referrals" description="Requests sent to other departments." />
            <CardContent>
              {referrals.length === 0 ? (
                <p className="text-sm text-surface-500">This request has not been referred.</p>
              ) : (
                <ul className="space-y-3">
                  {referrals.map((referral) => (
                    <li key={referral.id} className="rounded-lg border border-surface-200 p-3">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-medium text-surface-900">{departmentName(referral.department_slug)}</p>
                        <Badge tone={referral.status === 'pending' ? 'warning' : 'success'} size="sm" withDot>
                          {referral.status === 'pending' ? 'Pending' : 'Responded'}
                        </Badge>
                      </div>
                      {referral.brief && <p className="mt-1.5 text-xs whitespace-pre-wrap text-surface-600">{referral.brief}</p>}
                      {referral.response && (
                        <div className="mt-2 rounded-md bg-surface-50 px-2.5 py-2">
                          <p className="text-[11px] font-semibold tracking-wide text-surface-500 uppercase">Response</p>
                          <p className="mt-0.5 text-xs whitespace-pre-wrap text-surface-700">{referral.response}</p>
                        </div>
                      )}
                      <p className="mt-2 text-[11px] text-surface-400">
                        Sent {formatDateTime(referral.created_at)}
                        {referral.created_by ? ` by ${referral.created_by.full_name}` : ''}
                      </p>
                      {canRespondTo(referral) && !finished && (
                        <Button size="sm" variant="outline" className="mt-2" onClick={() => setRespondTo(referral)}>
                          Record response
                        </Button>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="History" description="Status changes with remarks." />
            <CardContent>
              <HrTimeline events={request.timeline ?? []} />
            </CardContent>
          </Card>

          {(request.assignments ?? []).length > 0 && (
            <Card>
              <CardHeader title="Assignment history" />
              <CardContent>
                <ul className="space-y-3">
                  {request.assignments?.map((assignment) => (
                    <li key={assignment.id} className="text-sm">
                      <p className="font-medium text-surface-900">
                        {assignment.assignee?.full_name ?? 'Unknown'}
                        {!assignment.released_at && <span className="ml-1.5 text-xs text-success-700">(current)</span>}
                      </p>
                      <p className="text-xs text-surface-500">
                        {formatDateTime(assignment.assigned_at)}
                        {assignment.assigned_by ? ` by ${assignment.assigned_by.full_name}` : ''}
                      </p>
                      {assignment.note && <p className="mt-0.5 text-xs text-surface-600">{assignment.note}</p>}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      <AssignHrRequestModal request={request} isOpen={assignOpen} onClose={() => setAssignOpen(false)} />
      <ChangeHrStatusModal request={request} isOpen={statusOpen} onClose={() => setStatusOpen(false)} />
      <ResolveHrRequestModal request={request} isOpen={resolveOpen} onClose={() => setResolveOpen(false)} />
      <ReferHrRequestModal request={request} isOpen={referOpen} onClose={() => setReferOpen(false)} />
      <RespondHrReferralModal request={request} referral={respondTo} onClose={() => setRespondTo(null)} />
    </div>
  )
}
