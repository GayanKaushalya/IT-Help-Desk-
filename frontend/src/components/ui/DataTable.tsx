import type { KeyboardEvent, ReactNode } from 'react'

import { EmptyState } from '@/components/ui/EmptyState'
import { ErrorState } from '@/components/ui/ErrorState'
import { Skeleton, SkeletonTableRows } from '@/components/ui/Skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeaderCell,
  TableRow,
} from '@/components/ui/Table'
import { cn } from '@/utils/cn'

export interface DataTableColumn<TRow> {
  /** Stable identifier, also used as the React key for the cell. */
  id: string
  header: ReactNode
  cell: (row: TRow) => ReactNode
  align?: 'left' | 'center' | 'right'
  /** Applied to both the header and body cells, e.g. `w-40` or `hidden md:table-cell`. */
  className?: string
}

export interface DataTableProps<TRow> {
  columns: DataTableColumn<TRow>[]
  rows: TRow[]
  rowKey: (row: TRow) => string | number
  isLoading?: boolean
  errorMessage?: string | null
  onRetry?: () => void
  onRowClick?: (row: TRow) => void
  emptyTitle?: string
  emptyDescription?: string
  emptyAction?: ReactNode
  /** Card layout used below the `md` breakpoint so wide tables stay usable. */
  mobileCard?: (row: TRow) => ReactNode
}

const ALIGN_CLASSES = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
} as const

function activateRow<TRow>(
  event: KeyboardEvent<HTMLTableRowElement>,
  row: TRow,
  onRowClick?: (row: TRow) => void,
) {
  if (!onRowClick) return
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onRowClick(row)
  }
}

/**
 * Renders a list of records with the loading, error and empty states the
 * design system requires, so screens never have to reimplement them.
 */
export function DataTable<TRow>({
  columns,
  rows,
  rowKey,
  isLoading = false,
  errorMessage = null,
  onRetry,
  onRowClick,
  emptyTitle = 'Nothing to show',
  emptyDescription = 'There are no records matching the current view.',
  emptyAction,
  mobileCard,
}: DataTableProps<TRow>) {
  if (errorMessage) {
    return <ErrorState description={errorMessage} {...(onRetry ? { onRetry } : {})} />
  }

  if (!isLoading && rows.length === 0) {
    return (
      <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
    )
  }

  const table = (
    <Table>
      <TableHead>
        <TableRow>
          {columns.map((column) => (
            <TableHeaderCell
              key={column.id}
              className={cn(ALIGN_CLASSES[column.align ?? 'left'], column.className)}
            >
              {column.header}
            </TableHeaderCell>
          ))}
        </TableRow>
      </TableHead>

      <TableBody>
        {isLoading ? (
          <SkeletonTableRows columns={columns.length} />
        ) : (
          rows.map((row) => (
            <TableRow
              key={rowKey(row)}
              interactive={Boolean(onRowClick)}
              tabIndex={onRowClick ? 0 : undefined}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
              onKeyDown={onRowClick ? (event) => activateRow(event, row, onRowClick) : undefined}
            >
              {columns.map((column) => (
                <TableCell
                  key={column.id}
                  className={cn(ALIGN_CLASSES[column.align ?? 'left'], column.className)}
                >
                  {column.cell(row)}
                </TableCell>
              ))}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  )

  if (!mobileCard) {
    return table
  }

  return (
    <>
      <div className="space-y-3 p-4 md:hidden">
        {isLoading
          ? Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-24 w-full rounded-xl" />
            ))
          : rows.map((row) => <div key={rowKey(row)}>{mobileCard(row)}</div>)}
      </div>
      <div className="hidden md:block">{table}</div>
    </>
  )
}
