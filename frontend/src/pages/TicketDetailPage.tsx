import { ArrowLeft, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import { TicketAttachments } from '@/features/tickets/TicketAttachments'
import { TicketComments } from '@/features/tickets/TicketComments'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { TicketTimeline } from '@/features/tickets/TicketTimeline'
import { VerifyResolutionModal } from '@/features/tickets/VerifyResolutionModal'
import { useTicket } from '@/features/tickets/useTickets'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

export function TicketDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: ticket, isPending, isError, error, refetch, isRefetching } = useTicket(id)
  const [verifyOpen, setVerifyOpen] = useState(false)

  if (isPending) {
    return <LoadingPanel label="Loading ticket…" />
  }

  if (isError || !ticket) {
    const forbidden = error instanceof ApiError && error.isForbidden
    const missing = error instanceof ApiError && error.status === 404

    return (
      <div className="space-y-6">
        <PageHeader title="Ticket" description="This request could not be opened." />
        <Card>
          <ErrorState
            title={forbidden ? 'You cannot view this ticket' : missing ? 'Ticket not found' : 'Unable to load ticket'}
            description={
              forbidden
                ? 'Employees can only open tickets they have raised themselves.'
                : error instanceof ApiError
                  ? error.message
                  : 'The ticket could not be loaded. Please try again.'
            }
            onRetry={forbidden || missing ? undefined : () => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
        <Button variant="outline" leadingIcon={<ArrowLeft className="size-4" />} onClick={() => void navigate(paths.tickets.index)}>
          Back to My Tickets
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
            onClick={() => void navigate(paths.tickets.index)}
            className="text-xs font-medium text-primary-600 hover:text-primary-700"
          >
            ← My Tickets
          </button>
        }
        title={ticket.subject}
        description={ticket.reference}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <TicketPriorityBadge priority={ticket.priority} />
            <TicketStatusBadge status={ticket.status} />
          </div>
        }
      />

      {(ticket.awaiting_verification || ticket.resolution_summary || ticket.verification?.status === 'verified') && (
        <Card
          className={
            ticket.awaiting_verification
              ? 'border-warning-200 bg-warning-50/70'
              : ticket.verification?.status === 'verified' || ticket.status?.is_closed
                ? 'border-success-200 bg-success-50/70'
                : undefined
          }
        >
          <CardContent className="space-y-4 py-6">
            <div className="flex gap-3">
              <span
                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${
                  ticket.awaiting_verification
                    ? 'bg-warning-100 text-warning-700'
                    : 'bg-success-100 text-success-700'
                }`}
              >
                <ShieldCheck className="size-5" />
              </span>
              <div className="min-w-0 flex-1">
                <h2 className="text-sm font-semibold text-surface-900">
                  {ticket.awaiting_verification
                    ? 'IT has marked this issue as resolved. Please confirm whether it is fixed.'
                    : ticket.verification?.status === 'verified' || ticket.status?.slug === 'closed'
                      ? 'You confirmed this fix. The ticket is closed.'
                      : 'This issue was resolved.'}
                </h2>
                <dl className="mt-3 grid gap-3 sm:grid-cols-3">
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Resolution Summary</dt>
                    <dd className="mt-1 text-sm text-surface-800">{ticket.resolution_summary || '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">Resolved By</dt>
                    <dd className="mt-1 text-sm text-surface-800">{ticket.resolved_by?.full_name || '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">
                      {ticket.verification?.status === 'verified' ? 'Confirmed' : 'Resolved Date'}
                    </dt>
                    <dd className="mt-1 text-sm text-surface-800">
                      {formatDateTime(
                        ticket.verification?.status === 'verified'
                          ? (ticket.verification.completed_at ?? ticket.resolved_at)
                          : ticket.resolved_at,
                      )}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>

            {ticket.awaiting_verification && (
              <div className="flex flex-col gap-3 border-t border-warning-200/80 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-surface-700">
                  Confirm if the issue is fixed, or tell IT if you still need help.
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
            <CardHeader title="Request" description="What you reported to IT support." />
            <CardContent className="space-y-4">
              <p className="text-sm leading-6 whitespace-pre-wrap text-surface-700">
                {ticket.description || 'No description was provided.'}
              </p>
              {ticket.additional_details && (
                <div>
                  <p className="text-xs font-medium tracking-wide text-surface-500 uppercase">Additional details</p>
                  <p className="mt-1 text-sm whitespace-pre-wrap text-surface-700">{ticket.additional_details}</p>
                </div>
              )}
              {(ticket.issue_type_label || ticket.item_type_label) && (
                <div className="grid gap-3 sm:grid-cols-2">
                  {ticket.issue_type_label && (
                    <div>
                      <p className="text-xs font-medium tracking-wide text-surface-500 uppercase">Issue type</p>
                      <p className="mt-1 text-sm text-surface-700">{ticket.issue_type_label}</p>
                    </div>
                  )}
                  {ticket.item_type_label && (
                    <div>
                      <p className="text-xs font-medium tracking-wide text-surface-500 uppercase">Item type</p>
                      <p className="mt-1 text-sm text-surface-700">{ticket.item_type_label}</p>
                    </div>
                  )}
                  {ticket.item_other && (
                    <div className="sm:col-span-2">
                      <p className="text-xs font-medium tracking-wide text-surface-500 uppercase">Other details</p>
                      <p className="mt-1 text-sm whitespace-pre-wrap text-surface-700">{ticket.item_other}</p>
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Conversation" description="Messages between you and IT support. Internal IT notes are not shown." />
            <CardContent>
              <TicketComments ticket={ticket} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Attachments" description="Files uploaded with this request. You can add more while the ticket is open." />
            <CardContent>
              <TicketAttachments ticket={ticket} attachments={ticket.attachments ?? []} allowUpload />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Ticket information" />
            <CardContent className="space-y-4 text-sm">
              <DescriptionList>
                <DescriptionItem label="Ticket Number" mono>
                  {ticket.reference}
                </DescriptionItem>
                <DescriptionItem label="Created date">{formatDateTime(ticket.created_at)}</DescriptionItem>
                <DescriptionItem label="Created by">{ticket.requester?.full_name ?? '—'}</DescriptionItem>
                <DescriptionItem label="Branch">{ticket.branch?.name ?? '—'}</DescriptionItem>
                <DescriptionItem label="Category">{ticket.category?.name ?? '—'}</DescriptionItem>
                {ticket.issue_type_label && (
                  <DescriptionItem label="Issue type">{ticket.issue_type_label}</DescriptionItem>
                )}
                {ticket.item_type_label && (
                  <DescriptionItem label="Item type">{ticket.item_type_label}</DescriptionItem>
                )}
                <DescriptionItem label="Priority">
                  <TicketPriorityBadge priority={ticket.priority} />
                </DescriptionItem>
                <DescriptionItem label="Status">
                  <TicketStatusBadge status={ticket.status} />
                </DescriptionItem>
                <DescriptionItem label="Assigned IT staff">{assigneeLabel(ticket)}</DescriptionItem>
                <DescriptionItem label="Phone">{ticket.contact_phone || '—'}</DescriptionItem>
                {(ticket.resolved_by || ticket.resolved_at) && (
                  <DescriptionItem label="Resolved by">
                    {ticket.resolved_by?.full_name ?? '—'}
                  </DescriptionItem>
                )}
                <DescriptionItem label="Last updated">{formatDateTime(ticket.updated_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Timeline" description="How this request has progressed." />
            <CardContent>
              <TicketTimeline events={ticket.timeline ?? []} />
            </CardContent>
          </Card>
        </div>
      </div>

      <VerifyResolutionModal ticket={ticket} isOpen={verifyOpen} onClose={() => setVerifyOpen(false)} />
    </div>
  )
}

