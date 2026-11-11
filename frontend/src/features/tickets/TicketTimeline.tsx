import { CheckCircle2, CircleDot, Clock3, Flag, MessageCircle, ShieldCheck, UserPlus } from 'lucide-react'
import type { ComponentType } from 'react'

import type { TicketTimelineEvent } from '@/types/models'
import { formatDateTime } from '@/utils/format'
import { cn } from '@/utils/cn'

const ICONS: Record<string, ComponentType<{ className?: string }>> = {
  created: Flag,
  assigned: UserPlus,
  in_progress: Clock3,
  awaiting_user: MessageCircle,
  resolved: CheckCircle2,
  awaiting_verification: ShieldCheck,
  verified: ShieldCheck,
  closed: CheckCircle2,
  cancelled: CircleDot,
}

interface TicketTimelineProps {
  events: TicketTimelineEvent[]
}

export function TicketTimeline({ events }: TicketTimelineProps) {
  if (events.length === 0) {
    return <p className="text-sm text-surface-500">No history has been recorded yet.</p>
  }

  return (
    <ol className="space-y-0">
      {events.map((event, index) => {
        const Icon = ICONS[event.type] ?? CircleDot
        const isLast = index === events.length - 1

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
                <Icon className="size-3.5" aria-hidden />
              </span>
              {!isLast && <span className="min-h-8 w-px flex-1 bg-surface-200" aria-hidden />}
            </div>
            <div className={cn('min-w-0 pb-6', isLast && 'pb-0')}>
              <p className="text-sm font-medium text-surface-900">{event.title}</p>
              {event.description && <p className="mt-0.5 text-xs text-surface-500">{event.description}</p>}
              <p className="mt-1 text-xs text-surface-400">{formatDateTime(event.occurred_at)}</p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
