import { AlarmClock, CheckCircle2, ClipboardList, Hourglass, Inbox } from 'lucide-react'
import { lazy, Suspense } from 'react'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton, SkeletonStatCard } from '@/components/ui/Skeleton'
import { AdmRequestTable } from '@/features/administration/AdmRequestTable'
import { useAdmStaffDashboard } from '@/features/administration/useAdmStaff'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'

const AdmAdminCharts = lazy(() =>
  import('@/features/administration/AdmAdminCharts').then((module) => ({ default: module.AdmAdminCharts })),
)

export function AdmAdminDashboardPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const { data, isPending, isError, error, refetch } = useAdmStaffDashboard()

  const stats = data?.statistics
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'The Administration service desk could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Administration Service Desk"
        description={`Administration operations for ${user?.role?.name ?? 'Administration staff'}.`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => void navigate(paths.administration.admin.requests)}>
              Open request queue
            </Button>
            <Button variant="outline" onClick={() => void navigate(paths.administration.admin.assigned)}>
              My assigned
            </Button>
          </div>
        }
      />

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        {isPending ? (
          Array.from({ length: 5 }).map((_, index) => <SkeletonStatCard key={index} />)
        ) : (
          <>
            <StatCard label="Total Requests" value={stats?.total ?? 0} icon={Inbox} tone="neutral" />
            <StatCard label="Pending" value={stats?.pending ?? 0} icon={Hourglass} tone="info" />
            <StatCard label="In Progress" value={stats?.in_progress ?? 0} icon={ClipboardList} tone="primary" />
            <StatCard label="Completed" value={stats?.completed ?? 0} icon={CheckCircle2} tone="success" />
            <StatCard label="Overdue" value={stats?.overdue ?? 0} icon={AlarmClock} tone="warning" />
          </>
        )}
      </section>

      {data && (
        <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
          <AdmAdminCharts byStatus={data.by_status} byCategory={data.by_category} />
        </Suspense>
      )}

      <Card>
        <CardHeader
          title="Recent requests"
          description="The newest Administration requests you can see."
          action={
            <Button variant="ghost" size="sm" onClick={() => void navigate(paths.administration.admin.requests)}>
              View all
            </Button>
          }
        />
        <AdmRequestTable
          staff
          rows={data?.recent_requests ?? []}
          isLoading={isPending}
          errorMessage={errorMessage}
          onRetry={() => void refetch()}
          emptyTitle="No Administration requests yet"
          emptyDescription="New employee requests will appear here."
        />
      </Card>
    </div>
  )
}
