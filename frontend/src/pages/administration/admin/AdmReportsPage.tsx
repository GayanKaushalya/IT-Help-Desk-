import {
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  Clock,
  FileText,
  Gauge,
  Inbox,
  ShieldAlert,
  UserX,
  Video,
} from 'lucide-react'
import { useState, type ReactNode } from 'react'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Combobox } from '@/components/ui/Combobox'
import { ErrorState } from '@/components/ui/ErrorState'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { LoadingPanel } from '@/components/ui/Spinner'
import { useAdmLookups } from '@/features/administration/useAdm'
import { useAdmReport } from '@/features/administration/registers/useAdmRegisters'
import type { AdmReportParams } from '@/types/admRegisters'
import { formatDateTime } from '@/utils/format'

const TONE_BAR: Record<string, string> = {
  neutral: 'bg-surface-400',
  primary: 'bg-primary-500',
  success: 'bg-success-500',
  warning: 'bg-warning-500',
  danger: 'bg-danger-500',
  info: 'bg-info-500',
}

function BarRow({ label, value, max, tone = 'primary', suffix }: { label: string; value: number; max: number; tone?: string; suffix?: string }) {
  const width = max > 0 ? Math.max(2, Math.round((value / max) * 100)) : 0

  return (
    <li>
      <div className="flex items-center justify-between gap-3 text-sm">
        <span className="truncate text-surface-700">{label}</span>
        <span className="font-medium text-surface-900">
          {value}
          {suffix}
        </span>
      </div>
      <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface-100">
        <div className={`h-full rounded-full ${TONE_BAR[tone] ?? TONE_BAR.primary}`} style={{ width: `${value > 0 ? width : 0}%` }} />
      </div>
    </li>
  )
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader title={title} description={description} />
      <CardContent>{children}</CardContent>
    </Card>
  )
}

function Metric({ label, value, tone }: { label: string; value: number | string; tone?: 'danger' | 'warning' }) {
  return (
    <div className="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3">
      <p className="text-xs text-surface-500">{label}</p>
      <p
        className={`mt-1 text-xl font-semibold ${tone === 'danger' ? 'text-danger-600' : tone === 'warning' ? 'text-warning-600' : 'text-surface-900'}`}
      >
        {value}
      </p>
    </div>
  )
}

