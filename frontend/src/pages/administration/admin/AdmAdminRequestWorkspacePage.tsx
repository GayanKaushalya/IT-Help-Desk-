import { ArrowLeft, CheckCircle2, ShieldCheck, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import {
  AssignAdmRequestModal,
  ChangeAdmStatusModal,
  ResolveAdmRequestModal,
} from '@/features/administration/AdmActionModals'
import { AdmRequestDetailsCard, CctvApprovalPanel } from '@/features/administration/CctvApprovalPanel'
import { AdmAttachments } from '@/features/administration/AdmAttachments'
import { AdmStaffConversation } from '@/features/administration/AdmComments'
import { admAssigneeLabel } from '@/features/administration/admAssigneeLabel'
import { AdmRequestFlags, AdmStatusBadge } from '@/features/administration/AdmStatusBadge'
import { AdmTimeline } from '@/features/administration/AdmTimeline'
import {
  useAdmStaffRequest,
  useAssignAdmRequest,
  useCloseAdmRequestAsVerified,
} from '@/features/administration/useAdmStaff'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDate, formatDateTime } from '@/utils/format'

function errorText(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Please try again.'
}

export function AdmAdminRequestWorkspacePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const { user, hasPermission } = useAuth()
  const { data: request, isPending, isError, error, refetch, isRefetching } = useAdmStaffRequest(id)
  const takeRequest = useAssignAdmRequest(id ?? '')
  const closeRequest = useCloseAdmRequestAsVerified(id ?? '')

  const [assignOpen, setAssignOpen] = useState(false)
  const [statusOpen, setStatusOpen] = useState(false)
  const [resolveOpen, setResolveOpen] = useState(false)

  if (isPending) {
    return <LoadingPanel label="Loading Administration request workspace..." />
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
          onClick={() => void navigate(paths.administration.admin.requests)}
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

  const canAssign = hasPermission('adm.requests.assign')
  const canStatus = (hasPermission('adm.requests.update_status') || assignedToMe) && !finished && !resolved
  const isCctv = request.category?.slug === 'cctv-surveillance'
  // The server enforces this too; the buttons just explain why they are unavailable.
  const cctvBlocked = isCctv && request.cctv_approval?.status !== 'approved'
  const canResolve = (hasPermission('adm.requests.resolve') || assignedToMe) && !finished && !resolved
  const canCloseAsVerified =
    Boolean(request.awaiting_verification) && !finished && (hasPermission('adm.requests.resolve') || assignedToMe)
  const canTake =
    !canAssign &&
    !finished &&
    (!request.assignee || teamAssigned) &&
    !assignedToMe &&
    (hasPermission('adm.requests.update_status') || hasPermission('adm.requests.resolve')) &&
    Boolean(user?.id)

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.administration.admin.requests)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            &larr; Administration Requests
          </button>
        }
        title={request.subject || request.request_type?.name || 'Administration request'}
        description={request.reference}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <AdmRequestFlags request={request} />
            <AdmStatusBadge status={request.status} awaitingVerification={request.awaiting_verification} />
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
                .catch((cause: unknown) => toast.error('Could not take request', errorText(cause)))
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
        {canResolve && (
          <Button
            variant="success"
            leadingIcon={<CheckCircle2 className="size-4" />}
            disabled={cctvBlocked}
            title={cctvBlocked ? 'CCTV requests must be approved before they can be resolved.' : undefined}
            onClick={() => setResolveOpen(true)}
          >
            Resolve request
          </Button>
        )}
        {canCloseAsVerified && (
          <Button
            variant="outline"
            leadingIcon={<ShieldCheck className="size-4" />}
            isLoading={closeRequest.isPending}
            disabled={cctvBlocked}
            onClick={() => {
              void closeRequest
                .mutateAsync()
                .then(() => toast.success('Request closed', `${request.reference} was closed as verified.`))
                .catch((cause: unknown) => toast.error('Could not close request', errorText(cause)))
            }}
          >
            Close as verified
          </Button>
        )}
      </div>

      {request.awaiting_verification && (
        <Card className="border-warning-200 bg-warning-50/70">
          <CardContent className="py-4 text-sm text-surface-700">
            Waiting for the employee to confirm this resolution in their Administration Help Desk.
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
              description="Public replies and internal notes. Internal notes never leave the Administration team."
            />
            <CardContent>
              <AdmStaffConversation request={request} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Attachments" description="Files supplied with this request." />
            <CardContent>
              <AdmAttachments attachments={request.attachments ?? []} />
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
                <DescriptionItem label="Phone">{request.contact_phone || '—'}</DescriptionItem>
                <DescriptionItem label="Branch">{request.branch?.name}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          {isCctv && <CctvApprovalPanel requestId={request.id} />}
          {isCctv && <AdmRequestDetailsCard details={request.details} />}

          <Card>
            <CardHeader title="Request details" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Category">{request.category?.name}</DescriptionItem>
                <DescriptionItem label="Request type">{request.request_type?.name}</DescriptionItem>
                <DescriptionItem label="Company">{request.company_name}</DescriptionItem>
                <DescriptionItem label="Required date">{formatDate(request.required_date)}</DescriptionItem>
                <DescriptionItem label="Assigned to">{admAssigneeLabel(request)}</DescriptionItem>
                <DescriptionItem label="Submitted">{formatDateTime(request.created_at)}</DescriptionItem>
                <DescriptionItem label="Due">{formatDateTime(request.due_at)}</DescriptionItem>
                <DescriptionItem label="Assigned at">{formatDateTime(request.assigned_at)}</DescriptionItem>
                <DescriptionItem label="Resolved">{formatDateTime(request.resolved_at)}</DescriptionItem>
                <DescriptionItem label="Closed">{formatDateTime(request.closed_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="History" description="Status changes with remarks." />
            <CardContent>
              <AdmTimeline events={request.timeline ?? []} />
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

      <AssignAdmRequestModal request={request} isOpen={assignOpen} onClose={() => setAssignOpen(false)} />
      <ChangeAdmStatusModal request={request} isOpen={statusOpen} onClose={() => setStatusOpen(false)} />
      <ResolveAdmRequestModal request={request} isOpen={resolveOpen} onClose={() => setResolveOpen(false)} />
    </div>
  )
}
