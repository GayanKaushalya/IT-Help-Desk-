import { ArrowLeft, ArrowRight, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { AssignTicketDrawer } from '@/features/admin/AssignTicketDrawer'
import { AdminTicketStatusCell } from '@/features/admin/AdminTicketTable'
import { StaffAttachments } from '@/features/admin/StaffAttachments'
import { StaffConversation } from '@/features/admin/StaffConversation'
import { ChangePriorityModal, ChangeStatusModal, ResolveTicketModal } from '@/features/admin/TicketActionModals'
import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import {
  useAssignStaffTicket,
  useClearStaffTicketResolution,
  useStaffTicket,
} from '@/features/admin/useStaffDashboard'
import { TicketPriorityBadge } from '@/features/tickets/TicketStatusBadge'
import { TicketTimeline } from '@/features/tickets/TicketTimeline'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

export function AdminTicketWorkspacePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const { user, hasPermission } = useAuth()
  const { data: ticket, isPending, isError, error, refetch, isRefetching } = useStaffTicket(id)
  const takeTicket = useAssignStaffTicket(id ?? '')
  const clearResolution = useClearStaffTicketResolution(id ?? '')
  const [assignOpen, setAssignOpen] = useState(false)
  const [statusOpen, setStatusOpen] = useState(false)
  const [priorityOpen, setPriorityOpen] = useState(false)
  const [resolveOpen, setResolveOpen] = useState(false)
  const [clearResolutionOpen, setClearResolutionOpen] = useState(false)

  if (isPending) {
    return <LoadingPanel label="Loading ticket workspace…" />
  }

  if (isError || !ticket) {
    const forbidden = error instanceof ApiError && error.isForbidden
    const missing = error instanceof ApiError && error.status === 404

    return (
      <div className="space-y-6">
        <PageHeader title="Ticket workspace" description="This ticket could not be opened." />
        <Card>
          <ErrorState
            title={forbidden ? 'You cannot view this ticket' : missing ? 'Ticket not found' : 'Unable to load ticket'}
            description={error instanceof ApiError ? error.message : 'The ticket could not be loaded.'}
            onRetry={forbidden || missing ? undefined : () => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
        <Button variant="outline" leadingIcon={<ArrowLeft className="size-4" />} onClick={() => void navigate(paths.admin.tickets)}>
          Back to tickets
        </Button>
      </div>
    )
  }

  const canAssign = hasPermission('tickets.assign')
  const canPriority = hasPermission('tickets.assign') || hasPermission('tickets.update_status')
  const canStatus = hasPermission('tickets.update_status')
  const closed = Boolean(ticket.status?.is_closed)
  const resolved = Boolean(ticket.status?.is_resolved || ticket.status?.slug === 'resolved')
  const canResolve = hasPermission('tickets.resolve') && !closed && !resolved
  const canClearResolution =
    hasPermission('tickets.resolve') &&
    !closed &&
    (resolved || Boolean(ticket.resolution_summary) || Boolean(ticket.awaiting_verification))
  const canTake = !canAssign && !closed && !ticket.assignee && (canStatus || canResolve) && Boolean(user?.id)

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={
          <button
            type="button"
            onClick={() => void navigate(paths.admin.tickets)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            ← Tickets
          </button>
        }
        title={ticket.subject}
        description={ticket.reference}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <TicketPriorityBadge priority={ticket.priority} />
            <AdminTicketStatusCell ticket={ticket} />
          </div>
        }
      />

      <div className="flex flex-wrap gap-2">
        {canAssign && (
          <Button variant="secondary" leadingIcon={<UserPlus className="size-4" />} onClick={() => setAssignOpen(true)}>
            Assign ticket
          </Button>
        )}
        {canTake && (
          <Button
            variant="secondary"
            leadingIcon={<UserPlus className="size-4" />}
            isLoading={takeTicket.isPending}
            onClick={() => {
              if (!user) return
              void takeTicket
                .mutateAsync({ assignedTo: user.id })
                .then(() => toast.success('Ticket taken', `${ticket.reference} is now assigned to you.`))
                .catch((error: unknown) => {
                  toast.error('Could not take ticket', error instanceof ApiError ? error.message : 'Please try again.')
                })
            }}
          >
            Take ticket
          </Button>
        )}
        {canStatus && !closed && (
          <Button variant="outline" onClick={() => setStatusOpen(true)}>
            Change status
          </Button>
        )}
        {canPriority && !closed && (
          <Button variant="outline" onClick={() => setPriorityOpen(true)}>
            Change priority
          </Button>
        )}
        {canResolve && (
          <Button variant="success" onClick={() => setResolveOpen(true)}>
            Resolve ticket
          </Button>
        )}
        {canClearResolution && (
          <Button variant="outline" onClick={() => setClearResolutionOpen(true)}>
            Clear resolution
          </Button>
        )}
      </div>

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card>
            <CardHeader title="Request" description="What the employee reported." />
            <CardContent className="space-y-4">
              <p className="text-sm leading-6 whitespace-pre-wrap text-surface-800">{ticket.description}</p>
              {ticket.additional_details && (
                <div>
                  <p className="text-xs font-semibold tracking-wide text-surface-500 uppercase">Additional details</p>
                  <p className="mt-1 text-sm whitespace-pre-wrap text-surface-700">{ticket.additional_details}</p>
                </div>
              )}
              {(ticket.issue_type_label || ticket.item_type_label) && (
                <div className="grid gap-3 sm:grid-cols-2">
                  {ticket.issue_type_label && (
                    <div>
                      <p className="text-xs font-semibold tracking-wide text-surface-500 uppercase">Issue type</p>
                      <p className="mt-1 text-sm text-surface-700">{ticket.issue_type_label}</p>
                    </div>
                  )}
                  {ticket.item_type_label && (
                    <div>
                      <p className="text-xs font-semibold tracking-wide text-surface-500 uppercase">Item type</p>
                      <p className="mt-1 text-sm text-surface-700">{ticket.item_type_label}</p>
                    </div>
                  )}
                  {ticket.item_other && (
                    <div className="sm:col-span-2">
                      <p className="text-xs font-semibold tracking-wide text-surface-500 uppercase">Other details</p>
                      <p className="mt-1 text-sm whitespace-pre-wrap text-surface-700">{ticket.item_other}</p>
                    </div>
                  )}
                </div>
              )}
              {ticket.resolution_summary && (
                <div className="rounded-xl border border-success-200 bg-success-50 px-4 py-3">
                  <p className="text-xs font-semibold tracking-wide text-success-700 uppercase">Resolution</p>
                  <p className="mt-1 text-sm text-success-700">{ticket.resolution_summary}</p>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Conversation" description="Public comments and internal notes. Internal notes never leave the IT desk." />
            <CardContent>
              <StaffConversation ticket={ticket} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Attachments" description="Evidence and working files." />
            <CardContent>
              <StaffAttachments ticket={ticket} />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Ticket details" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Employee">{ticket.requester?.full_name}</DescriptionItem>
                <DescriptionItem label="Branch">{ticket.branch?.name}</DescriptionItem>
                <DescriptionItem label="Category">{ticket.category?.name}</DescriptionItem>
                {ticket.issue_type_label && (
                  <DescriptionItem label="Issue type">{ticket.issue_type_label}</DescriptionItem>
                )}
                {ticket.item_type_label && (
                  <DescriptionItem label="Item type">{ticket.item_type_label}</DescriptionItem>
                )}
                <DescriptionItem label="Assigned to">{assigneeLabel(ticket)}</DescriptionItem>
                <DescriptionItem label="Phone">{ticket.contact_phone || '—'}</DescriptionItem>
                <DescriptionItem label="Location">{ticket.contact_location}</DescriptionItem>
                <DescriptionItem label="Preferred contact">{ticket.preferred_contact_method}</DescriptionItem>
                <DescriptionItem label="Created">{formatDateTime(ticket.created_at)}</DescriptionItem>
                <DescriptionItem label="Updated">{formatDateTime(ticket.updated_at)}</DescriptionItem>
                <DescriptionItem label="Resolved by">{ticket.resolved_by?.full_name}</DescriptionItem>
                <DescriptionItem label="Resolved">{formatDateTime(ticket.resolved_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader
              title="Verification"
              description="After you resolve a ticket, the employee confirms or rejects the fix in the app."
            />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Verification Status">
                  {ticket.awaiting_verification
                    ? 'Awaiting employee confirmation'
                    : ticket.verification?.status === 'verified'
                      ? 'Confirmed by employee'
                      : ticket.verification?.status === 'cancelled'
                        ? 'Rejected by employee'
                        : ticket.verification?.status
                          ? ticket.verification.status.replace(/_/g, ' ')
                          : 'Not requested'}
                </DescriptionItem>
                <DescriptionItem label="Requested At">
                  {formatDateTime(ticket.verification?.requested_at)}
                </DescriptionItem>
                <DescriptionItem label="Completed At">
                  {formatDateTime(ticket.verification?.completed_at)}
                </DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="History" description="Assignment, status and conversation events." />
            <CardContent>
              <TicketTimeline events={ticket.timeline ?? []} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Allowed next steps" />
            <CardContent>
              {(ticket.allowed_transitions ?? []).length === 0 ? (
                <p className="text-sm text-surface-500">No further workflow transitions.</p>
              ) : (
                <ul className="space-y-2">
                  {ticket.allowed_transitions?.map((item) => (
                    <li key={item.id} className="flex items-center gap-2 text-sm text-surface-700">
                      <ArrowRight className="size-3.5 text-surface-400" />
                      {item.name}
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <AssignTicketDrawer ticket={ticket} isOpen={assignOpen} onClose={() => setAssignOpen(false)} />
      <ChangeStatusModal ticket={ticket} isOpen={statusOpen} onClose={() => setStatusOpen(false)} />
      <ChangePriorityModal ticket={ticket} isOpen={priorityOpen} onClose={() => setPriorityOpen(false)} />
      <ResolveTicketModal ticket={ticket} isOpen={resolveOpen} onClose={() => setResolveOpen(false)} />
      <ConfirmDialog
        isOpen={clearResolutionOpen}
        onClose={() => setClearResolutionOpen(false)}
        title="Clear this resolution?"
        description="The resolution text will be removed, employee confirmation will be cancelled, and the ticket will return to In Progress."
        confirmLabel="Clear resolution"
        tone="danger"
        isConfirming={clearResolution.isPending}
        onConfirm={() => {
          void clearResolution
            .mutateAsync()
            .then(() => {
              setClearResolutionOpen(false)
              toast.success('Resolution cleared', `${ticket.reference} is In Progress again.`)
            })
            .catch((error: unknown) => {
              toast.error(
                'Could not clear resolution',
                error instanceof ApiError ? error.message : 'Please try again.',
              )
            })
        }}
      />
    </div>
  )
}

