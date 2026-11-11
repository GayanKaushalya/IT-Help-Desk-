/**
 * Display formatting helpers.
 *
 * Kept out of components so dates and numbers read the same everywhere.
 */

const DATE_TIME_FORMATTER = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const DATE_FORMATTER = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
})

const RELATIVE_FORMATTER = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 60 * 60],
  ['month', 30 * 24 * 60 * 60],
  ['day', 24 * 60 * 60],
  ['hour', 60 * 60],
  ['minute', 60],
  ['second', 1],
]

export function greetingFor(date: Date = new Date()): 'Good morning' | 'Good afternoon' | 'Good evening' {
  const hour = date.getHours()

  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'

  return 'Good evening'
}

export function formatDateTime(value: string | Date | null | undefined): string {
  if (!value) return '—'

  const date = value instanceof Date ? value : new Date(value)

  return Number.isNaN(date.getTime()) ? '—' : DATE_TIME_FORMATTER.format(date)
}

export function formatDate(value: string | Date | null | undefined): string {
  if (!value) return '—'

  const date = value instanceof Date ? value : new Date(value)

  return Number.isNaN(date.getTime()) ? '—' : DATE_FORMATTER.format(date)
}

/** e.g. "3 hours ago", "in 2 days". */
export function formatRelativeTime(value: string | Date | null | undefined): string {
  if (!value) return '—'

  const date = value instanceof Date ? value : new Date(value)

  if (Number.isNaN(date.getTime())) return '—'

  const deltaSeconds = (date.getTime() - Date.now()) / 1000

  for (const [unit, secondsInUnit] of RELATIVE_UNITS) {
    if (Math.abs(deltaSeconds) >= secondsInUnit || unit === 'second') {
      return RELATIVE_FORMATTER.format(Math.round(deltaSeconds / secondsInUnit), unit)
    }
  }

  return '—'
}

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`

  const units = ['KB', 'MB', 'GB']
  let value = bytes / 1024
  let unitIndex = 0

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024
    unitIndex += 1
  }

  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`
}

/** Minutes from ticket creation to resolution; null if not yet resolved. */
export function resolveTimeMinutes(
  createdAt: string | Date | null | undefined,
  resolvedAt: string | Date | null | undefined,
): number | null {
  if (!createdAt || !resolvedAt) return null

  const created = new Date(createdAt).getTime()
  const resolved = new Date(resolvedAt).getTime()
  if (Number.isNaN(created) || Number.isNaN(resolved) || resolved < created) return null

  return Math.round((resolved - created) / 60_000)
}

/** Converts SLA minutes into a compact "2h 30m" style duration. */
export function formatDuration(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) return '—'
  if (minutes < 60) return `${minutes}m`

  const hours = Math.floor(minutes / 60)
  const remainder = minutes % 60

  if (hours < 24) return remainder === 0 ? `${hours}h` : `${hours}h ${remainder}m`

  const days = Math.floor(hours / 24)
  const remainingHours = hours % 24

  return remainingHours === 0 ? `${days}d` : `${days}d ${remainingHours}h`
}
