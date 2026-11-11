import { Bell } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotificationFeed,
} from '@/features/notifications/useNotifications'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import type { NotificationDesk } from '@/services/notificationService'
import type { AppNotification } from '@/types/models'
import { DESK_ROLES, HR_DESK_ROLES } from '@/utils/roles'
import { formatRelativeTime } from '@/utils/format'

export interface NotificationBellProps {
  /** Only show notifications for this help desk. */
  desk: NotificationDesk
}

export function NotificationBell({ desk }: NotificationBellProps) {
  const { hasRole } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const panelRef = useRef<HTMLDivElement>(null)
  const feed = useNotificationFeed(true, { desk })
  const markRead = useMarkNotificationRead(desk)
  const markAll = useMarkAllNotificationsRead(desk)

  const unread = feed.data?.unread_count ?? 0
  const items = feed.data?.notifications ?? []
  const isDesk = hasRole(...DESK_ROLES)
  const isHrDesk = hasRole(...HR_DESK_ROLES)
  const label = desk === 'hr' ? 'HR notifications' : 'IT notifications'

  const emptyCopy = useMemo(
    () => (desk === 'hr' ? 'No HR notifications yet.' : 'No IT notifications yet.'),
    [desk],
  )

  useEffect(() => {
    if (!open) return

    function handlePointer(event: MouseEvent) {
      if (!panelRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointer)
    return () => document.removeEventListener('mousedown', handlePointer)
  }, [open])

  function hrefFor(notification: AppNotification): string | null {
    const hrRequestId = notification.data?.hr_request_id
    if (notification.type.startsWith('hr.') && hrRequestId) {
      return isHrDesk ? paths.hr.admin.requestDetail(hrRequestId) : paths.hr.detail(hrRequestId)
    }

    const ticketId = notification.data?.ticket_id
    if (!ticketId) return null

    return isDesk ? paths.admin.ticketDetail(ticketId) : paths.tickets.detail(ticketId)
  }

  function openNotification(notification: AppNotification) {
    if (!notification.is_read) {
      markRead.mutate(notification.id)
    }

    const hrRequestId = notification.data?.hr_request_id
    if (hrRequestId) {
      void queryClient.invalidateQueries({ queryKey: ['hr'] })
    }

    const ticketId = notification.data?.ticket_id
    if (ticketId) {
      void queryClient.invalidateQueries({ queryKey: ['tickets', String(ticketId)] })
      void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets', String(ticketId)] })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void queryClient.invalidateQueries({ queryKey: ['tickets'] })
      void queryClient.invalidateQueries({ queryKey: ['staff', 'tickets'] })
      void queryClient.invalidateQueries({ queryKey: ['staff', 'dashboard'] })
    }

    const href = hrefFor(notification)
    setOpen(false)
    if (href) {
      void navigate(href)
    }
  }

  return (
    <div ref={panelRef} className="relative">
      <button
        type="button"
        aria-label={unread > 0 ? `${label}, ${unread} unread` : label}
        aria-expanded={open}
        onClick={() => {
          setOpen((current) => {
            const next = !current
            if (next) {
              void feed.refetch()
            }
            return next
          })
        }}
        className="relative rounded-md p-2 text-surface-500 transition-colors hover:bg-surface-100 hover:text-surface-900"
      >
        <Bell className="size-5" />
        {unread > 0 && (
          <span className="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold text-white">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute top-full right-0 z-30 mt-2 w-80 animate-scale-in overflow-hidden rounded-lg border border-surface-200 bg-panel shadow-overlay">
          <div className="flex items-center justify-between border-b border-surface-200 px-4 py-3">
            <div>
              <p className="text-sm font-semibold text-surface-900">{label}</p>
              <p className="text-xs text-surface-500">
                {unread > 0 ? `${unread} unread` : 'You are up to date'}
              </p>
            </div>
            {unread > 0 && (
              <Button size="sm" variant="ghost" onClick={() => markAll.mutate()} isLoading={markAll.isPending}>
                Mark all as read
              </Button>
            )}
          </div>

          <div className="max-h-96 overflow-y-auto">
            {items.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-surface-500">{emptyCopy}</p>
            ) : (
              items.map((notification) => (
                <button
                  key={notification.id}
                  type="button"
                  onClick={() => openNotification(notification)}
                  className={`block w-full px-4 py-3 text-left transition-colors hover:bg-surface-50 ${
                    notification.is_read ? 'bg-panel' : 'bg-primary-50/60'
                  }`}
                >
                  <p className="text-sm font-medium text-surface-900">{notification.title}</p>
                  {notification.body && <p className="mt-0.5 text-xs text-surface-500">{notification.body}</p>}
                  <p className="mt-1 text-[11px] text-surface-400">{formatRelativeTime(notification.created_at)}</p>
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  )
}
