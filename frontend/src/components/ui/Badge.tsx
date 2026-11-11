import type { HTMLAttributes } from 'react'

import type { ColorToken } from '@/types/models'
import { cn } from '@/utils/cn'

const TONE_CLASSES: Record<ColorToken, string> = {
  neutral: 'bg-surface-100 text-surface-700 ring-surface-200',
  primary: 'bg-primary-50 text-primary-700 ring-primary-200',
  success: 'bg-success-50 text-success-700 ring-success-500/20',
  warning: 'bg-warning-50 text-warning-700 ring-warning-500/25',
  danger: 'bg-danger-50 text-danger-700 ring-danger-500/20',
  info: 'bg-info-50 text-info-700 ring-info-500/20',
}

const DOT_CLASSES: Record<ColorToken, string> = {
  neutral: 'bg-surface-400',
  primary: 'bg-primary-500',
  success: 'bg-success-500',
  warning: 'bg-warning-500',
  danger: 'bg-danger-500',
  info: 'bg-info-500',
}

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  /** Matches the `color_token` returned by the API for statuses and priorities. */
  tone?: ColorToken
  /** Shows a leading status dot, used for ticket status pills. */
  withDot?: boolean
  size?: 'sm' | 'md'
}

export function Badge({
  tone = 'neutral',
  withDot = false,
  size = 'md',
  className,
  children,
  ...props
}: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset',
        size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        TONE_CLASSES[tone],
        className,
      )}
      {...props}
    >
      {withDot && <span className={cn('size-1.5 rounded-full', DOT_CLASSES[tone])} aria-hidden />}
      {children}
    </span>
  )
}
