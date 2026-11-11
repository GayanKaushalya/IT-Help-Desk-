import {
  CheckCircle2,
  CircleDot,
  Clock3,
  LifeBuoy,
  Mail,
  Plus,
  Search,
  ShieldCheck,
  Ticket,
} from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { SkeletonStatCard } from '@/components/ui/Skeleton'
import { ContactItModal } from '@/features/dashboard/ContactItModal'
import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import { TrackTicketModal } from '@/features/dashboard/TrackTicketModal'
import { useDashboardOverview } from '@/features/dashboard/useDashboard'
import { TicketMobileCard } from '@/features/tickets/TicketMobileCard'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import type { Ticket as TicketRecord } from '@/types/models'
import { formatDate, formatDateTime, greetingFor } from '@/utils/format'

const TICKET_COLUMNS: DataTableColumn<TicketRecord>[] = [
  {
    id: 'reference',
    header: 'Ticket ID',
    cell: (ticket) => (
      <span className="font-mono text-xs font-medium text-primary-700">{ticket.reference}</span>
    ),
  },
  {
    id: 'subject',
    header: 'Title',
    cell: (ticket) => <span className="font-medium text-surface-900">{ticket.subject}</span>,
    className: 'min-w-[14rem]',
  },
  {
    id: 'category',
    header: 'Category',
    cell: (ticket) => ticket.category?.name ?? '—',
    className: 'hidden lg:table-cell',
  },
  {
    id: 'priority',
    header: 'Priority',
    cell: (ticket) => <TicketPriorityBadge priority={ticket.priority} />,
    className: 'hidden md:table-cell',
  },
  {
    id: 'status',
    header: 'Status',
    cell: (ticket) => <TicketStatusBadge status={ticket.status} />,
  },
  {
    id: 'created_at',
    header: 'Created',
    cell: (ticket) => formatDate(ticket.created_at),
    className: 'hidden xl:table-cell',
  },
  {
    id: 'assignee',
    header: 'Assigned IT Staff',
    cell: (ticket) => assigneeLabel(ticket),
    className: 'hidden lg:table-cell',
  },
  {
    id: 'updated_at',
    header: 'Last Updated',
    cell: (ticket) => formatDateTime(ticket.updated_at),
    className: 'hidden sm:table-cell',
  },
]

export function DashboardPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const { data, isPending, isError, error, refetch, isRefetching } = useDashboardOverview()
  const [isTracking, setTracking] = useState(false)
  const [isContactOpen, setContactOpen] = useState(false)

  const firstName = user?.full_name.split(' ')[0] ?? 'there'
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'Your tickets could not be loaded.'
    : null

  const stats = data?.statistics

  return (
    <div className="space-y-6">
      <section className="overflow-hidden rounded-card border border-primary-100 bg-panel shadow-card">
        <div className="hero-panel-glow relative px-6 py-7 sm:px-8">
          <p className="text-xs font-semibold tracking-[0.16em] text-primary-600 uppercase">
            IT Helpdesk
          </p>
          <div className="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="text-2xl font-semibold tracking-tight text-surface-900">
                {greetingFor()}, {firstName}
              </h1>
              <p className="mt-1 text-sm text-surface-500">How can IT help you today?</p>
            </div>

            <Button
              size="lg"
              leadingIcon={<Plus className="size-4" />}
              onClick={() => void navigate(paths.tickets.create)}
            >
              Create Ticket
            </Button>
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {isPending ? (
          Array.from({ length: 5 }).map((_, index) => <SkeletonStatCard key={index} />)
        ) : (
          <>
            <StatCard label="Total Tickets" value={stats?.total ?? 0} icon={Ticket} tone="neutral" />
            <StatCard label="Open Tickets" value={stats?.open ?? 0} icon={CircleDot} tone="info" />
            <StatCard
              label="In Progress"
              value={stats?.in_progress ?? 0}
              icon={Clock3}
              tone="primary"
            />
            <StatCard
              label="Awaiting Verification"
              value={stats?.awaiting_verification ?? 0}
              hint="Resolved tickets waiting for your confirmation"
              icon={ShieldCheck}
              tone="warning"
            />
            <StatCard label="Closed" value={stats?.closed ?? 0} icon={CheckCircle2} tone="success" />
          </>
        )}
      </section>

      <div className="grid gap-6 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <Card>
            <CardHeader
              title="Recent tickets"
              description="Only requests you have raised with IT support."
              action={
                <Button variant="ghost" size="sm" onClick={() => void navigate(paths.tickets.index)}>
                  View all
                </Button>
              }
            />
            <DataTable
              columns={TICKET_COLUMNS}
              rows={data?.recent_tickets ?? []}
              rowKey={(ticket) => ticket.id}
              isLoading={isPending}
              errorMessage={errorMessage}
              onRetry={() => void refetch()}
              onRowClick={(ticket) => void navigate(paths.tickets.detail(ticket.id))}
              emptyTitle="You have not raised any tickets yet"
              emptyDescription="Create a ticket and IT support will pick it up."
              emptyAction={
                <Button leadingIcon={<Plus className="size-4" />} onClick={() => void navigate(paths.tickets.create)}>
                  Create Ticket
                </Button>
              }
              mobileCard={(ticket) => (
                <TicketMobileCard ticket={ticket} onOpen={() => void navigate(paths.tickets.detail(ticket.id))} />
              )}
            />
          </Card>
        </div>

        <div className="space-y-4 xl:col-span-2">
          <Card>
            <CardHeader title="Quick actions" description="Common things employees need from IT." />
            <div className="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-1">
              <QuickAction
                icon={LifeBuoy}
                title="Create IT Ticket"
                copy="Tell IT support what you need help with."
                onClick={() => void navigate(paths.tickets.create)}
              />
              <QuickAction
                icon={Ticket}
                title="View My Tickets"
                copy="See every request you have raised."
                onClick={() => void navigate(paths.tickets.index)}
              />
              <QuickAction
                icon={Search}
                title="Track Ticket"
                copy="Open a ticket by ID or reference."
                onClick={() => setTracking(true)}
              />
              <QuickAction
                icon={Mail}
                title="Contact IT"
                copy="Reach the service desk during business hours."
                onClick={() => setContactOpen(true)}
              />
            </div>
          </Card>

          {isRefetching && !isPending && (
            <p className="px-1 text-xs text-surface-400">Refreshing your tickets…</p>
          )}
        </div>
      </div>

      <TrackTicketModal isOpen={isTracking} onClose={() => setTracking(false)} />
      <ContactItModal isOpen={isContactOpen} onClose={() => setContactOpen(false)} />
    </div>
  )
}

function QuickAction({
  icon: Icon,
  title,
  copy,
  onClick,
}: {
  icon: typeof Ticket
  title: string
  copy: string
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex gap-3 rounded-xl border border-surface-200 bg-surface-50/70 p-4 text-left transition-colors hover:border-primary-200 hover:bg-primary-50/40"
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-panel text-primary-600 shadow-sm">
        <Icon className="size-4" />
      </span>
      <span>
        <span className="block text-sm font-medium text-surface-900">{title}</span>
        <span className="mt-0.5 block text-xs text-surface-500">{copy}</span>
      </span>
    </button>
  )
}
