import { useEffect, useRef, useState } from 'react'

import { Button } from '@/components/ui/Button'
import { Drawer } from '@/components/ui/Drawer'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import { Textarea } from '@/components/ui/Textarea'
import { useHrLookups } from '@/features/hr/useHr'
import {
  useAssignHrRequest,
  useHrStaffAgents,
  useReferHrRequest,
  useResolveHrRequest,
  useRespondToHrReferral,
  useUpdateHrRequestStatus,
} from '@/features/hr/useHrStaff'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { HrReferral, HrRequest } from '@/types/models'
import { cn } from '@/utils/cn'

const TEXT_MAX = 2000

interface ModalBaseProps {
  request: HrRequest
  isOpen: boolean
  onClose: () => void
}

/** Runs `reset` each time the modal transitions from closed to open. */
function useResetOnOpen(isOpen: boolean, reset: () => void): void {
  const wasOpen = useRef(false)
  const resetRef = useRef(reset)

  useEffect(() => {
    resetRef.current = reset
  })

  useEffect(() => {
    if (isOpen && !wasOpen.current) resetRef.current()
    wasOpen.current = isOpen
  }, [isOpen])
}

function errorText(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Please try again.'
}

/* ------------------------------------------------------------------ */

export function AssignHrRequestModal({ request, isOpen, onClose }: ModalBaseProps) {
  const toast = useToast()
  const { data: agents = [], isPending } = useHrStaffAgents()
  const assign = useAssignHrRequest(String(request.id))
  const [selected, setSelected] = useState<number | null>(request.assignee?.id ?? null)
  const [note, setNote] = useState('')

  usePauseLiveRefresh(isOpen || note.trim().length > 0)
  useResetOnOpen(isOpen, () => {
    setSelected(request.assignee?.id ?? null)
    setNote('')
  })

  async function submit(): Promise<void> {
    if (!selected) return

    try {
      await assign.mutateAsync({ assignedTo: selected, note: note.trim() || undefined })
      toast.success('Request assigned', `${request.reference} has been assigned.`)
      onClose()
    } catch (error) {
      toast.error('Assignment failed', errorText(error))
    }
  }

  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      title="Assign request"
      description="Choose an HR staff member. Workload is shown to help balance the queue."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={assign.isPending}>
            Cancel
          </Button>
          <Button disabled={!selected} isLoading={assign.isPending} onClick={() => void submit()}>
            Assign
          </Button>
        </>
      }
    >
      {isPending ? (
        <p className="text-sm text-surface-500">Loading HR staff…</p>
      ) : agents.length === 0 ? (
        <p className="text-sm text-surface-500">No assignable HR staff are available.</p>
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
                      <p className="text-xs text-surface-500">{agent.role ?? 'HR staff'}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-surface-900">{agent.open_requests}</p>
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
        <label htmlFor="hr-assign-note" className="mb-1.5 block text-xs font-medium text-surface-600">
          Assignment note
        </label>
        <Textarea
          id="hr-assign-note"
          rows={3}
          maxLength={255}
          value={note}
          onChange={(event) => setNote(event.target.value)}
          placeholder="Optional context for the assignee"
        />
      </div>
    </Drawer>
  )
}

/* ------------------------------------------------------------------ */

const FINISHING_SLUGS = ['resolved', 'closed', 'rejected']