export function AdmReportsPage() {
  const [filters, setFilters] = useState<{ date_from: string; date_to: string; category_id: string }>({
    date_from: '',
    date_to: '',
    category_id: '',
  })
  const params: AdmReportParams = {
    ...(filters.date_from ? { date_from: filters.date_from } : {}),
    ...(filters.date_to ? { date_to: filters.date_to } : {}),
    ...(filters.category_id ? { category_id: filters.category_id } : {}),
  }
  const { data: report, isPending, isError, refetch, isFetching } = useAdmReport(params)
  const { data: lookups } = useAdmLookups()

  const hasFilters = Object.values(filters).some(Boolean)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reports"
        description="Live figures from Administration requests, insurance and vehicle bookings."
        actions={
          <Button variant="outline" isLoading={isFetching && !isPending} onClick={() => void refetch()}>
            Refresh
          </Button>
        }
      />

      <Card>
        <FilterToolbar className="md:grid-cols-4 xl:grid-cols-4">
          <FilterField label="Created from">
            {(id) => (
              <Input id={id} type="date" value={filters.date_from} onChange={(event) => setFilters((c) => ({ ...c, date_from: event.target.value }))} />
            )}
          </FilterField>
          <FilterField label="Created to">
            {(id) => (
              <Input id={id} type="date" value={filters.date_to} onChange={(event) => setFilters((c) => ({ ...c, date_to: event.target.value }))} />
            )}
          </FilterField>
          <FilterField label="Category">
            {(id) => (
              <Combobox
                id={id}
                value={filters.category_id}
                onChange={(value) => setFilters((c) => ({ ...c, category_id: value }))}
                options={(lookups?.categories ?? []).map((category) => ({ value: String(category.id), label: category.name }))}
                placeholder="All categories"
              />
            )}
          </FilterField>
          <div className="flex items-end">
            <Button variant="ghost" disabled={!hasFilters} onClick={() => setFilters({ date_from: '', date_to: '', category_id: '' })}>
              Clear filters
            </Button>
          </div>
        </FilterToolbar>
      </Card>

      {isPending ? (
        <LoadingPanel label="Loading report..." />
      ) : isError || !report ? (
        <ErrorState title="Unable to load the report" onRetry={() => void refetch()} />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Total requests" value={report.kpis.total_requests} icon={Inbox} tone="primary" />
            <StatCard label="Open" value={report.kpis.open_requests} icon={Clock} tone="info" hint={`${report.kpis.unassigned_requests} unassigned`} />
            <StatCard label="Overdue" value={report.kpis.overdue_requests} icon={AlertTriangle} tone={report.kpis.overdue_requests > 0 ? 'danger' : 'success'} />
            <StatCard
              label="SLA compliance"
              value={report.kpis.sla_compliance_percent === null ? '\u2014' : `${report.kpis.sla_compliance_percent}%`}
              icon={Gauge}
              tone="success"
              hint="Resolved within target"
            />
            <StatCard label="Resolved" value={report.kpis.resolved_requests} icon={CheckCircle2} tone="success" />
            <StatCard label="Closed" value={report.kpis.closed_requests} icon={FileText} tone="neutral" />
            <StatCard
              label="Avg. resolution"
              value={report.kpis.avg_resolution_hours === null ? '\u2014' : `${report.kpis.avg_resolution_hours}h`}
              icon={CalendarClock}
              tone="info"
            />
            <StatCard label="CCTV pending approval" value={report.kpis.pending_cctv_approvals} icon={Video} tone={report.kpis.pending_cctv_approvals > 0 ? 'warning' : 'neutral'} />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <Section title="Requests by category" description="Total and still-open requests.">
              {report.by_category.length === 0 ? (
                <p className="text-sm text-surface-500">No requests in this period.</p>
              ) : (
                <ul className="space-y-4">
                  {report.by_category.map((row) => (
                    <BarRow
                      key={row.id}
                      label={`${row.name} (${row.open} open)`}
                      value={row.total}
                      max={Math.max(...report.by_category.map((item) => item.total))}
                    />
                  ))}
                </ul>
              )}
            </Section>

            <Section title="Requests by status">
              {report.by_status.every((row) => row.count === 0) ? (
                <p className="text-sm text-surface-500">No requests in this period.</p>
              ) : (
                <ul className="space-y-4">
                  {report.by_status.map((row) => (
                    <BarRow
                      key={row.id}
                      label={row.name}
                      value={row.count}
                      tone={row.color_token}
                      max={Math.max(...report.by_status.map((item) => item.count))}
                    />
                  ))}
                </ul>
              )}
            </Section>
          </div>

          <Section title="Last 6 months" description="Requests created and resolved each month.">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-surface-500 uppercase">
                    <th className="py-2 pr-4 font-medium">Month</th>
                    <th className="py-2 pr-4 font-medium">Created</th>
                    <th className="py-2 pr-4 font-medium">Resolved</th>
                    <th className="w-1/2 py-2 font-medium">Volume</th>
                  </tr>
                </thead>
                <tbody>
                  {report.monthly_trend.map((row) => {
                    const peak = Math.max(1, ...report.monthly_trend.map((item) => Math.max(item.created, item.resolved)))

                    return (
                      <tr key={row.month} className="border-t border-surface-100">
                        <td className="py-2 pr-4 text-surface-800">{row.label}</td>
                        <td className="py-2 pr-4">{row.created}</td>
                        <td className="py-2 pr-4">{row.resolved}</td>
                        <td className="py-2">
                          <div className="space-y-1">
                            <div className="h-1.5 rounded-full bg-primary-500" style={{ width: `${(row.created / peak) * 100}%` }} />
                            <div className="h-1.5 rounded-full bg-success-500" style={{ width: `${(row.resolved / peak) * 100}%` }} />
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <div className="mt-3 flex gap-4 text-xs text-surface-500">
              <span className="flex items-center gap-1.5">
                <span className="size-2 rounded-full bg-primary-500" /> Created
              </span>
              <span className="flex items-center gap-1.5">
                <span className="size-2 rounded-full bg-success-500" /> Resolved
              </span>
            </div>
          </Section>

          <div className="grid gap-6 lg:grid-cols-2">
            <Section title="Insurance" description="Policies by expiry. These figures ignore the date filters.">
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Metric label="Active policies" value={report.insurance.active} />
                <Metric label="Expired" value={report.insurance.expired} tone={report.insurance.expired > 0 ? 'danger' : undefined} />
                <Metric label="Renewal in progress" value={report.insurance.renewal_in_progress} />
                <Metric label="Expiring in 30 days" value={report.insurance.expiring_30_days} tone={report.insurance.expiring_30_days > 0 ? 'warning' : undefined} />
                <Metric label="Expiring in 60 days" value={report.insurance.expiring_60_days} />
                <Metric label="Expiring in 90 days" value={report.insurance.expiring_90_days} />
              </div>
              {report.insurance.expired > 0 && (
                <p className="mt-3 flex items-center gap-2 text-sm text-danger-600">
                  <ShieldAlert className="size-4" /> {report.insurance.expired} policies have expired and need renewing.
                </p>
              )}
            </Section>

            <Section title="Vehicle allocations">
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Metric label="Current" value={report.allocations.current} />
                <Metric label="Upcoming" value={report.allocations.upcoming} />
                <Metric label="Completed" value={report.allocations.completed} />
                <Metric label="Cancelled" value={report.allocations.cancelled} />
                <Metric label="Active in period" value={report.allocations.in_period_active} />
                <Metric label="Total" value={report.allocations.total} />
              </div>
            </Section>
          </div>

          <Section title="Registers">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              <Metric label="Vehicles" value={`${report.registers.vehicles_available} / ${report.registers.vehicles} available`} />
              <Metric label="Drivers" value={`${report.registers.drivers_active} / ${report.registers.drivers} active`} />
              <Metric label="Fuel cards" value={`${report.registers.fuel_cards_active} / ${report.registers.fuel_cards} active`} />
              <Metric label="Insurance policies" value={report.registers.insurance_policies} />
            </div>
            <p className="mt-4 flex items-center gap-2 text-xs text-surface-500">
              <Badge tone="neutral">Generated</Badge> {formatDateTime(report.generated_at)}
              {report.kpis.unassigned_requests > 0 && (
                <span className="ml-3 flex items-center gap-1 text-warning-600">
                  <UserX className="size-3.5" /> {report.kpis.unassigned_requests} requests have no assignee
                </span>
              )}
            </p>
          </Section>
        </>
      )}
    </div>
  )
}
