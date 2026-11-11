import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Textarea } from '@/components/ui/Textarea'
import { useStaffTicketComment } from '@/features/admin/useStaffDashboard'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { useToast } from '@/hooks/useToast'
import { ApiError } from '@/types/api'
import type { Ticket, TicketComment } from '@/types/models'
import { clearFormDraft, readFormDraft, writeFormDraft } from '@/utils/formDraft'
import { formatDateTime } from '@/utils/format'
import { cn } from '@/utils/cn'

const COMMENT_MAX = 4000

type ConversationDraft = { body: string; internal: boolean }

function draftKey(ticketId: number | string): string {
  return `helpdesk.staff-comment.${ticketId}`
}

export function StaffConversation({ ticket }: { ticket: Ticket }) {
  const { hasPermission } = useAuth()
  const toast = useToast()
  const comment = useStaffTicketComment(String(ticket.id))
  const saved = readFormDraft<ConversationDraft>(draftKey(ticket.id))
  const [body, setBody] = useState(saved?.body ?? '')
  const [internal, setInternal] = useState(Boolean(saved?.internal))
  const [error, setError] = useState<string | null>(null)

  const closed = Boolean(ticket.status?.is_closed)
  const canPublic = hasPermission('tickets.comment') && !closed
  const canInternal = hasPermission('tickets.comment_internal') && !closed

  usePauseLiveRefresh(body.trim().length > 0)

  useEffect(() => {
    if (body.trim()) {
      writeFormDraft(draftKey(ticket.id), { body, internal })
    } else {
      clearFormDraft(draftKey(ticket.id))
    }
  }, [body, internal, ticket.id])

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault()
    const message = body.trim()
    if (message.length < 2) {
      setError('Enter a message of at least 2 characters.')
      return
    }

    setError(null)

    try {
      await comment.mutateAsync({ body: message, isInternal: internal && canInternal })
      setBody('')
      clearFormDraft(draftKey(ticket.id))
      toast.success(internal ? 'Internal note added' : 'Message sent')
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'The message could not be saved.')
    }
  }

  const comments = ticket.comments ?? []

  return (
    <div className="space-y-5">
      {comments.length === 0 ? (
        <p className="text-sm text-surface-500">No conversation yet.</p>
      ) : (
        <ul className="space-y-3">
          {comments.map((entry) => (
            <StaffCommentBubble key={entry.id} comment={entry} />
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
            placeholder={internal ? 'Internal note — employees will never see this.' : 'Public reply visible to the employee.'}
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
        <p className="text-xs text-surface-500">
          {closed ? 'This ticket is closed, so new notes cannot be added.' : 'You cannot comment on this ticket.'}
        </p>
      )}
    </div>
  )
}

function StaffCommentBubble({ comment }: { comment: TicketComment }) {
  const internal = Boolean(comment.is_internal)

  return (
    <li
      className={cn(
        'rounded-xl border px-4 py-3',
        internal
          ? 'border-warning-500/40 bg-warning-500/15 text-surface-900'
          : 'border-surface-300 bg-surface-200 text-surface-900',
      )}
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-xs font-semibold text-surface-900">
          {comment.author?.full_name ?? 'Unknown'}
          <span className="ml-1.5 font-medium text-surface-500">
            {internal ? 'Internal note' : comment.author?.is_staff ? 'IT staff' : 'Employee'}
          </span>
        </p>
        <p className="text-[11px] text-surface-500">{formatDateTime(comment.created_at)}</p>
      </div>
      <p className="mt-1.5 text-sm leading-6 whitespace-pre-wrap text-surface-800">{comment.body}</p>
    </li>
  )
}
