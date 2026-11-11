import { Plus, Search } from 'lucide-react'
import { useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { Combobox } from '@/components/ui/Combobox'
import { DateRangeField } from '@/components/ui/DateRangeField'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { useLookups } from '@/features/system/useSystemStatus'
import { assigneeLabel } from '@/features/tickets/assigneeLabel'
import { TicketMobileCard } from '@/features/tickets/TicketMobileCard'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { useTicketList } from '@/features/tickets/useTickets'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'
import { formatDate, formatDateTime } from '@/utils/format'

const TICKET_COLUMNS: DataTableColumn<Ticket>[] = [
  {
    id: 'reference',
    header: 'Ticket Number',
    cell: (ticket) => (
      <span className="font-mono text-xs font-medium text-primary-700">{ticket.reference}</span>
    ),
  },
  {
    id: 'subject',
    header: 'Subject',
    cell: (ticket) => <span className="font-medium text-surface-900">{ticket.subject}</span>,
    className: 'min-w-[14rem]',
  },
  {
    id: 'category',
    header: 'Category',
    cell: (ticket) => ticket.category?.name ?? '—',
    className: 'hidden lg:table-cell',
  },
  {
    id: 'priority',
    header: 'Priority',
    cell: (ticket) => <TicketPriorityBadge priority={ticket.priority} />,
    className: 'hidden md:table-cell',
  },
  {
    id: 'status',
    header: 'Status',
    cell: (ticket) => <TicketStatusBadge status={ticket.status} />,
  },
  {
    id: 'assignee',
    header: 'Assigned IT Staff',
    cell: (ticket) => assigneeLabel(ticket),
    className: 'hidden lg:table-cell',
  },
  {
    id: 'created_at',
    header: 'Created Date',
    cell: (ticket) => formatDate(ticket.created_at),
    className: 'hidden xl:table-cell',
  },
  {
    id: 'updated_at',
    header: 'Last Updated',
    cell: (ticket) => formatDateTime(ticket.updated_at),
    className: 'hidden sm:table-cell',
  },
]

export function MyTicketsPage() {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const { data: lookups } = useLookups()

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      status_id: params.get('status_id') ?? '',
      priority_id: params.get('priority_id') ?? '',
      category_id: params.get('category_id') ?? '',
      date_from: params.get('date_from') ?? '',
      date_to: params.get('date_to') ?? '',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 10,
    }),
    [params],
  )

  const { data, isPending, isError, error, refetch } = useTicketList(filters)

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
    if (key !== 'page') {
      next.delete('page')
    }
    setParams(next)
  }

  const rows = data?.data ?? []
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'Your tickets could not be loaded.'
    : null

  return (
    <div className="space-y-6">
      <PageHeader
        title="My Tickets"
        description="Every IT support request you have raised."
        actions={
          <Button leadingIcon={<Plus className="size-4" />} onClick={() => void navigate(paths.tickets.create)}>
            Create Ticket
          </Button>
        }
      />

      <Card>
        <FilterToolbar className="xl:grid-cols-5">
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
                options={lookups?.statuses.map((status) => ({ value: String(status.id), label: status.name })) ?? []}
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

        <DataTable
          columns={TICKET_COLUMNS}
          rows={rows}
          rowKey={(ticket) => ticket.id}
          isLoading={isPending}
          errorMessage={errorMessage}
          onRetry={() => void refetch()}
          onRowClick={(ticket) => void navigate(paths.tickets.detail(ticket.id))}
          emptyTitle="No tickets match these filters"
          emptyDescription="Try clearing a filter, or raise a new request with IT support."
          emptyAction={
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => void navigate(paths.tickets.create)}>
              Create Ticket
            </Button>
          }
          mobileCard={(ticket) => (
            <TicketMobileCard ticket={ticket} onOpen={() => void navigate(paths.tickets.detail(ticket.id))} />
          )}
        />

        {data?.meta && <Pagination meta={data.meta} onPageChange={(page) => updateFilter('page', String(page))} />}
      </Card>
    </div>
  )
}
