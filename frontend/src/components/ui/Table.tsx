import type { HTMLAttributes, ThHTMLAttributes, TdHTMLAttributes } from 'react'

import { cn } from '@/utils/cn'

/**
 * Low-level table primitives for screens that need a bespoke layout.
 * For the common list-of-records case, prefer DataTable.
 */

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="w-full overflow-x-auto">
      <table className={cn('w-full border-collapse text-left text-sm', className)} {...props} />
    </div>
  )
}

export function TableHead({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return <thead className={cn('bg-surface-50', className)} {...props} />
}

export function TableBody({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return <tbody className={cn('divide-y divide-surface-100', className)} {...props} />
}

export interface TableRowProps extends HTMLAttributes<HTMLTableRowElement> {
  interactive?: boolean
}

export function TableRow({ interactive = false, className, ...props }: TableRowProps) {
  return (
    <tr
      className={cn(
        'transition-colors duration-100',
        interactive && 'cursor-pointer hover:bg-surface-50 focus-visible:bg-primary-50',
        className,
      )}
      {...props}
    />
  )
}

export function TableHeaderCell({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      scope="col"
      className={cn(
        'border-b border-surface-200 px-4 py-3 sm:px-6',
        'text-xs font-semibold tracking-wide text-surface-500 uppercase',
        className,
      )}
      {...props}
    />
  )
}

export function TableCell({ className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return <td className={cn('px-4 py-3.5 text-surface-700 sm:px-6 sm:py-4', className)} {...props} />
}