export function ChangeHrStatusModal({ request, isOpen, onClose }: ModalBaseProps) {
  const toast = useToast()
  const { hasPermission } = useAuth()
  const { data: lookups } = useHrLookups()
  const update = useUpdateHrRequestStatus(String(request.id))
  const [statusId, setStatusId] = useState('')
  const [remarks, setRemarks] = useState('')

  usePauseLiveRefresh(isOpen)
  useResetOnOpen(isOpen, () => {
    setStatusId('')
    setRemarks('')
  })

  const canResolve = hasPermission('hr.requests.resolve')
  // Resolved/closed go through Resolve + employee confirmation, like IT.
  const options = (lookups?.statuses ?? []).filter(
    (status) =>
      status.id !== request.status?.id &&
      status.slug !== 'resolved' &&
      status.slug !== 'closed' &&
      (canResolve || !FINISHING_SLUGS.includes(status.slug)),
  )

  async function submit(): Promise<void> {
    if (!statusId) return

    try {
      const updated = await update.mutateAsync({ statusId: Number(statusId), remarks: remarks.trim() || undefined })
      toast.success('Status updated', `${request.reference} is now ${updated.status?.name ?? 'updated'}.`)
      onClose()
    } catch (error) {
      toast.error('Status not changed', errorText(error))
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Change status"
      description="Move the request to its next workflow step. Remarks are visible to HR staff only."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={update.isPending}>
            Cancel
          </Button>
          <Button disabled={!statusId} isLoading={update.isPending} onClick={() => void submit()}>
            Update status
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div>
          <label htmlFor="hr-status-select" className="mb-1.5 block text-xs font-medium text-surface-600">
            New status <span className="text-danger-600">*</span>
          </label>
          <Select id="hr-status-select" value={statusId} onChange={(event) => setStatusId(event.target.value)}>
            <option value="">Select the next status</option>
            {options.map((status) => (
              <option key={status.id} value={status.id}>
                {status.name}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <label htmlFor="hr-status-remarks" className="mb-1.5 block text-xs font-medium text-surface-600">
            Remarks (optional)
          </label>
          <Textarea
            id="hr-status-remarks"
            rows={3}
            maxLength={TEXT_MAX}
            value={remarks}
            onChange={(event) => setRemarks(event.target.value)}
            placeholder="Why is the status changing?"
          />
        </div>
      </div>
    </Modal>
  )
}

/* ------------------------------------------------------------------ */

export function ResolveHrRequestModal({ request, isOpen, onClose }: ModalBaseProps) {
  const toast = useToast()
  const resolve = useResolveHrRequest(String(request.id))
  const [summary, setSummary] = useState('')

  usePauseLiveRefresh(isOpen)
  useResetOnOpen(isOpen, () => {
    setSummary('')
  })

  const valid = summary.trim().length >= 5

  async function submit(): Promise<void> {
    if (!valid) return

    try {
      await resolve.mutateAsync({ summary: summary.trim(), close: false })
      toast.success(
        'Request resolved',
        `${request.reference} is waiting for the employee to confirm.`,
      )
      onClose()
    } catch (error) {
      toast.error('Could not resolve request', errorText(error))
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Resolve request"
      description="Describe the work completed. If the request is still with the whole HR desk, resolving assigns it to you. The employee will confirm or reject the fix in the app."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={resolve.isPending}>
            Cancel
          </Button>
          <Button variant="success" disabled={!valid} isLoading={resolve.isPending} onClick={() => void submit()}>
            Resolve request
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div>
          <label htmlFor="hr-resolve-summary" className="mb-1.5 block text-xs font-medium text-surface-600">
            Resolution summary <span className="text-danger-600">*</span>
          </label>
          <Textarea
            id="hr-resolve-summary"
            rows={4}
            maxLength={TEXT_MAX}
            value={summary}
            onChange={(event) => setSummary(event.target.value)}
            placeholder="What was done to resolve this request? (at least 5 characters)"
          />
          <p className="mt-2 text-xs text-surface-500">
            {summary.trim().length} / {TEXT_MAX}
          </p>
        </div>
      </div>
    </Modal>
  )
}

/* ------------------------------------------------------------------ */

export function ReferHrRequestModal({ request, isOpen, onClose }: ModalBaseProps) {
  const toast = useToast()
  const { data: lookups } = useHrLookups()
  const refer = useReferHrRequest(String(request.id))
  const [department, setDepartment] = useState('')
  const [brief, setBrief] = useState('')

  usePauseLiveRefresh(isOpen)
  useResetOnOpen(isOpen, () => {
    setDepartment(request.request_type?.default_referral_department ?? '')
    setBrief('')
  })

  const departments = lookups?.referral_departments ?? []

  async function submit(): Promise<void> {
    if (!department) return

    try {
      await refer.mutateAsync({ department, brief: brief.trim() || undefined })
      toast.success('Request referred', `${request.reference} was sent to ${department}.`)
      onClose()
    } catch (error) {
      toast.error('Referral failed', errorText(error))
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Refer to another department"
      description="The request moves to Awaiting Other Department until they respond."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={refer.isPending}>
            Cancel
          </Button>
          <Button disabled={!department} isLoading={refer.isPending} onClick={() => void submit()}>
            Send referral
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div>
          <label htmlFor="hr-refer-dept" className="mb-1.5 block text-xs font-medium text-surface-600">
            Department <span className="text-danger-600">*</span>
          </label>
          <Select id="hr-refer-dept" value={department} onChange={(event) => setDepartment(event.target.value)}>
            <option value="">Select a department</option>
            {departments.map((item) => (
              <option key={item.slug} value={item.slug}>
                {item.name}
              </option>
            ))}
          </Select>
        </div>
        <div>
          <label htmlFor="hr-refer-brief" className="mb-1.5 block text-xs font-medium text-surface-600">
            Brief (optional)
          </label>
          <Textarea
            id="hr-refer-brief"
            rows={4}
            maxLength={TEXT_MAX}
            value={brief}
            onChange={(event) => setBrief(event.target.value)}
            placeholder="What do you need from this department?"
          />
        </div>
      </div>
    </Modal>
  )
}

/* ------------------------------------------------------------------ */

export function RespondHrReferralModal({
  request,
  referral,
  onClose,
}: {
  request: HrRequest
  referral: HrReferral | null
  onClose: () => void
}) {
  const toast = useToast()
  const respond = useRespondToHrReferral(String(request.id))
  const [response, setResponse] = useState('')
  const isOpen = referral !== null

  usePauseLiveRefresh(isOpen)
  useResetOnOpen(isOpen, () => setResponse(''))

  const valid = response.trim().length >= 2

  async function submit(): Promise<void> {
    if (!referral || !valid) return

    try {
      await respond.mutateAsync({ referralId: referral.id, response: response.trim() })
      toast.success('Response recorded', request.reference)
      onClose()
    } catch (error) {
      toast.error('Response not saved', errorText(error))
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Respond to referral"
      description={referral?.brief ? `Brief: ${referral.brief}` : 'Record the department response.'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={respond.isPending}>
            Cancel
          </Button>
          <Button disabled={!valid} isLoading={respond.isPending} onClick={() => void submit()}>
            Submit response
          </Button>
        </>
      }
    >
      <label htmlFor="hr-referral-response" className="mb-1.5 block text-xs font-medium text-surface-600">
        Response <span className="text-danger-600">*</span>
      </label>
      <Textarea
        id="hr-referral-response"
        rows={4}
        maxLength={4000}
        value={response}
        onChange={(event) => setResponse(event.target.value)}
        placeholder="Outcome or information from the department"
      />
    </Modal>
  )
}
