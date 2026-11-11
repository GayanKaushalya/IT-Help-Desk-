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
import { HrRequestTable } from '@/features/hr/HrRequestTable'
import { useHrLookups } from '@/features/hr/useHr'
import { useHrStaffAgents, useHrStaffAssigned, useHrStaffQueue } from '@/features/hr/useHrStaff'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { ApiError } from '@/types/api'

export interface HrAdminRequestsPageProps {
  /** Only requests assigned to the signed-in user. */
  assignedOnly?: boolean
  /** Only confidential complaints. */
  complaintsOnly?: boolean
  /** Only requests currently waiting on another department. */
  referralsOnly?: boolean
}

const REFERRAL_STATUS_SLUG = 'awaiting-other-department'

const TITLES = {
  all: ['HR Requests', 'Organisation-wide HR service desk queue.'],
  assigned: ['My Assigned Requests', 'HR requests currently assigned to you.'],
  complaints: ['Complaints', 'Confidential employee complaints you are cleared to see.'],
  referrals: ['Referrals', 'Requests waiting on Finance, IT or another department.'],
} as const

export function HrAdminRequestsPage({
  assignedOnly = false,
  complaintsOnly = false,
  referralsOnly = false,
}: HrAdminRequestsPageProps) {
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useHrLookups()
  const { data: agents } = useHrStaffAgents()

  const referralStatusId = lookups?.statuses.find((status) => status.slug === REFERRAL_STATUS_SLUG)?.id
  const mode = assignedOnly ? 'assigned' : complaintsOnly ? 'complaints' : referralsOnly ? 'referrals' : 'all'
  const [title, description] = TITLES[mode]

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status_id: referralsOnly
        ? referralStatusId
          ? String(referralStatusId)
          : ''
        : (params.get('status_id') ?? ''),
      category_id: params.get('category_id') ?? '',
      assigned_to: params.get('assigned_to') ?? '',
      unassigned: params.get('unassigned') === '1',
      overdue: params.get('overdue') === '1',
      confidential: complaintsOnly,
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 15,
    }),
    [params, complaintsOnly, referralsOnly, referralStatusId],
  )

  // The referrals view needs the "awaiting other department" status id first.
  const referralsReady = !referralsOnly || referralStatusId !== undefined
  const waitingForLookups = referralsOnly && !lookups
  // Lookups loaded but the referral status is not configured: nothing to list.
  const referralsUnavailable = referralsOnly && Boolean(lookups) && referralStatusId === undefined

  const queue = useHrStaffQueue(filters, !assignedOnly && referralsReady)
  const mine = useHrStaffAssigned(filters, assignedOnly)
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
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const rows = referralsUnavailable ? [] : (query.data?.data ?? [])
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
                placeholder="Reference or employee"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>

          {!referralsOnly && (
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
          )}

          {!complaintsOnly && (
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
          )}

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
                    ...(agents?.map((agent) => ({ value: String(agent.id), label: agent.full_name })) ?? []),
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

        <HrRequestTable
          staff
          rows={rows}
          isLoading={waitingForLookups || (!referralsUnavailable && query.isPending)}
          errorMessage={errorMessage}
          onRetry={() => void query.refetch()}
          emptyTitle={
            assignedOnly
              ? 'No requests assigned to you'
              : complaintsOnly
                ? 'No complaints to show'
                : referralsOnly
                  ? 'No requests are waiting on another department'
                  : 'No requests match these filters'
          }
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
