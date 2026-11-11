import { Database, Server } from 'lucide-react'

import { Badge } from '@/components/ui/Badge'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { Skeleton } from '@/components/ui/Skeleton'
import { useSystemHealth } from '@/features/system/useSystemStatus'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

/**
 * Live connectivity check between the SPA and the Laravel API.
 */
export function SystemStatusCard() {
  const { data, isPending, isError, error, refetch, isRefetching } = useSystemHealth()

  const description =
    error instanceof ApiError
      ? error.message
      : 'The API did not respond. Confirm the backend is running on port 8000.'

  return (
    <Card>
      <CardHeader
        title="System status"
        description="Connectivity between this application and the API."
        action={
          isPending ? (
            <Skeleton className="h-6 w-20 rounded-full" />
          ) : (
            <Badge tone={data?.database.connected ? 'success' : 'danger'} withDot>
              {data?.database.connected ? 'Operational' : 'Degraded'}
            </Badge>
          )
        }
      />

      <CardContent>
        {isError ? (
          <ErrorState
            title="API unreachable"
            description={description}
            onRetry={() => void refetch()}
            isRetrying={isRefetching}
            className="py-8"
          />
        ) : (
          <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
            <StatusRow
              icon={Server}
              label="API"
              value={isPending ? null : `${data.application} · ${data.api_version}`}
              hint={isPending ? null : data.environment}
            />
            <StatusRow
              icon={Database}
              label="Database"
              value={isPending ? null : (data.database.name ?? 'Not connected')}
              hint={isPending ? null : data.database.driver}
            />
            <StatusRow
              label="Server time"
              value={isPending ? null : formatDateTime(data.server_time)}
            />
          </dl>
        )}
      </CardContent>
    </Card>
  )
}

interface StatusRowProps {
  label: string
  value: string | null
  hint?: string | null
  icon?: React.ComponentType<{ className?: string }>
}

function StatusRow({ label, value, hint, icon: Icon }: StatusRowProps) {
  return (
    <div className="flex items-start gap-3">
      {Icon && (
        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-100">
          <Icon className="size-4 text-surface-500" />
        </span>
      )}

      <div className="min-w-0">
        <dt className="text-xs font-medium tracking-wide text-surface-500 uppercase">{label}</dt>
        <dd className="mt-1">
          {value === null ? (
            <Skeleton className="h-4 w-32" />
          ) : (
            <span className="text-sm font-medium text-surface-900">{value}</span>
          )}
          {hint && <span className="ml-2 text-xs text-surface-500">{hint}</span>}
        </dd>
      </div>
    </div>
  )
}
