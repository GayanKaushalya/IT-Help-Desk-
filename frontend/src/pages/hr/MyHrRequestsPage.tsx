import { Plus, Search } from 'lucide-react'
import { useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { HrRequestTable } from '@/features/hr/HrRequestTable'
import { useHrLookups, useHrRequestList } from '@/features/hr/useHr'
import { useAuth } from '@/hooks/useAuth'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'

export function MyHrRequestsPage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useHrLookups()

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status_id: params.get('status_id') ?? '',
      category_id: params.get('category_id') ?? '',
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 10,
    }),
    [params],
  )

  const { data, isPending, isError, error, refetch } = useHrRequestList(filters)

  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) => updateFilter('search', value))

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) {
      next.set(key, value)
    } else {
      next.delete(key)
    }
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'Your HR requests could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="My HR Requests"
        description="Every HR request you have raised."
        actions={
          hasPermission('hr.requests.create') ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => void navigate(paths.hr.submit)}>
              Submit Request
            </Button>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar className="xl:grid-cols-4">
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Reference number"
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
          rows={data?.data ?? []}
          isLoading={isPending}
          errorMessage={errorMessage}
          onRetry={() => void refetch()}
          emptyTitle="No HR requests found"
          emptyDescription="Requests you submit to the HR team will appear here."
        />

        {data?.meta && <Pagination meta={data.meta} onPageChange={(page) => updateFilter('page', String(page))} />}
      </Card>
    </div>
  )
}
