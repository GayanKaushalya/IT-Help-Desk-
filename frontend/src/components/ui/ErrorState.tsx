import { AlertTriangle, RefreshCw } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { cn } from '@/utils/cn'

export interface ErrorStateProps {
  title?: string
  description?: string
  onRetry?: () => void
  isRetrying?: boolean
  className?: string
}

export function ErrorState({
  title = 'Something went wrong',
  description = 'The information could not be loaded. Please try again.',
  onRetry,
  isRetrying = false,
  className,
}: ErrorStateProps) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-16 text-center', className)}>
      <div className="flex size-12 items-center justify-center rounded-full bg-danger-50">
        <AlertTriangle className="size-6 text-danger-600" />
      </div>

      <h3 className="mt-4 text-sm font-semibold text-surface-900">{title}</h3>
      <p className="mt-1 max-w-sm text-sm text-surface-500">{description}</p>

      {onRetry && (
        <Button
          variant="outline"
          size="sm"
          className="mt-5"
          onClick={onRetry}
          isLoading={isRetrying}
          leadingIcon={<RefreshCw className="size-4" />}
        >
          Try again
        </Button>
      )}
    </div>
  )
}
