import { useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { ticketKeys, useVerifyTicket } from '@/features/tickets/useTickets'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ticketService } from '@/services/ticketService'
import { ApiError } from '@/types/api'
import type { Ticket } from '@/types/models'

function isAlreadyClosed(ticket: Ticket | null | undefined): boolean {
  if (!ticket) return false

  return Boolean(
    ticket.status?.is_closed
      || ticket.status?.slug === 'closed'
      || ticket.verification?.status === 'verified'
      || (ticket.awaiting_verification === false && ticket.verification?.completed_at),
  )
}

export function VerifyResolutionModal({
  ticket,
  isOpen,
  onClose,
}: {
  ticket: Ticket
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const verify = useVerifyTicket(String(ticket.id))
  const [error, setError] = useState<string | null>(null)
  const submitting = useRef(false)

  usePauseLiveRefresh(isOpen)

  useEffect(() => {
    if (!isOpen) return
    submitting.current = false
    setError(null)
  }, [isOpen, ticket.id])

  function succeedConfirmed(): void {
    submitting.current = false
    setError(null)
    toast.success('Resolution confirmed', `${ticket.reference} is now closed.`)
    onClose()
  }

  function succeedRejected(): void {
    submitting.current = false
    setError(null)
    toast.success('Thanks for the update', 'IT support will continue working on this ticket.')
    onClose()
  }

  async function latestTicket(): Promise<Ticket | null> {
    try {
      return await queryClient.fetchQuery({
        queryKey: ticketKeys.detail(String(ticket.id)),
        queryFn: () => ticketService.show(ticket.id),
      })
    } catch {
      return null
    }
  }

  async function handleDecision(decision: 'confirmed' | 'rejected'): Promise<void> {
    if (verify.isPending || submitting.current) return

    if (decision === 'confirmed' && isAlreadyClosed(ticket)) {
      succeedConfirmed()
      return
    }

    setError(null)
    submitting.current = true

    try {
      await verify.mutateAsync(decision)
      if (decision === 'confirmed') {
        succeedConfirmed()
      } else {
        succeedRejected()
      }
    } catch (cause) {
      submitting.current = false
      const latest = await latestTicket()

      if (decision === 'confirmed' && (isAlreadyClosed(latest) || isAlreadyClosed(ticket))) {
        succeedConfirmed()
        return
      }

      setError(
        cause instanceof ApiError
          ? cause.fieldErrors.assigned_to ||
              cause.fieldErrors.decision ||
              cause.message
          : 'The response could not be saved. Please try again.',
      )
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Confirm resolution"
      description="IT marked this ticket as resolved. Please confirm whether your issue is fixed."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={verify.isPending}>
            Cancel
          </Button>
          <Button
            variant="danger"
            isLoading={verify.isPending}
            disabled={verify.isPending}
            onClick={() => void handleDecision('rejected')}
          >
            Not resolved
          </Button>
          <Button
            variant="success"
            isLoading={verify.isPending}
            disabled={verify.isPending}
            onClick={() => void handleDecision('confirmed')}
          >
            Yes, it is resolved
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && (
          <Alert tone="danger" title="Could not save your response">
            {error}
          </Alert>
        )}
        <p className="text-sm text-surface-700">
          Choose <strong>Yes, it is resolved</strong> to close the ticket, or{' '}
          <strong>Not resolved</strong> if IT still needs to help.
        </p>
      </div>
    </Modal>
  )
}
