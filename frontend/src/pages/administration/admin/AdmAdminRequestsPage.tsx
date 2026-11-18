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
import { AdmRequestTable } from '@/features/administration/AdmRequestTable'
import { useAdmLookups } from '@/features/administration/useAdm'
import { useAdmStaffAgents, useAdmStaffAssigned, useAdmStaffQueue } from '@/features/administration/useAdmStaff'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { ApiError } from '@/types/api'

export interface AdmAdminRequestsPageProps {
  /** Only requests assigned to the signed-in user. */
  assignedOnly?: boolean
}

export function AdmAdminRequestsPage({ assignedOnly = false }: AdmAdminRequestsPageProps) {
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useAdmLookups()
  const { data: agents } = useAdmStaffAgents()

  // Sidebar category links use ?category=<slug>; resolve it to the id the API filters on.
  const categorySlug = params.get('category') ?? ''
  const slugCategory = categorySlug ? lookups?.categories.find((category) => category.slug === categorySlug) : undefined
  const categoryId = slugCategory ? String(slugCategory.id) : (params.get('category_id') ?? '')
  const waitingForLookups = Boolean(categorySlug) && !lookups

  const title = assignedOnly ? 'My Assigned Requests' : (slugCategory?.name ?? 'Administration Requests')
  const description = assignedOnly
    ? 'Administration requests currently assigned to you.'
    : slugCategory
      ? `Administration requests in ${slugCategory.name}.`
      : 'Organisation-wide Administration service desk queue.'

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status_id: params.get('status_id') ?? '',
      category_id: categoryId,
      assigned_to: params.get('assigned_to') ?? '',
      unassigned: params.get('unassigned') === '1',
      overdue: params.get('overdue') === '1',
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 15,
    }),
    [params, categoryId],
  )

  const queue = useAdmStaffQueue(filters, !assignedOnly && !waitingForLookups)
  const mine = useAdmStaffAssigned(filters, assignedOnly)
  const query = assignedOnly ? mine : queue

  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) => updateFilter('search', value))

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) {
      next.set(key, value)
    } else {
      next.delete(key)
    }
    if (key === 'assigned_to') next.delete('unassigned')
    // Picking a category by hand replaces the slug the sidebar link set.
    if (key === 'category_id') next.delete('category')
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'The request queue could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader title={title} description={description} />

      <Card>
        <FilterToolbar className="xl:grid-cols-3 2xl:grid-cols-6">
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Reference, subject or employee"
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
                options={lookups?.statuses.map((status) => ({ value: String(status.id), label: status.name })) ?? []}
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
                options={
                  lookups?.categories.map((category) => ({ value: String(category.id), label: category.name })) ?? []
                }
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
                      label: agent.role ? `${agent.full_name} \u00b7 ${agent.role}` : agent.full_name,
                    })) ?? []),
                  ]}
                />
              )}
            </FilterField>
          )}

          <FilterField label="Timeliness">
            {(id) => (
              <Combobox
                id={id}
                value={filters.overdue ? 'overdue' : ''}
                onChange={(value) => updateFilter('overdue', value === 'overdue' ? '1' : '')}
                placeholder="All requests"
                options={[{ value: 'overdue', label: 'Overdue only' }]}
              />
            )}
          </FilterField>

          <FilterField label="Submitted">
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

        <AdmRequestTable
          staff
          rows={query.data?.data ?? []}
          isLoading={waitingForLookups || query.isPending}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle={assignedOnly ? 'No requests assigned to you' : 'No requests match these filters'}
          emptyDescription={
            assignedOnly
              ? 'Requests assigned to you will appear in this queue.'
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
