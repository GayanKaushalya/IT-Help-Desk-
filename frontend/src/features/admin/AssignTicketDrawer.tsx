import { useEffect, useRef, useState } from 'react'

import { Button } from '@/components/ui/Button'
import { Drawer } from '@/components/ui/Drawer'
import { Textarea } from '@/components/ui/Textarea'
import { useAssignStaffTicket, useStaffAgents } from '@/features/admin/useStaffDashboard'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'
import { cn } from '@/utils/cn'

export function AssignTicketDrawer({
  ticket,
  isOpen,
  onClose,
}: {
  ticket: Ticket
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const { data: agents = [], isPending } = useStaffAgents()
  const assign = useAssignStaffTicket(String(ticket.id))
  const [selected, setSelected] = useState<number | null>(ticket.assignee?.id ?? null)
  const [note, setNote] = useState('')
  const wasOpen = useRef(false)

  usePauseLiveRefresh(isOpen || note.trim().length > 0)

  useEffect(() => {
    if (isOpen && !wasOpen.current) {
      setSelected(ticket.assignee?.id ?? null)
      setNote('')
    }
    wasOpen.current = isOpen
  }, [isOpen, ticket.assignee?.id])

  async function handleAssign(): Promise<void> {
    if (!selected) return

    try {
      await assign.mutateAsync({ assignedTo: selected, note: note.trim() || undefined })
      toast.success('Ticket assigned', `${ticket.reference} has been assigned.`)
      onClose()
    } catch (error) {
      toast.error('Assignment failed', error instanceof ApiError ? error.message : 'Please try again.')
    }
  }

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      title="Assign ticket"
      description="Choose an IT staff member. Workload is shown to help balance the queue."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={assign.isPending}>
            Cancel
          </Button>
          <Button onClick={() => void handleAssign()} isLoading={assign.isPending} disabled={!selected}>
            Assign
          </Button>
        </>
      }
    >
      {isPending ? (
        <p className="text-sm text-surface-500">Loading IT staff…</p>
      ) : (
        <ul className="space-y-2">
          {agents.map((agent) => {
            const isSelected = selected === agent.id

            return (
              <li key={agent.id}>
                <button
                  type="button"
                  onClick={() => setSelected(agent.id)}
                  className={cn(
                    'w-full rounded-xl border px-4 py-3 text-left transition-colors',
                    isSelected
                      ? 'border-primary-400 bg-primary-50'
                      : 'border-surface-200 bg-panel hover:border-primary-200 hover:bg-surface-50',
                  )}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-surface-900">{agent.full_name}</p>
                      <p className="text-xs text-surface-500">{agent.role ?? 'IT staff'}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-surface-900">{agent.open_tickets}</p>
                      <p className="text-[11px] text-surface-500">open</p>
                    </div>
                  </div>
                  <p className="mt-1 text-xs text-surface-400">{agent.total_assigned} assigned in total</p>
                </button>
              </li>
            )
          })}
        </ul>
      )}

      <div className="mt-5">
        <label className="mb-1.5 block text-xs font-medium text-surface-600">Assignment note</label>
        <Textarea
          rows={3}
          value={note}
          onChange={(event) => setNote(event.target.value)}
          placeholder="Optional context for the assignee"
        />
      </div>
    </Drawer>
  )
}
