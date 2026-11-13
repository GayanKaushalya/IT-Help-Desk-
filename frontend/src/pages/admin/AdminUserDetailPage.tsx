import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'

import { DescriptionItem, DescriptionList } from '@/components/common/DescriptionList'
import { PageHeader } from '@/components/common/PageHeader'
import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import { AccountStatusBadge } from '@/features/admin/accountStatus'
import { TemporaryPasswordDialog } from '@/features/admin/TemporaryPasswordDialog'
import { UserFormModal } from '@/features/admin/UserFormModal'
import {
  useActivateAdminUser,
  useAdminUser,
  useDeactivateAdminUser,
  useResetAdminUserPassword,
} from '@/features/admin/useAdminDirectory'
import { TicketPriorityBadge, TicketStatusBadge } from '@/features/tickets/TicketStatusBadge'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

export function AdminUserDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const { user: actor, hasPermission } = useAuth()
  const { data: user, isPending, isError, error, refetch, isRefetching } = useAdminUser(id)
  const activate = useActivateAdminUser(id ?? '0')
  const deactivate = useDeactivateAdminUser(id ?? '0')
  const resetPassword = useResetAdminUserPassword(id ?? '0')
  const [editOpen, setEditOpen] = useState(false)
  const [confirmDeactivate, setConfirmDeactivate] = useState(false)
  const [confirmReset, setConfirmReset] = useState(false)
  const [issued, setIssued] = useState<string | null>(null)

  if (isPending) {
    return <LoadingPanel label="Loading user…" />
  }

  if (isError || !user) {
    return (
      <div className="space-y-6">
        <PageHeader title="User" description="This account could not be opened." />
        <Card>
          <ErrorState
            title="User could not be loaded"
            description={error instanceof ApiError ? error.message : 'This account was not found.'}
            onRetry={() => void refetch()}
            isRetrying={isRefetching}
          />
        </Card>
        <Button variant="outline" leadingIcon={<ArrowLeft className="size-4" />} onClick={() => void navigate(paths.admin.users)}>
          Back to users
        </Button>
      </div>
    )
  }

  const isSelf = actor?.id === user.id
  const canUpdate = hasPermission('users.update')
  const canToggle = hasPermission('users.deactivate') && !isSelf
  const canReset = hasPermission('users.reset_password') && !isSelf
  const stats = user.ticket_statistics

  return (
    <div className="space-y-6">
      <PageHeader
        title={user.full_name}
        description={`@${user.username}`}
        eyebrow={
          <Button variant="ghost" size="sm" onClick={() => void navigate(paths.admin.users)}>
            <ArrowLeft className="size-4" /> Users
          </Button>
        }
        actions={
          <div className="flex flex-wrap gap-2">
            <AccountStatusBadge status={user.account_status} isActive={user.is_active} isApproved={user.is_approved} />
            {canUpdate && (
              <Button variant="outline" onClick={() => setEditOpen(true)}>
                Edit user
              </Button>
            )}
            {canToggle && user.is_active && (
              <Button variant="danger" onClick={() => setConfirmDeactivate(true)}>
                Deactivate
              </Button>
            )}
            {canToggle && !user.is_active && (
              <Button
                variant="success"
                isLoading={activate.isPending}
                onClick={() =>
                  void activate.mutateAsync().then(() => toast.success('User activated', `${user.full_name} can sign in again.`))
                }
              >
                Activate
              </Button>
            )}
            {canReset && (
              <Button variant="outline" onClick={() => setConfirmReset(true)}>
                Reset password
              </Button>
            )}
          </div>
        }
      />

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card>
            <CardHeader title="Personal Information" />
            <CardContent>
              <DescriptionList className="grid gap-4 sm:grid-cols-2 sm:space-y-0">
                <DescriptionItem label="Full name">{user.full_name}</DescriptionItem>
                <DescriptionItem label="Email">{user.email}</DescriptionItem>
                <DescriptionItem label="Phone">{user.phone}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Employee Information" />
            <CardContent>
              <DescriptionList className="grid gap-4 sm:grid-cols-2 sm:space-y-0">
                <DescriptionItem label="Employee ID" mono>
                  {user.employee_id}
                </DescriptionItem>
                <DescriptionItem label="EPF Number" mono>
                  {user.epf_number}
                </DescriptionItem>
                <DescriptionItem label="Job title">{user.job_title}</DescriptionItem>
                <DescriptionItem label="Department">{user.department?.name}</DescriptionItem>
                <DescriptionItem label="Branch">{user.branch?.name}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Ticket Statistics" />
            <CardContent>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard label="Total" value={stats?.total ?? 0} />
                <StatCard label="Open" value={stats?.open ?? 0} tone="info" />
                <StatCard label="In Progress" value={stats?.in_progress ?? 0} />
                <StatCard label="Awaiting Verification" value={stats?.awaiting_verification ?? 0} tone="warning" />
                <StatCard label="Closed" value={stats?.closed ?? 0} tone="neutral" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Recent Tickets" description="Requests this person has raised." />
            <CardContent>
              {(user.recent_tickets ?? []).length === 0 ? (
                <p className="text-sm text-surface-500">No tickets have been raised by this user.</p>
              ) : (
                <ul className="divide-y divide-surface-100">
                  {user.recent_tickets?.map((ticket) => (
                    <li key={ticket.id}>
                      <button
                        type="button"
                        className="flex w-full items-center justify-between gap-3 py-3 text-left"
                        onClick={() => void navigate(paths.admin.ticketDetail(ticket.id))}
                      >
                        <div>
                          <p className="font-mono text-xs text-primary-700">{ticket.reference}</p>
                          <p className="text-sm font-medium text-surface-900">{ticket.subject}</p>
                        </div>
                        <div className="flex items-center gap-2">
                          <TicketPriorityBadge priority={ticket.priority} />
                          <TicketStatusBadge status={ticket.status} awaitingVerification={ticket.awaiting_verification} />
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Account Information" />
            <CardContent>
              <DescriptionList>
                <DescriptionItem label="Username" mono>
                  {user.username}
                </DescriptionItem>
                <DescriptionItem label="Role">{user.role?.name}</DescriptionItem>
                <DescriptionItem label="Status">
                  <AccountStatusBadge status={user.account_status} isActive={user.is_active} isApproved={user.is_approved} />
                </DescriptionItem>
                <DescriptionItem label="Last sign-in">{formatDateTime(user.last_login_at)}</DescriptionItem>
                <DescriptionItem label="Approved">{formatDateTime(user.approved_at)}</DescriptionItem>
                <DescriptionItem label="Password changed">{formatDateTime(user.password_changed_at)}</DescriptionItem>
                <DescriptionItem label="Created">{formatDateTime(user.created_at)}</DescriptionItem>
              </DescriptionList>
            </CardContent>
          </Card>

          {user.audit_logs && (
            <Card>
              <CardHeader title="Audit History" description="Account events visible to authorised administrators." />
              <CardContent>
                {user.audit_logs.length === 0 ? (
                  <p className="text-sm text-surface-500">No audit events are recorded for this account yet.</p>
                ) : (
                  <ul className="space-y-3">
                    {user.audit_logs.map((entry) => (
                      <li key={entry.id} className="border-b border-surface-100 pb-3 last:border-0 last:pb-0">
                        <p className="text-sm font-medium text-surface-800">{entry.description || entry.event}</p>
                        <p className="mt-0.5 text-xs text-surface-500">
                          {formatDateTime(entry.created_at)}
                          {entry.actor?.full_name ? ` · ${entry.actor.full_name}` : ''}
                        </p>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      <UserFormModal
        isOpen={editOpen}
        onClose={() => setEditOpen(false)}
        user={user}
        onIssuedPassword={(_username, password) => setIssued(password)}
      />
      <TemporaryPasswordDialog
        isOpen={issued !== null}
        username={user.username}
        password={issued ?? ''}
        onClose={() => setIssued(null)}
      />
      <ConfirmDialog
        isOpen={confirmDeactivate}
        onClose={() => setConfirmDeactivate(false)}
        title={`Deactivate ${user.full_name}?`}
        description="They will not be able to sign in until the account is activated again. Open sessions will be ended."
        confirmLabel="Deactivate"
        tone="danger"
        isConfirming={deactivate.isPending}
        onConfirm={() =>
          void deactivate.mutateAsync().then(() => {
            toast.success('User deactivated', `${user.full_name} can no longer sign in.`)
            setConfirmDeactivate(false)
          })
        }
      />
      <ConfirmDialog
        isOpen={confirmReset}
        onClose={() => setConfirmReset(false)}
        title={`Reset password for ${user.full_name}?`}
        description="A temporary password will be generated once. Their current sessions will be signed out."
        confirmLabel="Reset password"
        isConfirming={resetPassword.isPending}
        onConfirm={() =>
          void resetPassword.mutateAsync().then((updated) => {
            setConfirmReset(false)
            if (updated.temporary_password) {
              setIssued(updated.temporary_password)
            }
            toast.success('Password reset', 'Give the temporary password to the user once.')
          })
        }
      />
    </div>
  )
}

