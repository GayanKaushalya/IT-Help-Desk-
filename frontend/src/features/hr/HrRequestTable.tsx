import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { DataTable, type DataTableColumn } from '@/components/ui/DataTable'
import { hrAssigneeLabel } from '@/features/hr/hrAssigneeLabel'
import { HrRequestFlags, HrStatusBadge } from '@/features/hr/HrStatusBadge'
import { paths } from '@/routes/paths'
import type { HrRequest } from '@/types/models'
import { formatDate, formatDateTime } from '@/utils/format'

export interface HrRequestTableProps {
  rows: HrRequest[]
  isLoading: boolean
  errorMessage: string | null
  onRetry: () => void
  emptyTitle: string
  emptyDescription: string
  /** Staff view adds employee and assignee columns and links to the workspace. */
  staff?: boolean
}

function buildColumns(staff: boolean, onOpen: (request: HrRequest) => void): DataTableColumn<HrRequest>[] {
  const columns: DataTableColumn<HrRequest>[] = [
    {
      id: 'reference',
      header: 'Reference',
      cell: (request) => (
        <div className="space-y-1">
          <span className="font-mono text-xs font-medium text-primary-700">{request.reference}</span>
          <div>
            <HrRequestFlags request={request} />
          </div>
        </div>
      ),
    },
    {
      id: 'type',
      header: 'Request',
      cell: (request) => (
        <div className="min-w-0">
          <p className="font-medium text-surface-900">{request.request_type?.name ?? '\u2014'}</p>
          <p className="text-xs text-surface-500">{request.category?.name ?? ''}</p>
        </div>
      ),
      className: 'min-w-[12rem]',
    },
  ]

  if (staff) {
    columns.push({
      id: 'employee',
      header: 'Employee',
      cell: (request) => (
        <div className="min-w-0">
          <p className="text-sm text-surface-900">{request.employee_name ?? request.requester?.full_name ?? '\u2014'}</p>
          <p className="text-xs text-surface-500">{request.employee_id ?? ''}</p>
        </div>
      ),
      className: 'hidden md:table-cell',
    })
  }

  columns.push({
    id: 'status',
    header: 'Status',
    cell: (request) => <HrStatusBadge status={request.status} />,
  })

  if (staff) {
    columns.push({
      id: 'assignee',
      header: 'Assigned to',
      cell: (request) => hrAssigneeLabel(request),
      className: 'hidden lg:table-cell',
    })
  }

  columns.push(
    {
      id: 'created',
      header: 'Submitted',
      cell: (request) => formatDateTime(request.created_at),
      className: 'hidden lg:table-cell',
    },
    {
      id: 'due',
      header: 'Due',
      cell: (request) => formatDate(request.due_at),
      className: 'hidden xl:table-cell',
    },
    {
      id: 'actions',
      header: 'Actions',
      align: 'right',
      cell: (request) => (
        <Button
          size="sm"
          variant="outline"
          onClick={(event) => {
            event.stopPropagation()
            onOpen(request)
          }}
        >
          Open
        </Button>
      ),
    },
  )

  return columns
}

export function HrRequestTable({
  rows,
  isLoading,
  errorMessage,
  onRetry,
  emptyTitle,
  emptyDescription,
  staff = false,
}: HrRequestTableProps) {
  const navigate = useNavigate()
  const open = (request: HrRequest) =>
    void navigate(staff ? paths.hr.admin.requestDetail(request.id) : paths.hr.detail(request.id))

  return (
    <DataTable
      columns={buildColumns(staff, open)}
      rows={rows}
      rowKey={(request) => request.id}
      isLoading={isLoading}
      errorMessage={errorMessage}
      onRetry={onRetry}
      onRowClick={open}
      emptyTitle={emptyTitle}
      emptyDescription={emptyDescription}
      mobileCard={(request) => (
        <button
          type="button"
          onClick={() => open(request)}
          className="block w-full rounded-xl border border-surface-200 bg-panel p-4 text-left shadow-card transition-shadow hover:shadow-card-hover"
        >
          <div className="flex items-start justify-between gap-3">
            <span className="font-mono text-xs font-medium text-primary-700">{request.reference}</span>
            <HrStatusBadge status={request.status} />
          </div>
          <p className="mt-2 text-sm font-medium text-surface-900">{request.request_type?.name ?? '\u2014'}</p>
          <p className="text-xs text-surface-500">{request.category?.name ?? ''}</p>
          {staff && (
            <p className="mt-1 text-xs text-surface-500">
              {request.employee_name ?? request.requester?.full_name ?? ''}
              {request.assignee ? ` \u00b7 ${request.assignee.full_name}` : ' \u00b7 Unassigned'}
            </p>
          )}
          <div className="mt-2 flex items-center justify-between gap-2">
            <HrRequestFlags request={request} />
            <span className="text-xs text-surface-400">{formatDate(request.created_at)}</span>
          </div>
        </button>
      )}
    />
  )
}
