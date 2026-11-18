import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Textarea } from '@/components/ui/Textarea'
import { useAddAdmComment } from '@/features/administration/useAdm'
import { useAdmStaffComment } from '@/features/administration/useAdmStaff'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { AdmComment, AdmRequest } from '@/types/models'
import { cn } from '@/utils/cn'
import { clearFormDraft, readFormDraft, writeFormDraft } from '@/utils/formDraft'
import { formatDateTime } from '@/utils/format'

const COMMENT_MAX = 4000

/* ------------------------------------------------------------------ *
 * Employee conversation - public messages only
 * ------------------------------------------------------------------ */

function employeeDraftKey(id: number | string): string {
  return `helpdesk.adm-comment.${id}`
}

export function AdmComments({ request, canReply = true }: { request: AdmRequest; canReply?: boolean }) {
  const { hasPermission } = useAuth()
  const comment = useAddAdmComment(String(request.id))
  const [body, setBody] = useState(() => readFormDraft<string>(employeeDraftKey(request.id)) ?? '')
  const [error, setError] = useState<string | null>(null)

  const terminal = Boolean(request.status?.is_terminal)
  const allowed = canReply && hasPermission('adm.requests.comment') && !terminal

  usePauseLiveRefresh(body.trim().length > 0)

  useEffect(() => {
    if (body.trim()) {
      writeFormDraft(employeeDraftKey(request.id), body)
    } else {
      clearFormDraft(employeeDraftKey(request.id))
    }
  }, [body, request.id])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    const message = body.trim()

    if (message.length < 2) {
      setError('Enter a message to send.')
      return
    }

    setError(null)

    try {
      await comment.mutateAsync(message)
      setBody('')
      clearFormDraft(employeeDraftKey(request.id))
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Your message could not be sent.')
    }
  }

  const comments = (request.comments ?? []).filter((entry) => !entry.is_internal)

  return (
    <div className="space-y-5">
      {comments.length === 0 ? (
        <p className="text-sm text-surface-500">No messages yet. Administration will reply here if they need more information.</p>
      ) : (
        <ul className="space-y-4">
          {comments.map((entry) => (
            <EmployeeBubble key={entry.id} comment={entry} />
          ))}
        </ul>
      )}

      {allowed ? (
        <form noValidate className="space-y-3 border-t border-surface-200 pt-4" onSubmit={(event) => void handleSubmit(event)}>
          {error && (
            <Alert tone="danger" title="Message not sent">
              {error}
            </Alert>
          )}
          <Textarea
            rows={4}
            maxLength={COMMENT_MAX}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Write a reply for the Administration team..."
            disabled={comment.isPending}
          />
          <div className="flex items-center justify-between gap-3">
            <p className="text-xs text-surface-400">
              {body.length} / {COMMENT_MAX}
            </p>
            <Button type="submit" isLoading={comment.isPending}>
              Send message
            </Button>
          </div>
        </form>
      ) : (
        <p className="text-xs text-surface-500">
          {terminal
            ? 'This request is finished, so new messages cannot be added.'
            : 'You cannot add messages to this request.'}
        </p>
      )}
    </div>
  )
}

function EmployeeBubble({ comment }: { comment: AdmComment }) {
  const fromStaff = Boolean(comment.author?.is_administration_staff)

  if (comment.is_system) {
    return (
      <li className="text-center text-xs text-surface-500">
        {comment.body} &middot; {formatDateTime(comment.created_at)}
      </li>
    )
  }

  return (
    <li className={cn('flex', fromStaff ? 'justify-start' : 'justify-end')}>
      <div
        className={cn(
          'max-w-[85%] rounded-2xl px-4 py-3',
          fromStaff
            ? 'rounded-tl-md border border-primary-500/30 bg-primary-600 text-white'
            : 'rounded-tr-md border border-surface-300 bg-surface-200 text-surface-900',
        )}
      >
        <div className="flex flex-wrap items-baseline gap-2">
          <p className="text-xs font-semibold">
            {comment.author?.full_name ?? 'Unknown'}
            <span className={cn('ml-1.5 font-medium', fromStaff ? 'text-primary-100' : 'text-surface-500')}>
              {fromStaff ? 'Administration Team' : 'Employee'}
            </span>
          </p>
          <p className={cn('text-[11px]', fromStaff ? 'text-primary-100/90' : 'text-surface-500')}>
            {formatDateTime(comment.created_at)}
          </p>
        </div>
        <p className="mt-1.5 text-sm leading-6 whitespace-pre-wrap">{comment.body}</p>
      </div>
    </li>
  )
}

