import { cn } from '@/utils/cn'

const SIZE_CLASSES = {
  sm: 'size-7 text-[11px]',
  md: 'size-9 text-xs',
  lg: 'size-12 text-sm',
} as const

export interface AvatarProps {
  name: string
  size?: keyof typeof SIZE_CLASSES
  className?: string
}

/** Builds up to two initials from a full name. */
function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)

  if (parts.length === 0) return '?'

  const first = parts[0]?.charAt(0) ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.charAt(0) ?? '') : ''

  return (first + last).toUpperCase()
}

export function Avatar({ name, size = 'md', className }: AvatarProps) {
  return (
    <span
      title={name}
      aria-label={name}
      role="img"
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full',
        'bg-primary-100 font-semibold text-primary-700 select-none',
        SIZE_CLASSES[size],
        className,
      )}
    >
      {initialsOf(name)}
    </span>
  )
}
