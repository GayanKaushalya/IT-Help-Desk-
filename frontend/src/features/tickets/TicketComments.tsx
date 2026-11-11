import { useEffect, useState, type FormEvent } from 'react'

import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Textarea } from '@/components/ui/Textarea'
import { useAddTicketComment } from '@/features/tickets/useTickets'
import { useAuth } from '@/hooks/useAuth'
import { usePauseLiveRefresh } from '@/hooks/useLiveRefreshGuard'
import { ApiError } from '@/types/api'
import type { Ticket, TicketComment } from '@/types/models'
import { clearFormDraft, readFormDraft, writeFormDraft } from '@/utils/formDraft'
import { formatDateTime } from '@/utils/format'
import { cn } from '@/utils/cn'

const COMMENT_MAX = 4000

interface TicketCommentsProps {
  ticket: Ticket
}

function draftKey(ticketId: number | string): string {
  return `helpdesk.ticket-comment.${ticketId}`
}

export function TicketComments({ ticket }: TicketCommentsProps) {
  const { hasPermission } = useAuth()
  const canComment = hasPermission('tickets.comment') && !ticket.status?.is_closed
  const comment = useAddTicketComment(String(ticket.id))
  const [body, setBody] = useState(() => readFormDraft<string>(draftKey(ticket.id)) ?? '')
  const [error, setError] = useState<string | null>(null)

  usePauseLiveRefresh(body.trim().length > 0)

  useEffect(() => {
    if (body.trim()) {
      writeFormDraft(draftKey(ticket.id), body)
    } else {
      clearFormDraft(draftKey(ticket.id))
    }
  }, [body, ticket.id])

  async function handleSubmit(_event: FormEvent<HTMLFormElement>): Promise<void> {
    const message = body.trim()
    if (message.length < 2) {
      setError('Enter a message to send.')
      return
    }

    setError(null)

    try {
      await comment.mutateAsync(message)
      setBody('')
      clearFormDraft(draftKey(ticket.id))
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Your message could not be sent.')
    }
  }

  const comments = ticket.comments ?? []

  return (
    <div className="space-y-5">
      {comments.length === 0 ? (
        <p className="text-sm text-surface-500">No messages yet. Add an update if IT needs more information.</p>
      ) : (
        <ul className="space-y-4">
          {comments.map((entry) => (
            <CommentBubble key={entry.id} comment={entry} />
          ))}
        </ul>
      )}

      {canComment ? (
        <form
          noValidate
          className="space-y-3 border-t border-surface-200 pt-4"
          onSubmit={(event) => {
            event.preventDefault()
            void handleSubmit(event)
          }}
        >
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
            placeholder="Write a reply for IT support…"
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
          {ticket.status?.is_closed
            ? 'This ticket is closed, so new messages cannot be added.'
            : 'You do not have permission to comment on this ticket.'}
        </p>
      )}
    </div>
  )
}

function CommentBubble({ comment }: { comment: TicketComment }) {
  const fromStaff = Boolean(comment.author?.is_staff)

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
            <span
              className={cn(
                'ml-1.5 font-medium',
                fromStaff ? 'text-primary-100' : 'text-surface-500',
              )}
            >
              {fromStaff ? 'IT Support' : 'Employee'}
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
