import { AlarmClock, CheckCircle2, ClipboardList, FileBarChart2, Hourglass, Inbox, ScrollText, ShieldAlert } from 'lucide-react'
import { lazy, Suspense, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton, SkeletonStatCard } from '@/components/ui/Skeleton'
import { HrRequestTable } from '@/features/hr/HrRequestTable'
import { useHrReports } from '@/features/hr/useHrReports'
import { useHrStaffDashboard } from '@/features/hr/useHrStaff'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'

const HrAdminCharts = lazy(() =>
  import('@/features/hr/HrAdminCharts').then((module) => ({ default: module.HrAdminCharts })),
)

const HrReportCharts = lazy(() =>
  import('@/features/hr/HrReportCharts').then((module) => ({ default: module.HrReportCharts })),
)

function lastDays(days: number): { date_from: string; date_to: string } {
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - (days - 1))
  const iso = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

  return { date_from: iso(from), date_to: iso(to) }
}

export function HrAdminDashboardPage() {
  const { user, hasPermission } = useAuth()
  const navigate = useNavigate()
  const { data, isPending, isError, error, refetch } = useHrStaffDashboard()
  const canViewReports = hasPermission('hr.reports.view')
  const reportFilters = useMemo(() => lastDays(30), [])
  const reports = useHrReports(reportFilters, canViewReports)

  const stats = data?.statistics
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'The HR service desk could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="HR Service Desk"
        description={`HR operations for ${user?.role?.name ?? 'HR staff'}.`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => void navigate(paths.hr.admin.requests)}>
              Open request queue
            </Button>
            {canViewReports && (
              <>
                <Button
                  variant="outline"
                  leadingIcon={<FileBarChart2 className="size-4" />}
                  onClick={() => void navigate(paths.hr.admin.reports)}
                >
                  Reports
                </Button>
                <Button
                  variant="outline"
                  leadingIcon={<ScrollText className="size-4" />}
                  onClick={() => void navigate(paths.hr.admin.auditLogs)}
                >
                  Audit logs
                </Button>
              </>
            )}
          </div>
        }
      />

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {isPending ? (
          Array.from({ length: 6 }).map((_, index) => <SkeletonStatCard key={index} />)
        ) : (
          <>
            <StatCard label="Total Requests" value={stats?.total ?? 0} icon={Inbox} tone="neutral" />
            <StatCard label="Pending" value={stats?.pending ?? 0} icon={Hourglass} tone="info" />
            <StatCard label="In Progress" value={stats?.in_progress ?? 0} icon={ClipboardList} tone="primary" />
            <StatCard label="Completed" value={stats?.completed ?? 0} icon={CheckCircle2} tone="success" />
            <StatCard label="Overdue" value={stats?.overdue ?? 0} icon={AlarmClock} tone="warning" />
            <StatCard label="Complaints" value={stats?.complaints ?? 0} icon={ShieldAlert} tone="danger" />
          </>
        )}
      </section>

      {data && (
        <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
          <HrAdminCharts byStatus={data.by_status} byCategory={data.by_category} />
        </Suspense>
      )}

      <Card>
        <CardHeader
          title="Recent requests"
          description="The newest HR requests you can see."
          action={
            <Button variant="ghost" size="sm" onClick={() => void navigate(paths.hr.admin.requests)}>
              View all
            </Button>
          }
        />
        <HrRequestTable
          staff
          rows={data?.recent_requests ?? []}
          isLoading={isPending}
          errorMessage={errorMessage}
          onRetry={() => void refetch()}
          emptyTitle="No HR requests yet"
          emptyDescription="New employee requests will appear here."
        />
      </Card>

      {canViewReports && reports.data && (reports.data.kpis.request_volume ?? 0) > 0 && (
        <section className="space-y-4">
          <div className="flex items-end justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold tracking-tight text-surface-900">Monitoring (last 30 days)</h2>
              <p className="mt-1 text-sm text-surface-600">Live charts from the HR reports engine.</p>
            </div>
            <Button variant="ghost" size="sm" onClick={() => void navigate(paths.hr.admin.reports)}>
              Open full reports
            </Button>
          </div>
          <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
            <HrReportCharts
              volume={reports.data.charts.volume}
              complaintTrends={reports.data.charts.complaint_trends}
              byBranch={reports.data.charts.by_branch}
              byCategory={reports.data.charts.by_category}
              byRequestType={reports.data.charts.by_request_type}
              byStatus={reports.data.charts.by_status}
              byStaff={reports.data.charts.by_staff}
              aging={reports.data.charts.aging}
              referrals={reports.data.charts.referrals}
            />
          </Suspense>
        </section>
      )}
    </div>
  )
}
