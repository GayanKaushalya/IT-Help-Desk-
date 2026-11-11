import { Search } from 'lucide-react'
import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/Card'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { AdminTicketTable } from '@/features/admin/AdminTicketTable'
import { useStaffAssignedTickets, useStaffAgents, useStaffTicketQueue } from '@/features/admin/useStaffDashboard'
import { useLookups } from '@/features/system/useSystemStatus'
import { ApiError } from '@/types/api'

export function AdminTicketsPage({ assignedOnly = false }: { assignedOnly?: boolean }) {
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useLookups()
  const { data: agents } = useStaffAgents()

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status_id: params.get('status_id') ?? '',
      priority_id: params.get('priority_id') ?? '',
      category_id: params.get('category_id') ?? '',
      branch_id: params.get('branch_id') ?? '',
      assigned_to: params.get('assigned_to') ?? '',
      unassigned: params.get('unassigned') === '1',
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 15,
    }),
    [params],
  )

  const queue = useStaffTicketQueue(filters, !assignedOnly)
  const mine = useStaffAssignedTickets(filters, assignedOnly)
  const query = assignedOnly ? mine : queue

  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) =>
    updateFilter('search', value),
  )

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) {
      next.set(key, value)
    } else {
      next.delete(key)
    }
    if (key === 'assigned_to') next.delete('unassigned')
    if (key === 'unassigned') next.delete('assigned_to')
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const rows = query.data?.data ?? []
  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'The ticket queue could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title={assignedOnly ? 'My Assigned Tickets' : 'Tickets'}
        description={
          assignedOnly
            ? 'Requests currently assigned to you.'
            : 'Organisation-wide service desk queue.'
        }
      />

      <Card>
        <FilterToolbar className="xl:grid-cols-4 2xl:grid-cols-7">
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Number or subject"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>
          <FilterField label="Status">
            {(id) => (
              <Combobox
                id={id}
                value={filters.status_id}
                onChange={(value) => updateFilter('status_id', value)}
                placeholder="All statuses"
                options={
                  lookups?.statuses.map((status) => ({
                    value: String(status.id),
                    label: status.slug === 'new' ? 'Open' : status.name,
                  })) ?? []
                }
              />
            )}
          </FilterField>
          <FilterField label="Priority">
            {(id) => (
              <Combobox
                id={id}
                value={filters.priority_id}
                onChange={(value) => updateFilter('priority_id', value)}
                placeholder="All priorities"
                options={lookups?.priorities.map((priority) => ({ value: String(priority.id), label: priority.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Category">
            {(id) => (
              <Combobox
                id={id}
                value={filters.category_id}
                onChange={(value) => updateFilter('category_id', value)}
                placeholder="All categories"
                options={lookups?.categories.map((category) => ({ value: String(category.id), label: category.name })) ?? []}
              />
            )}
          </FilterField>
          <FilterField label="Branch">
            {(id) => (
              <Combobox
                id={id}
                value={filters.branch_id}
                onChange={(value) => updateFilter('branch_id', value)}
                placeholder="All branches"
                options={lookups?.branches.map((branch) => ({ value: String(branch.id), label: branch.name })) ?? []}
              />
            )}
          </FilterField>
          {!assignedOnly && (
            <FilterField label="Assignee">
              {(id) => (
                <Combobox
                  id={id}
                  value={filters.unassigned ? 'unassigned' : filters.assigned_to}
                  onChange={(value) => {
                    const next = new URLSearchParams(params)
                    next.delete('page')
                    if (value === 'unassigned') {
                      next.set('unassigned', '1')
                      next.delete('assigned_to')
                    } else if (value) {
                      next.set('assigned_to', value)
                      next.delete('unassigned')
                    } else {
                      next.delete('assigned_to')
                      next.delete('unassigned')
                    }
                    setParams(next)
                  }}
                  placeholder="All assignees"
                  options={[
                    { value: 'unassigned', label: 'Unassigned' },
                    ...(agents?.map((agent) => ({
                      value: String(agent.id),
                      label: `${agent.full_name} (${agent.open_tickets} open)`,
                    })) ?? []),
                  ]}
                />
              )}
            </FilterField>
          )}
          <FilterField label="Created">
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

        <AdminTicketTable
          rows={rows}
          isLoading={query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle={assignedOnly ? 'No tickets assigned to you' : 'No tickets match these filters'}
          emptyDescription={
            assignedOnly
              ? 'Tickets assigned to you will appear in this queue.'
              : 'Try clearing a filter or wait for a new request.'
          }
        />

        {query.data?.meta && (
          <Pagination meta={query.data.meta} onPageChange={(page) => updateFilter('page', String(page))} />
        )}
      </Card>
    </div>
  )
}
