import { ArrowRight, CircleDot, Flag } from 'lucide-react'

import type { AdmStatusHistoryEntry } from '@/types/models'
import { cn } from '@/utils/cn'
import { formatDateTime } from '@/utils/format'

export function AdmTimeline({ events }: { events: AdmStatusHistoryEntry[] }) {
  if (events.length === 0) {
    return <p className="text-sm text-surface-500">No history has been recorded yet.</p>
  }

  return (
    <ol>
      {events.map((event, index) => {
        const isLast = index === events.length - 1
        const Icon = event.from_status ? ArrowRight : Flag
        const title = event.from_status
          ? `${event.from_status.name} \u2192 ${event.to_status?.name ?? 'Updated'}`
          : (event.to_status?.name ?? 'Request created')

        return (
          <li key={event.id} className="flex gap-4">
            <div className="flex flex-col items-center">
              <span
                className={cn(
                  'flex size-8 items-center justify-center rounded-full border',
                  isLast
                    ? 'border-primary-200 bg-primary-50 text-primary-500'
                    : 'border-surface-200 bg-panel text-surface-500',
                )}
              >
                {event.to_status ? <Icon className="size-3.5" aria-hidden /> : <CircleDot className="size-3.5" aria-hidden />}
              </span>
              {!isLast && <span className="min-h-8 w-px flex-1 bg-surface-200" aria-hidden />}
            </div>
            <div className={cn('min-w-0 pb-6', isLast && 'pb-0')}>
              <p className="text-sm font-medium text-surface-900">{title}</p>
              {event.remarks && <p className="mt-0.5 text-xs text-surface-500">{event.remarks}</p>}
              <p className="mt-1 text-xs text-surface-400">
                {formatDateTime(event.changed_at)}
                {event.changed_by ? ` \u00b7 ${event.changed_by.full_name}` : ''}
              </p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
