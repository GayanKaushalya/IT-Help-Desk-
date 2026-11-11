import {
  AlertTriangle,
  CheckCircle2,
  CircleDot,
  Clock3,
  Inbox,
  ShieldCheck,
  Ticket,
  UserCheck,
  UserRound,
} from 'lucide-react'
import { lazy, Suspense } from 'react'
import { useNavigate } from 'react-router-dom'

import { StatCard } from '@/components/common/StatCard'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton, SkeletonStatCard } from '@/components/ui/Skeleton'
import { AdminTicketTable } from '@/features/admin/AdminTicketTable'
import { useStaffDashboard } from '@/features/admin/useStaffDashboard'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'

const AdminCharts = lazy(() =>
  import('@/features/admin/AdminCharts').then((module) => ({ default: module.AdminCharts })),
)

export function AdminDashboardPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const { data, isPending, isError, error, refetch } = useStaffDashboard()

  const stats = data?.statistics
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'The service desk could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="IT Service Desk"
        description={`Organisation-wide operations for ${user?.role?.name ?? 'IT staff'}.`}
        actions={
          <Button variant="outline" onClick={() => void navigate(paths.admin.tickets)}>
            Open ticket queue
          </Button>
        }
      />

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {isPending ? (
          Array.from({ length: 9 }).map((_, index) => <SkeletonStatCard key={index} />)
        ) : (
          <>
            <StatCard label="Total Tickets" value={stats?.total ?? 0} icon={Ticket} tone="neutral" />
            <StatCard label="Open" value={stats?.open ?? 0} icon={CircleDot} tone="info" />
            <StatCard label="Assigned" value={stats?.assigned ?? 0} icon={UserCheck} tone="primary" />
            <StatCard label="In Progress" value={stats?.in_progress ?? 0} icon={Clock3} tone="primary" />
            <StatCard label="Waiting for User" value={stats?.awaiting_user ?? 0} icon={UserRound} tone="warning" />
            <StatCard label="Resolved" value={stats?.resolved ?? 0} icon={CheckCircle2} tone="success" />
            <StatCard
              label="Awaiting Verification"
              value={stats?.awaiting_verification ?? 0}
              icon={ShieldCheck}
              tone="warning"
            />
            <StatCard label="Closed" value={stats?.closed ?? 0} icon={Inbox} tone="neutral" />
            <StatCard label="Critical Tickets" value={stats?.critical ?? 0} icon={AlertTriangle} tone="danger" />
          </>
        )}
      </section>

      {data && (
        <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
          <AdminCharts
            byStatus={data.charts.by_status}
            byPriority={data.charts.by_priority}
            byCategory={data.charts.by_category}
            byBranch={data.charts.by_branch}
            overTime={data.charts.over_time}
            workload={data.charts.workload}
          />
        </Suspense>
      )}

      <Card>
        <CardHeader
          title="Recent tickets"
          description="The newest requests across the organisation."
          action={
            <Button variant="ghost" size="sm" onClick={() => void navigate(paths.admin.tickets)}>
              View all
            </Button>
          }
        />
        <AdminTicketTable
          rows={data?.recent_tickets ?? []}
          isLoading={isPending}
          errorMessage={errorMessage}
          onRetry={() => void refetch()}
          emptyTitle="No tickets yet"
          emptyDescription="New employee requests will appear here."
        />
      </Card>
    </div>
  )
}