/* ------------------------------------------------------------------ *
 * Staff conversation - public replies and internal notes
 * ------------------------------------------------------------------ */

type StaffDraft = { body: string; internal: boolean }

function staffDraftKey(id: number | string): string {
  return `helpdesk.adm-staff-comment.${id}`
}

export function AdmStaffConversation({ request }: { request: AdmRequest }) {
  const { hasPermission } = useAuth()
  const toast = useToast()
  const comment = useAdmStaffComment(String(request.id))
  const saved = readFormDraft<StaffDraft>(staffDraftKey(request.id))
  const [body, setBody] = useState(saved?.body ?? '')
  const [internal, setInternal] = useState(Boolean(saved?.internal))
  const [error, setError] = useState<string | null>(null)

  const canPublic = hasPermission('adm.requests.comment')
  const canInternal = hasPermission('adm.requests.comment_internal')

  usePauseLiveRefresh(body.trim().length > 0)

  useEffect(() => {
    if (body.trim()) {
      writeFormDraft(staffDraftKey(request.id), { body, internal })
    } else {
      clearFormDraft(staffDraftKey(request.id))
    }
  }, [body, internal, request.id])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    const message = body.trim()

    if (message.length < 2) {
      setError('Enter a message of at least 2 characters.')
      return
    }

    const sendInternal = internal && canInternal
    setError(null)

    try {
      await comment.mutateAsync({ body: message, isInternal: sendInternal })
      setBody('')
      clearFormDraft(staffDraftKey(request.id))
      toast.success(sendInternal ? 'Internal note added' : 'Message sent')
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'The message could not be saved.')
    }
  }

  const comments = request.comments ?? []

  return (
    <div className="space-y-5">
      {comments.length === 0 ? (
        <p className="text-sm text-surface-500">No conversation yet.</p>
      ) : (
        <ul className="space-y-3">
          {comments.map((entry) => (
            <StaffBubble key={entry.id} comment={entry} />
          ))}
        </ul>
      )}

      {canPublic || canInternal ? (
        <form noValidate className="space-y-3 border-t border-surface-200 pt-4" onSubmit={(event) => void handleSubmit(event)}>
          {error && (
            <Alert tone="danger" title="Not saved">
              {error}
            </Alert>
          )}
          <Textarea
            rows={4}
            maxLength={COMMENT_MAX}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder={
              internal ? 'Internal note - the employee will never see this.' : 'Public reply visible to the employee.'
            }
            disabled={comment.isPending}
          />
          <div className="flex flex-wrap items-center justify-between gap-3">
            <label className={cn('flex items-center gap-2 text-sm', canInternal ? 'text-surface-700' : 'text-surface-400')}>
              <input
                type="checkbox"
                checked={internal}
                disabled={!canInternal}
                onChange={(event) => setInternal(event.target.checked)}
              />
              Internal note
            </label>
            <Button type="submit" isLoading={comment.isPending}>
              {internal ? 'Add internal note' : 'Send public comment'}
            </Button>
          </div>
        </form>
      ) : (
        <p className="text-xs text-surface-500">You cannot comment on this request.</p>
      )}
    </div>
  )
}

function StaffBubble({ comment }: { comment: AdmComment }) {
  const internal = Boolean(comment.is_internal)

  return (
    <li
      className={cn(
        'rounded-xl border px-4 py-3',
        internal
          ? 'border-warning-500/40 bg-warning-500/15 text-surface-900'
          : comment.is_system
            ? 'border-surface-200 bg-surface-50 text-surface-700'
            : 'border-surface-300 bg-surface-200 text-surface-900',
      )}
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-xs font-semibold text-surface-900">
          {comment.is_system ? 'System' : (comment.author?.full_name ?? 'Unknown')}
          <span className="ml-1.5 font-medium text-surface-500">
            {internal ? 'Internal note' : comment.is_system ? '' : comment.author?.is_administration_staff ? 'Administration staff' : 'Employee'}
          </span>
        </p>
        <p className="text-[11px] text-surface-500">{formatDateTime(comment.created_at)}</p>
      </div>
      <p className="mt-1.5 text-sm leading-6 whitespace-pre-wrap text-surface-800">{comment.body}</p>
    </li>
  )
}
