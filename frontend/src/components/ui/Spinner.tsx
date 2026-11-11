import { Loader2 } from 'lucide-react'

import { cn } from '@/utils/cn'

const SIZE_CLASSES = {
  sm: 'size-4',
  md: 'size-6',
  lg: 'size-8',
} as const

export interface SpinnerProps {
  size?: keyof typeof SIZE_CLASSES
  className?: string
  label?: string
}

export function Spinner({ size = 'md', className, label = 'Loading' }: SpinnerProps) {
  return (
    <span role="status" aria-label={label} className="inline-flex">
      <Loader2 className={cn('animate-spin text-primary-600', SIZE_CLASSES[size], className)} />
    </span>
  )
}

/** Preferred name used by feature screens; Spinner remains the primitive. */
export const LoadingSpinner = Spinner

/** Centred spinner for filling a page or panel while data loads. */
export function LoadingPanel({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-surface-500">
      <Spinner size="lg" />
      <p className="text-sm">{label}</p>
    </div>
  )
}
