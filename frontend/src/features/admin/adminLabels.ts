import type { TicketStatus } from '@/types/models'

/** Admin-facing labels that match the service-desk workflow copy. */
export function adminStatusLabel(status?: TicketStatus, awaitingVerification = false): string {
  if (!status) return '—'
  if (status.slug === 'new') return 'Open'
  if (status.slug === 'awaiting-user') return 'Waiting for User'
  if (awaitingVerification || (status.is_resolved && status.requires_verification && !status.is_closed)) {
    return 'Awaiting Verification'
  }

  return status.name
}
