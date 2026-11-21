import { useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { hrKeys, useVerifyHrRequest } from '@/features/hr/useHr'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { hrService } from '@/services/hrService'
import { ApiError } from '@/types/api'
import type { HrRequest } from '@/types/models'
import { formatDateTime } from '@/utils/format'

function isAlreadyClosed(request: HrRequest | null | undefined): boolean {
  if (!request) return false

  return Boolean(
    request.status?.is_terminal ||
      request.status?.slug === 'closed' ||
      request.verification?.status === 'verified' ||
      (request.awaiting_verification === false && request.verification?.completed_at),
  )
}

export function VerifyHrResolutionModal({
  request,
  isOpen,
  onClose,
}: {
  request: HrRequest
  isOpen: boolean
  onClose: () => void
}) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const verify = useVerifyHrRequest(String(request.id))
  const [error, setError] = useState<string | null>(null)
  const submitting = useRef(false)

  usePauseLiveRefresh(isOpen)

  useEffect(() => {
    if (!isOpen) return
    submitting.current = false
    setError(null)
  }, [isOpen, request.id])

  function succeedConfirmed(): void {
    submitting.current = false
    setError(null)
    toast.success('Resolution confirmed', `${request.reference} is now closed.`)
    onClose()
  }

  function succeedRejected(): void {
    submitting.current = false
    setError(null)
    toast.success('Thanks for the update', 'HR will continue working on this request.')
    onClose()
  }

  async function latestRequest(): Promise<HrRequest | null> {
    try {
      return await queryClient.fetchQuery({
        queryKey: hrKeys.detail(String(request.id)),
        queryFn: () => hrService.show(request.id),
      })
    } catch {
      return null
    }
  }

  async function handleDecision(decision: 'confirmed' | 'rejected'): Promise<void> {
    if (verify.isPending || submitting.current) return

    if (decision === 'confirmed' && isAlreadyClosed(request)) {
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
      const latest = await latestRequest()

      if (decision === 'confirmed' && (isAlreadyClosed(latest) || isAlreadyClosed(request))) {
        succeedConfirmed()
        return
      }

      setError(
        cause instanceof ApiError
          ? cause.fieldErrors.assigned_to || cause.fieldErrors.decision || cause.message
          : 'The response could not be saved. Please try again.',
      )
    }
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Confirm resolution"
      description="HR marked this request as resolved. Please confirm whether it is complete."
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
          Choose <strong>Yes, it is resolved</strong> to close the request, or{' '}
          <strong>Not resolved</strong> if HR still needs to help.
        </p>
        <div className="rounded-lg border border-warning-200 bg-warning-50/70 px-3 py-2 text-sm text-surface-800">
          <p className="font-medium">Please confirm within 2 days.</p>
          {request.verification_due_at && (
            <p className="mt-0.5 text-xs text-surface-600">
              Due by {formatDateTime(request.verification_due_at)}. The request closes automatically if we do not hear back.
            </p>
          )}
        </div>
      </div>
    </Modal>
  )
}
