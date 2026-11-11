import {
  CheckCircle2,
  CircleDot,
  Clock3,
  FileText,
  Mail,
  Plus,
  Search,
  Send,
  ShieldCheck,
  Users,
} from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { StatCard } from '@/components/common/StatCard'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { SkeletonStatCard } from '@/components/ui/Skeleton'
import { ContactHrModal } from '@/features/hr/ContactHrModal'
import { hrAssigneeLabel } from '@/features/hr/hrAssigneeLabel'
import { HrRequestTable } from '@/features/hr/HrRequestTable'
import { TrackHrRequestModal } from '@/features/hr/TrackHrRequestModal'
import { useHrEmployeeDashboard } from '@/features/hr/useHr'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { greetingFor } from '@/utils/format'

export function HrHomePage() {
  const { user, hasPermission } = useAuth()
  const navigate = useNavigate()
  const { data, isPending, isError, error, refetch, isRefetching } = useHrEmployeeDashboard()
  const [isTracking, setTracking] = useState(false)
  const [isContactOpen, setContactOpen] = useState(false)

  const firstName = user?.full_name.split(' ')[0] ?? 'there'
  const canCreate = hasPermission('hr.requests.create')
  const errorMessage = isError
    ? error instanceof ApiError
      ? error.message
      : 'Your HR requests could not be loaded.'
    : null

  const stats = data?.statistics

  return (
    <div className="space-y-6">
      <section className="overflow-hidden rounded-card border border-primary-100 bg-panel shadow-card">
        <div className="hero-panel-glow relative px-6 py-7 sm:px-8">
          <p className="text-xs font-semibold tracking-[0.16em] text-primary-600 uppercase">HR Help Desk</p>
          <div className="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="text-2xl font-semibold tracking-tight text-surface-900">
                {greetingFor()}, {firstName}
              </h1>
              <p className="mt-1 text-sm text-surface-500">How can HR help you today?</p>
            </div>

            {canCreate && (
              <Button
                size="lg"
                leadingIcon={<Plus className="size-4" />}
                onClick={() => void navigate(paths.hr.submit)}
              >
                Submit Request
              </Button>
            )}
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {isPending ? (
          Array.from({ length: 5 }).map((_, index) => <SkeletonStatCard key={index} />)
        ) : (
          <>
            <StatCard label="Total Requests" value={stats?.total ?? 0} icon={FileText} tone="neutral" />
            <StatCard label="Open Requests" value={stats?.open ?? 0} icon={CircleDot} tone="info" />
            <StatCard label="In Progress" value={stats?.in_progress ?? 0} icon={Clock3} tone="primary" />
            <StatCard
              label="Awaiting Confirmation"
              value={stats?.awaiting_verification ?? 0}
              hint="Resolved requests waiting for your confirmation"
              icon={ShieldCheck}
              tone="warning"
            />
            <StatCard label="Closed" value={stats?.closed ?? 0} icon={CheckCircle2} tone="success" />
          </>
        )}
      </section>

      <div className="grid gap-6 xl:grid-cols-5">
        <div className="xl:col-span-3">
          <Card>
            <CardHeader
              title="Recent requests"
              description="Only HR requests you have raised."
              action={
                <Button variant="ghost" size="sm" onClick={() => void navigate(paths.hr.requests)}>
                  View all
                </Button>
              }
            />
            <HrRequestTable
              rows={data?.recent_requests ?? []}
              isLoading={isPending}
              errorMessage={errorMessage}
              onRetry={() => void refetch()}
              emptyTitle="You have not raised any HR requests yet"
              emptyDescription="Submit a request and the HR team will pick it up."
            />
            {!isPending && (data?.recent_requests?.length ?? 0) === 0 && canCreate && (
              <div className="flex justify-center border-t border-surface-100 px-5 py-4">
                <Button leadingIcon={<Plus className="size-4" />} onClick={() => void navigate(paths.hr.submit)}>
                  Submit Request
                </Button>
              </div>
            )}
          </Card>
        </div>

        <div className="space-y-4 xl:col-span-2">
          <Card>
            <CardHeader title="Quick actions" description="Common things employees need from HR." />
            <div className="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-1">
              {canCreate && (
                <QuickAction
                  icon={Send}
                  title="Submit HR Request"
                  copy="Ask for letters, payroll help, leave support, or raise a concern."
                  onClick={() => void navigate(paths.hr.submit)}
                />
              )}
              <QuickAction
                icon={FileText}
                title="View My HR Requests"
                copy="See every request you have raised with HR."
                onClick={() => void navigate(paths.hr.requests)}
              />
              <QuickAction
                icon={Search}
                title="Track Request"
                copy="Open a request by reference number."
                onClick={() => setTracking(true)}
              />
              <QuickAction
                icon={Mail}
                title="Contact HR"
                copy="Reach the HR help desk during business hours."
                onClick={() => setContactOpen(true)}
              />
            </div>
          </Card>

          {(data?.recent_requests?.length ?? 0) > 0 && (
            <Card>
              <CardHeader title="Assigned to" description="Who is handling your latest open request." />
              <div className="space-y-2 px-5 pb-5">
                {data?.recent_requests.slice(0, 3).map((request) => (
                  <button
                    key={request.id}
                    type="button"
                    onClick={() => void navigate(paths.hr.detail(request.id))}
                    className="flex w-full items-center gap-3 rounded-xl border border-surface-200 bg-surface-50/70 px-3 py-2.5 text-left transition-colors hover:border-primary-200 hover:bg-primary-50/40"
                  >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-panel text-primary-600 shadow-sm">
                      <Users className="size-3.5" />
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-mono text-[11px] text-primary-700">{request.reference}</span>
                      <span className="block truncate text-xs text-surface-600">{hrAssigneeLabel(request)}</span>
                    </span>
                  </button>
                ))}
              </div>
            </Card>
          )}

          {isRefetching && !isPending && (
            <p className="px-1 text-xs text-surface-400">Refreshing your HR requests…</p>
          )}
        </div>
      </div>

      <TrackHrRequestModal isOpen={isTracking} onClose={() => setTracking(false)} />
      <ContactHrModal isOpen={isContactOpen} onClose={() => setContactOpen(false)} />
    </div>
  )
}

function QuickAction({
  icon: Icon,
  title,
  copy,
  onClick,
}: {
  icon: typeof FileText
  title: string
  copy: string
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex gap-3 rounded-xl border border-surface-200 bg-surface-50/70 p-4 text-left transition-colors hover:border-primary-200 hover:bg-primary-50/40"
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-panel text-primary-600 shadow-sm">
        <Icon className="size-4" />
      </span>
      <span>
        <span className="block text-sm font-medium text-surface-900">{title}</span>
        <span className="mt-0.5 block text-xs text-surface-500">{copy}</span>
      </span>
    </button>
  )
}
