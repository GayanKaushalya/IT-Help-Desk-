import { Search } from 'lucide-react'
import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { useAdminAuditLogs, useAdminAuditLookups } from '@/features/admin/useAdminAuditLogs'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { ApiError } from '@/types/api'
import type { AuditLogEntry } from '@/types/models'
import { formatDateTime } from '@/utils/format'

export function AdminAuditLogsPage() {
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useAdminAuditLookups()

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      event: params.get('event') ?? '',
      entity_type: params.get('entity_type') ?? '',
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 20,
    }),
    [params],
  )

  const query = useAdminAuditLogs(filters)

  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) =>
    updateFilter('search', value),
  )

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const columns: DataTableColumn<AuditLogEntry>[] = [
    {
      id: 'created',
      header: 'Timestamp',
      cell: (row) => <span className="whitespace-nowrap text-xs">{formatDateTime(row.created_at)}</span>,
    },
    {
      id: 'user',
      header: 'User',
      cell: (row) => row.actor?.full_name ?? 'System',
    },
    {
      id: 'action',
      header: 'Action',
      cell: (row) => <span className="font-medium text-surface-900">{row.action ?? row.event}</span>,
    },
    {
      id: 'entity',
      header: 'Entity',
      cell: (row) => (
        <span className="capitalize">
          {row.entity_type ?? '—'}
          {row.entity_id ? ` #${row.entity_id}` : ''}
        </span>
      ),
    },
    {
      id: 'description',
      header: 'Description',
      cell: (row) => <span className="text-sm text-surface-600">{row.description ?? '—'}</span>,
      className: 'min-w-[16rem]',
    },
    {
      id: 'ip',
      header: 'IP address',
      cell: (row) => <span className="font-mono text-xs">{row.ip_address ?? '—'}</span>,
      className: 'whitespace-nowrap',
    },
  ]

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Audit logs could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="Audit logs"
        description="Append-only record of sign-in, ticket, user and administrative actions. Records cannot be edited from this screen."
      />

      <Card>
        <FilterToolbar className="xl:grid-cols-4">
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Action, description or IP"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>
          <FilterField label="Action">
            {(id) => (
              <Combobox
                id={id}
                value={filters.event}
                onChange={(value) => updateFilter('event', value)}
                placeholder="All actions"
                options={lookups?.events.map((event) => ({ value: event.value, label: event.label })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Entity">
            {(id) => (
              <Combobox
                id={id}
                value={filters.entity_type}
                onChange={(value) => updateFilter('entity_type', value)}
                placeholder="All entities"
                options={lookups?.entity_types.map((entity) => ({ value: entity.value, label: entity.label })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Date range">
            {(id) => (
              <DateRangeField
                fromId={id}
                from={filters.date_from}
                to={filters.date_to}
                onFromChange={(value) => updateFilter('date_from', value)}
                onToChange={(value) => updateFilter('date_to', value)}
              />
            )}
          </FilterField>
        </FilterToolbar>

        <DataTable
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          mobileCard={(row) => (
            <div className="rounded-xl border border-surface-200 bg-panel p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-surface-900">{row.action ?? row.event}</p>
                <p className="whitespace-nowrap text-[11px] text-surface-400">{formatDateTime(row.created_at)}</p>
              </div>
              <p className="mt-1 text-sm text-surface-600">{row.description ?? '—'}</p>
              <p className="mt-2 text-xs text-surface-500">
                {row.actor?.full_name ?? 'System'}
                {row.entity_type ? ` · ${row.entity_type}` : ''}
                {row.entity_id ? ` #${row.entity_id}` : ''}
                {row.ip_address ? ` · ${row.ip_address}` : ''}
              </p>
            </div>
          )}
          emptyTitle="No audit events match these filters"
          emptyDescription="Try a wider date range or clear a filter."
        />

        {query.data?.meta && (
          <Pagination meta={query.data.meta} onPageChange={(page) => updateFilter('page', String(page))} />
        )}
      </Card>
    </div>
  )
}
