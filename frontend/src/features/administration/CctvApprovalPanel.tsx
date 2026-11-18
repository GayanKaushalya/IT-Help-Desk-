import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, X } from 'lucide-react'
import { useState } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { FormField } from '@/components/ui/FormField'
import { Textarea } from '@/components/ui/Textarea'
import { LoadingPanel } from '@/components/ui/Spinner'
import { admStaffKeys } from '@/features/administration/useAdmStaff'
import { registerKeys } from '@/features/administration/registers/useAdmRegisters'
import { useToast } from '@/hooks/useToast'
import { admRegistersService } from '@/services/admRegistersService'
import { ApiError } from '@/types/api'
import type { CctvDecisionStatus } from '@/types/admRegisters'
import type { ColorToken } from '@/types/models'
import { formatDateTime } from '@/utils/format'

const TONE: Record<CctvDecisionStatus, ColorToken> = { pending: 'warning', approved: 'success', rejected: 'danger' }
const LABEL: Record<CctvDecisionStatus, string> = {
  pending: 'Awaiting approval',
  approved: 'Approved',
  rejected: 'Rejected',
}

const DETAIL_LABELS: Record<string, string> = {
  location: 'Camera location',
  footage_from: 'Footage from',
  footage_to: 'Footage to',
  purpose: 'Purpose',
}

/** Read-only list of the category-specific fields captured when the request was raised. */
export function AdmRequestDetailsCard({ details }: { details: Record<string, string> | null | undefined }) {
  const entries = Object.entries(details ?? {}).filter(([, value]) => value)
  if (entries.length === 0) return null

  return (
    <Card>
      <CardHeader title="Footage details" description="Supplied by the requester." />
      <CardContent>
        <dl className="space-y-3 text-sm">
          {entries.map(([key, value]) => (
            <div key={key}>
              <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">
                {DETAIL_LABELS[key] ?? key.replace(/_/g, ' ')}
              </dt>
              <dd className="mt-0.5 whitespace-pre-wrap text-surface-900">
                {key.startsWith('footage_') ? formatDateTime(value) : value}
              </dd>
            </div>
          ))}
        </dl>
      </CardContent>
    </Card>
  )
}

/** CCTV approval: shows the decision and lets an authorised approver approve or reject. */
export function CctvApprovalPanel({ requestId }: { requestId: number }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [notes, setNotes] = useState('')
  const [notesError, setNotesError] = useState<string | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: registerKeys.cctv(requestId),
    queryFn: () => admRegistersService.cctv.show(requestId),
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: registerKeys.cctv(requestId) })
    void queryClient.invalidateQueries({ queryKey: admStaffKeys.detail(String(requestId)) })
    void queryClient.invalidateQueries({ queryKey: ['administration', 'staff', 'report'] })
  }

  const approve = useMutation({
    mutationFn: () => admRegistersService.cctv.approve(requestId, notes.trim()),
    onSuccess: () => {
      toast.success('CCTV request approved')
      setNotes('')
      refresh()
    },
    onError: (cause) => toast.error('Could not approve', cause instanceof ApiError ? cause.message : 'Please try again.'),
  })

  const reject = useMutation({
    mutationFn: () => admRegistersService.cctv.reject(requestId, notes.trim()),
    onSuccess: () => {
      toast.success('CCTV request rejected')
      setNotes('')
      refresh()
    },
    onError: (cause) => toast.error('Could not reject', cause instanceof ApiError ? cause.message : 'Please try again.'),
  })

  const approval = data?.approval ?? null

  return (
    <Card>
      <CardHeader
        title="CCTV approval"
        description="Footage can only be released once approved."
        action={approval ? <Badge tone={TONE[approval.status]} withDot>{LABEL[approval.status]}</Badge> : undefined}
      />
      <CardContent className="space-y-4">
        {isPending ? (
          <LoadingPanel label="Loading approval..." />
        ) : isError || !data ? (
          <Alert tone="danger" title="Unable to load the approval">
            Please refresh the page.
          </Alert>
        ) : (
          <>
            {approval && approval.status !== 'pending' && (
              <div className="rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 text-sm">
                <p className="font-medium text-surface-900">
                  {LABEL[approval.status]}
                  {approval.decided_by ? ` by ${approval.decided_by.full_name}` : ''}
                </p>
                <p className="text-xs text-surface-500">{formatDateTime(approval.decided_at)}</p>
                {approval.decision_notes && (
                  <p className="mt-2 whitespace-pre-wrap text-surface-700">{approval.decision_notes}</p>
                )}
              </div>
            )}

            {approval?.status === 'pending' && !data.can_decide && (
              <p className="text-sm text-surface-600">
                Waiting for an authorised approver. You cannot approve a request you raised yourself.
              </p>
            )}

            {data.can_decide && approval?.status === 'pending' && (
              <div className="space-y-3">
                <FormField label="Decision notes" error={notesError ?? undefined} hint="Required when rejecting.">
                  {({ id, hasError }) => (
                    <Textarea
                      id={id}
                      rows={3}
                      hasError={hasError}
                      value={notes}
                      onChange={(event) => {
                        setNotes(event.target.value)
                        setNotesError(null)
                      }}
                    />
                  )}
                </FormField>
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="success"
                    leadingIcon={<Check className="size-4" />}
                    isLoading={approve.isPending}
                    disabled={reject.isPending}
                    onClick={() => approve.mutate()}
                  >
                    Approve
                  </Button>
                  <Button
                    variant="danger"
                    leadingIcon={<X className="size-4" />}
                    isLoading={reject.isPending}
                    disabled={approve.isPending}
                    onClick={() => {
                      if (!notes.trim()) {
                        setNotesError('Give a reason for rejecting this request.')
                        return
                      }
                      reject.mutate()
                    }}
                  >
                    Reject
                  </Button>
                </div>
              </div>
            )}

            {data.access_logs.length > 0 && (
              <div>
                <p className="text-xs font-semibold tracking-wide text-surface-500 uppercase">Access log</p>
                <ul className="mt-2 space-y-2">
                  {data.access_logs.map((log) => (
                    <li key={log.id} className="text-sm">
                      <span className="font-medium text-surface-900">{log.action.replace(/_/g, ' ')}</span>
                      <span className="text-xs text-surface-500">
                        {' '}
                        &middot; {log.user?.full_name ?? 'System'} &middot; {formatDateTime(log.created_at)}
                      </span>
                      {log.notes && <p className="text-xs text-surface-600">{log.notes}</p>}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}
