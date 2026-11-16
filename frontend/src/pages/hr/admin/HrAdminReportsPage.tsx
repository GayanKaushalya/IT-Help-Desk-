import {
  AlarmClock,
  AlertTriangle,
  CheckCircle2,
  Clock3,
  Download,
  FileBarChart2,
  Inbox,
  RotateCcw,
  Share2,
  ShieldAlert,
  Timer,
} from 'lucide-react'
import { lazy, Suspense, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { EmptyState } from '@/components/ui/EmptyState'
import { ErrorState } from '@/components/ui/ErrorState'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Skeleton, SkeletonStatCard } from '@/components/ui/Skeleton'
import { useHrLookups } from '@/features/hr/useHr'
import { triggerBrowserDownload, useHrReports } from '@/features/hr/useHrReports'
import { useHrStaffAgents } from '@/features/hr/useHrStaff'
import { useLookups } from '@/features/system/useSystemStatus'
import { useAuth } from '@/hooks/useAuth'
import { hrService, type HrReportParams } from '@/services/hrService'
import { ApiError } from '@/types/api'

const HrReportCharts = lazy(() =>
  import('@/features/hr/HrReportCharts').then((module) => ({ default: module.HrReportCharts })),
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

export function HrAdminReportsPage() {
  const { hasPermission } = useAuth()
  const [params, setParams] = useSearchParams()
  const { data: systemLookups } = useLookups()
  const { data: hrLookups } = useHrLookups()
  const { data: agents } = useHrStaffAgents()
  const [exporting, setExporting] = useState<'csv' | 'pdf' | null>(null)
  const [exportError, setExportError] = useState<string | null>(null)

  const defaults = useMemo(() => lastDays(30), [])
  const allTime = params.get('range') === 'all'
  const canExport = hasPermission('hr.reports.export')

  const dateFrom = allTime ? '' : (params.get('date_from') ?? defaults.date_from)
  const dateTo = allTime ? '' : (params.get('date_to') ?? defaults.date_to)
  const selectedCategory = params.get('category_id') ?? ''

  const filters = useMemo<HrReportParams>(
    () => ({
      date_from: dateFrom,
      date_to: dateTo,
      branch_id: params.get('branch_id') ?? '',
      category_id: selectedCategory,
      request_type_id: params.get('request_type_id') ?? '',
      assigned_to: params.get('assigned_to') ?? '',
      status_id: params.get('status_id') ?? '',
      confidential: params.get('confidential') ?? '',
    }),
    [dateFrom, dateTo, params, selectedCategory],
  )

  const query = useHrReports(filters)
  const kpis = query.data?.kpis
  const empty = !query.isPending && !query.isError && (kpis?.request_volume ?? 0) === 0

  const requestTypeOptions = useMemo(() => {
    const categories = hrLookups?.categories ?? []
    const scoped = selectedCategory
      ? categories.filter((category) => String(category.id) === selectedCategory)
      : categories

    return scoped.flatMap((category) =>
      (category.types ?? []).map((type) => ({
        value: String(type.id),
        label: selectedCategory ? type.name : `${category.name} · ${type.name}`,
      })),
    )
  }, [hrLookups?.categories, selectedCategory])

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key === 'date_from' || key === 'date_to') next.delete('range')
    if (key === 'category_id') next.delete('request_type_id')
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
      const file = await hrService.staff.exportReport(filters, format)
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
        title="HR Reports and Monitoring"
        description="HR service-desk KPIs and charts from live request data. CSV and PDF exports include every matching request with requester, category, and resolution details."
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
                options={systemLookups?.branches.map((branch) => ({ value: String(branch.id), label: branch.name })) ?? []}
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
                options={hrLookups?.categories.map((category) => ({ value: String(category.id), label: category.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Request type">
            {(id) => (
              <Combobox
                id={id}
                value={filters.request_type_id ?? ''}
                onChange={(value) => updateFilter('request_type_id', value)}
                placeholder="All types"
                options={requestTypeOptions}
              />
            )}
          </FilterField>
          <FilterField label="HR staff">
            {(id) => (
              <Combobox
                id={id}
                value={filters.assigned_to ?? ''}
                onChange={(value) => updateFilter('assigned_to', value)}
                placeholder="All HR staff"
                options={agents?.map((agent) => ({
                  value: String(agent.id),
                  label: agent.role ? `${agent.full_name} · ${agent.role}` : agent.full_name,
                })) ?? []}
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
                options={hrLookups?.statuses.map((status) => ({ value: String(status.id), label: status.name })) ?? []}
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
          <Button
            size="sm"
            variant={filters.confidential === '1' ? 'primary' : 'ghost'}
            onClick={() => updateFilter('confidential', filters.confidential === '1' ? '' : '1')}
          >
            Confidential only
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
                  label="Request volume"
                  value={kpis?.request_volume ?? 0}
                  hint="Requests created in this period"
                  icon={Inbox}
                  tone="neutral"
                />
                <StatCard
                  label="Completed requests"
                  value={kpis?.completed_requests ?? 0}
                  hint={`${kpis?.open_requests ?? 0} still open`}
                  icon={CheckCircle2}
                  tone="success"
                />
                <StatCard
                  label="Average resolution"
                  value={kpis?.average_resolution.label ?? '—'}
                  hint={
                    kpis?.average_resolution.sample_size
                      ? `Based on ${kpis.average_resolution.sample_size} resolved requests`
                      : 'No resolved requests in this period'
                  }
                  icon={Timer}
                  tone="primary"
                />
                <StatCard
                  label="Average first response"
                  value={kpis?.average_first_response.label ?? '—'}
                  hint={
                    kpis?.average_first_response.sample_size
                      ? `Based on ${kpis.average_first_response.sample_size} assignments`
                      : 'No assignments recorded'
                  }
                  icon={Clock3}
                  tone="info"
                />
                <StatCard
                  label="Overdue requests"
                  value={kpis?.overdue_requests ?? 0}
                  hint="Past SLA due date"
                  icon={AlarmClock}
                  tone="warning"
                />
                <StatCard
                  label="Open request aging"
                  value={kpis?.aged_open_requests ?? 0}
                  hint="Open more than 7 days"
                  icon={AlertTriangle}
                  tone="warning"
                />
                <StatCard
                  label="Complaints"
                  value={kpis?.complaints ?? 0}
                  hint={`${kpis?.confidential_requests ?? 0} confidential overall`}
                  icon={ShieldAlert}
                  tone="danger"
                />
                <StatCard
                  label="Referral completion"
                  value={`${kpis?.referrals.completion_rate ?? 0}%`}
                  hint={`${kpis?.referrals.completed ?? 0} of ${kpis?.referrals.total ?? 0} referrals completed`}
                  icon={Share2}
                  tone="success"
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
                title="No HR requests in this period"
                description="Try a wider date range or clear a filter. Charts stay empty until matching requests exist."
              />
            </Card>
          ) : query.data ? (
            <Suspense fallback={<Skeleton className="h-80 w-full rounded-card" />}>
              <HrReportCharts
                volume={query.data.charts.volume}
                complaintTrends={query.data.charts.complaint_trends}
                byBranch={query.data.charts.by_branch}
                byCategory={query.data.charts.by_category}
                byRequestType={query.data.charts.by_request_type}
                byStatus={query.data.charts.by_status}
                byStaff={query.data.charts.by_staff}
                aging={query.data.charts.aging}
                referrals={query.data.charts.referrals}
              />
            </Suspense>
          ) : null}
        </>
      )}
    </div>
  )
}
