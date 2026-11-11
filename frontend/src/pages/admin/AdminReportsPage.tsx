import {
  AlertTriangle,
  CheckCircle2,
  Clock3,
  Download,
  FileBarChart2,
  Inbox,
  RotateCcw,
  ShieldCheck,
  Ticket,
  Timer,
} from 'lucide-react'
import { lazy, Suspense, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import { ErrorState } from '@/components/ui/ErrorState'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Skeleton, SkeletonStatCard } from '@/components/ui/Skeleton'
import { triggerBrowserDownload, useAdminReports } from '@/features/admin/useAdminReports'
import { useStaffAgents } from '@/features/admin/useStaffDashboard'
import { useLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { adminService, type AdminReportParams } from '@/services/adminService'
import { ApiError } from '@/types/api'

const ReportCharts = lazy(() =>
  import('@/features/admin/ReportCharts').then((module) => ({ default: module.ReportCharts })),
)

function isoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function lastDays(days: number): { date_from: string; date_to: string } {
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - (days - 1))

  return { date_from: isoDate(from), date_to: isoDate(to) }
}

export function AdminReportsPage() {
  const { hasPermission } = useAuth()
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useLookups()
  const { data: agents } = useStaffAgents()
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null)
  const [exportError, setExportError] = useState<string | null>(null)

  const defaults = useMemo(() => lastDays(30), [])
  const allTime = params.get('range') === 'all'
  const canExport = hasPermission('reports.export')

  const dateFrom = allTime ? '' : (params.get('date_from') ?? defaults.date_from)
  const dateTo = allTime ? '' : (params.get('date_to') ?? defaults.date_to)

  const filters = useMemo<AdminReportParams>(
    () => ({
      date_from: dateFrom,
      date_to: dateTo,
      branch_id: params.get('branch_id') ?? '',
      category_id: params.get('category_id') ?? '',
      priority_id: params.get('priority_id') ?? '',
      assigned_to: params.get('assigned_to') ?? '',
      status_id: params.get('status_id') ?? '',
    }),
    [dateFrom, dateTo, params],
  )

  const query = useAdminReports(filters)
  const kpis = query.data?.kpis
  const empty = !query.isPending && !query.isError && (kpis?.ticket_volume ?? 0) === 0

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key === 'date_from' || key === 'date_to') {
      next.delete('range')
    }
    setParams(next)
  }

  function applyPreset(days: number | null) {
    const next = new URLSearchParams(params)

    if (days === null) {
      next.delete('date_from')
      next.delete('date_to')
      next.set('range', 'all')
      setParams(next)
      return
    }

    const range = lastDays(days)
    next.delete('range')
    next.set('date_from', range.date_from)
    next.set('date_to', range.date_to)
    setParams(next)
  }

  function resetFilters() {
    const next = new URLSearchParams()
    next.set('date_from', defaults.date_from)
    next.set('date_to', defaults.date_to)
    setParams(next)
  }

  async function exportReport(format: 'csv' | 'pdf') {
    setExportError(null)
    setExporting(format)

    try {
      const file = await adminService.exportReport(filters, format)
      triggerBrowserDownload(file.blob, file.filename)
    } catch (error) {
      setExportError(error instanceof ApiError ? error.message : 'The report could not be exported.')
    } finally {
      setExporting(null)
    }
  }

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Reports could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Reports and Analytics"
        description="Service-desk KPIs and charts from the database. CSV and PDF exports include every matching ticket with requester, issue, and resolution details."
        actions={
          canExport ? (
            <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                leadingIcon={<Download className="size-4" />}
                isLoading={exporting === 'csv'}
                onClick={() => void exportReport('csv')}
              >
                Export CSV
              </Button>
              <Button
                variant="outline"
                leadingIcon={<Download className="size-4" />}
                isLoading={exporting === 'pdf'}
                onClick={() => void exportReport('pdf')}
              >
                Export PDF
              </Button>
            </div>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar className="xl:grid-cols-3 2xl:grid-cols-6">
          <FilterField label="Date range">
            {(id) => (
              <DateRangeField
                fromId={id}
                from={dateFrom}
                to={dateTo}
                onFromChange={(value) => updateFilter('date_from', value)}
                onToChange={(value) => updateFilter('date_to', value)}
              />
            )}
          </FilterField>
          <FilterField label="Branch">
            {(id) => (
              <Combobox
                id={id}
                value={filters.branch_id ?? ''}
                onChange={(value) => updateFilter('branch_id', value)}
                placeholder="All branches"
                options={lookups?.branches.map((branch) => ({ value: String(branch.id), label: branch.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Category">
            {(id) => (
              <Combobox
                id={id}
                value={filters.category_id ?? ''}
                onChange={(value) => updateFilter('category_id', value)}
                placeholder="All categories"
                options={lookups?.categories.map((category) => ({ value: String(category.id), label: category.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Priority">
            {(id) => (
              <Combobox
                id={id}
                value={filters.priority_id ?? ''}
                onChange={(value) => updateFilter('priority_id', value)}
                placeholder="All priorities"
                options={lookups?.priorities.map((priority) => ({ value: String(priority.id), label: priority.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="IT staff">
            {(id) => (
              <Combobox
                id={id}
                value={filters.assigned_to ?? ''}
                onChange={(value) => updateFilter('assigned_to', value)}
                placeholder="All IT staff"
                options={agents?.map((agent) => ({ value: String(agent.id), label: agent.full_name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Status">
            {(id) => (
              <Combobox
                id={id}
                value={filters.status_id ?? ''}
                onChange={(value) => updateFilter('status_id', value)}
                placeholder="All statuses"
                options={lookups?.statuses.map((status) => ({ value: String(status.id), label: status.name })) ?? []}
              />
            )}
          </FilterField>
        </FilterToolbar>
        <div className="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6">
          <Button size="sm" variant="ghost" onClick={() => applyPreset(7)}>
            Last 7 days
          </Button>
          <Button size="sm" variant="ghost" onClick={() => applyPreset(30)}>
            Last 30 days
          </Button>
          <Button size="sm" variant="ghost" onClick={() => applyPreset(90)}>
            Last 90 days
          </Button>
          <Button size="sm" variant="ghost" onClick={() => applyPreset(null)}>
            All time
          </Button>
          <Button size="sm" variant="outline" leadingIcon={<RotateCcw className="size-3.5" />} onClick={resetFilters}>
            Reset
          </Button>
        </div>
      </Card>

      {exportError && (
        <p className="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700">{exportError}</p>
      )}

      {errorMessage && !query.data ? (
        <Card>
          <ErrorState
            title="Reports could not be loaded"
            description={errorMessage}
            onRetry={() => void query.refetch()}
            isRetrying={query.isFetching}
          />
        </Card>
      ) : (
        <>
          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {query.isPending ? (
              Array.from({ length: 8 }).map((_, index) => <SkeletonStatCard key={index} />)
            ) : (
              <>
                <StatCard
                  label="Ticket volume"
                  value={kpis?.ticket_volume ?? 0}
                  hint="Tickets created in this period"
                  icon={Ticket}
                  tone="neutral"
                />
                <StatCard
                  label="Closed tickets"
                  value={kpis?.closed_tickets ?? 0}
                  hint={`${kpis?.open_tickets ?? 0} still open`}
                  icon={Inbox}
                  tone="success"
                />
                <StatCard
                  label="Average resolution"
                  value={kpis?.average_resolution.label ?? '—'}
                  hint={
                    kpis?.average_resolution.sample_size
                      ? `Based on ${kpis.average_resolution.sample_size} resolved tickets`
                      : 'No resolved tickets in this period'
                  }
                  icon={Timer}
                  tone="primary"
                />
                <StatCard
                  label="Average first response"
                  value={kpis?.average_first_response.label ?? '—'}
                  hint={
                    kpis?.average_first_response.sample_size
                      ? `Based on ${kpis.average_first_response.sample_size} first responses`
                      : 'No first responses recorded'
                  }
                  icon={Clock3}
                  tone="info"
                />
                <StatCard
                  label="Open ticket aging"
                  value={kpis?.aged_open_tickets ?? 0}
                  hint="Open more than 7 days"
                  icon={AlertTriangle}
                  tone="warning"
                />
                <StatCard
                  label="Reopened tickets"
                  value={kpis?.reopened_tickets ?? 0}
                  hint={`${kpis?.reopen_events ?? 0} reopen events`}
                  icon={RotateCcw}
                  tone="warning"
                />
                <StatCard
                  label="Verification completion"
                  value={`${kpis?.verification.completion_rate ?? 0}%`}
                  hint={`${kpis?.verification.verified ?? 0} of ${kpis?.verification.issued ?? 0} codes verified`}
                  icon={ShieldCheck}
                  tone="success"
                />
                <StatCard
                  label="Critical tickets"
                  value={kpis?.critical_tickets ?? 0}
                  hint={`${kpis?.open_critical_tickets ?? 0} still open`}
                  icon={CheckCircle2}
                  tone="danger"
                />
              </>
            )}
          </section>

          {query.isPending ? (
            <div className="grid gap-6 xl:grid-cols-2">
              {Array.from({ length: 4 }).map((_, index) => (
                <Card key={index} className="h-80 p-6">
                  <Skeleton className="h-4 w-40" />
                  <Skeleton className="mt-6 h-56 w-full" />
                </Card>
              ))}
            </div>
          ) : empty ? (
            <Card>
              <EmptyState
                icon={FileBarChart2}
                title="No tickets in this period"
                description="Try a wider date range or clear a filter. Charts stay empty until matching tickets exist."
              />
            </Card>
          ) : query.data ? (
            <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
              <ReportCharts
                volume={query.data.charts.volume}
                byBranch={query.data.charts.by_branch}
                byCategory={query.data.charts.by_category}
                byPriority={query.data.charts.by_priority}
                byStatus={query.data.charts.by_status}
                byStaff={query.data.charts.by_staff}
                aging={query.data.charts.aging}
                verification={query.data.charts.verification}
              />
            </Suspense>
          ) : null}
        </>
      )}
    </div>
  )
}
