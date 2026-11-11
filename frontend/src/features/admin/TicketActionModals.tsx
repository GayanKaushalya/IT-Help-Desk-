import { useEffect, useRef, useState } from 'react'

import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Textarea } from '@/components/ui/Textarea'
import { useLookups } from '@/features/system/useSystemStatus'
import {
  useResolveStaffTicket,
  useUpdateStaffTicketPriority,
  useUpdateStaffTicketStatus,
} from '@/features/admin/useStaffDashboard'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'

const RESOLUTION_MAX = 2000

export function ChangeStatusModal({
  ticket,
  isOpen,
  onClose,
}: {
  ticket: Ticket
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const updateStatus = useUpdateStaffTicketStatus(String(ticket.id))
  const transitions = ticket.allowed_transitions ?? []
  const [statusId, setStatusId] = useState(transitions[0]?.id ? String(transitions[0].id) : '')
  const [summary, setSummary] = useState(ticket.resolution_summary ?? '')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const wasOpen = useRef(false)

  usePauseLiveRefresh(isOpen)

  // Seed fields only when the modal opens — not on every ticket poll while open.
  useEffect(() => {
    if (isOpen && !wasOpen.current) {
      setConfirmOpen(false)
      setSummary(ticket.resolution_summary ?? '')
      const first = ticket.allowed_transitions?.[0]
      setStatusId(first?.id ? String(first.id) : '')
    }
    if (!isOpen) {
      setConfirmOpen(false)
    }
    wasOpen.current = isOpen
  }, [isOpen, ticket.allowed_transitions, ticket.resolution_summary])

  const selected = transitions.find((item) => String(item.id) === statusId)
  const resolving = selected?.slug === 'resolved'
  const needsConfirm = selected?.slug === 'cancelled'
  const canSubmit = Boolean(statusId) && (!resolving || summary.trim().length > 0)

  async function apply(): Promise<void> {
    if (!statusId) return

    try {
      const updated = await updateStatus.mutateAsync({
        statusId: Number(statusId),
        resolutionSummary: resolving ? summary.trim() : undefined,
      })
      setConfirmOpen(false)

      if (updated.awaiting_verification || resolving) {
        toast.success(
          'Ticket resolved',
          `${ticket.reference} is waiting for the employee to confirm.`,
        )
      } else {
        toast.success('Status updated', `${ticket.reference} is now ${selected?.name ?? 'updated'}.`)
      }
      onClose()
    } catch (error) {
      toast.error('Status not changed', error instanceof ApiError ? error.message : 'Please try again.')
    }
  }

  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        title="Change status"
        description="Choose the next step. Resolving asks the employee to confirm the fix."
        footer={
          <>
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button
              disabled={!canSubmit}
              isLoading={updateStatus.isPending && !confirmOpen}
              onClick={() => (needsConfirm ? setConfirmOpen(true) : void apply())}
            >
              {resolving ? 'Resolve ticket' : 'Update status'}
            </Button>
          </>
        }
      >
        {transitions.length === 0 ? (
          <p className="text-sm text-surface-500">This ticket has no further status changes.</p>
        ) : (
          <Select value={statusId} onChange={(event) => setStatusId(event.target.value)}>
            <option value="">Select the next status</option>
            {transitions.map((item) => (
              <option key={item.id} value={item.id}>
                {item.slug === 'resolved' ? 'Resolved — ask employee to confirm' : item.name}
              </option>
            ))}
          </Select>
        )}
        {resolving && (
          <div className="mt-4">
            <label className="mb-1.5 block text-xs font-medium text-surface-600">
              Resolution summary <span className="text-danger-600">*</span>
            </label>
            <Textarea
              rows={4}
              maxLength={RESOLUTION_MAX}
              value={summary}
              onChange={(event) => setSummary(event.target.value)}
              placeholder="What was done to resolve this request?"
            />
            <p className="mt-2 text-xs text-surface-500">
              {summary.trim().length} / {RESOLUTION_MAX}
            </p>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => void apply()}
        title={`Move to ${selected?.name ?? 'this status'}?`}
        description="This change is recorded in the ticket history and cannot be reversed freely."
        confirmLabel="Confirm change"
        tone={selected?.slug === 'cancelled' ? 'danger' : 'primary'}
        isConfirming={updateStatus.isPending}
      />
    </>
  )
}

export function ChangePriorityModal({
  ticket,
  isOpen,
  onClose,
}: {
  ticket: Ticket
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const { data: lookups } = useLookups()
  const updatePriority = useUpdateStaffTicketPriority(String(ticket.id))
  const [priorityId, setPriorityId] = useState(ticket.priority ? String(ticket.priority.id) : '')
  const wasOpen = useRef(false)

  usePauseLiveRefresh(isOpen)

  useEffect(() => {
    if (isOpen && !wasOpen.current) {
      setPriorityId(ticket.priority ? String(ticket.priority.id) : '')
    }
    wasOpen.current = isOpen
  }, [isOpen, ticket.priority])

  async function apply(): Promise<void> {
    if (!priorityId) return

    try {
      await updatePriority.mutateAsync(Number(priorityId))
      toast.success('Priority updated', `${ticket.reference} priority has been changed.`)
      onClose()
    } catch (error) {
      toast.error('Priority not changed', error instanceof ApiError ? error.message : 'Please try again.')
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Change priority"
      description="Use this when the impact or urgency has changed."
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button isLoading={updatePriority.isPending} disabled={!priorityId} onClick={() => void apply()}>
            Update priority
          </Button>
        </>
      }
    >
      <Select value={priorityId} onChange={(event) => setPriorityId(event.target.value)}>
        <option value="">Select priority</option>
        {lookups?.priorities.map((priority) => (
          <option key={priority.id} value={priority.id}>
            {priority.name}
          </option>
        ))}
      </Select>
    </Modal>
  )
}

export function ResolveTicketModal({
  ticket,
  isOpen,
  onClose,
}: {
  ticket: Ticket
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const resolve = useResolveStaffTicket(String(ticket.id))
  const [summary, setSummary] = useState(ticket.resolution_summary ?? '')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const wasOpen = useRef(false)

  usePauseLiveRefresh(isOpen)

  useEffect(() => {
    if (isOpen && !wasOpen.current) {
      setConfirmOpen(false)
      setSummary(ticket.resolution_summary ?? '')
    }
    if (!isOpen) {
      setConfirmOpen(false)
    }
    wasOpen.current = isOpen
  }, [isOpen, ticket.resolution_summary])

  async function apply(): Promise<void> {
    const trimmed = summary.trim()
    if (!trimmed) {
      toast.error('Resolution required', 'Describe how the issue was resolved.')
      return
    }

    try {
      await resolve.mutateAsync(trimmed)
      setConfirmOpen(false)
      toast.success(
        'Ticket resolved',
        `${ticket.reference} is waiting for the employee to confirm.`,
      )
      onClose()
    } catch (error) {
      toast.error('Ticket not resolved', error instanceof ApiError ? error.message : 'Please try again.')
    }
  }

  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        title="Resolve ticket"
        description="Describe the work completed. If the ticket is still with the whole IT desk, resolving assigns it to you. The employee will confirm or reject the fix in the app."
        footer={
          <>
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button
              variant="success"
              disabled={summary.trim().length === 0}
              onClick={() => setConfirmOpen(true)}
            >
              Resolve
            </Button>
          </>
        }
      >
        <label className="mb-1.5 block text-xs font-medium text-surface-600">
          Resolution summary <span className="text-danger-600">*</span>
        </label>
        <Textarea
          rows={5}
          maxLength={RESOLUTION_MAX}
          value={summary}
          onChange={(event) => setSummary(event.target.value)}
          placeholder="What was done to resolve this request?"
        />
        <p className="mt-2 text-xs text-surface-500">
          {summary.trim().length} / {RESOLUTION_MAX}
        </p>
      </Modal>

      <ConfirmDialog
        isOpen={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => void apply()}
        title="Mark this ticket as resolved?"
        description="The employee will be asked to confirm that the issue is fixed."
        confirmLabel="Resolve ticket"
        isConfirming={resolve.isPending}
      />
    </>
  )
}
