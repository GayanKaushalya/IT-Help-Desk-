import { Search } from 'lucide-react'
import type { FormEvent } from 'react'

export function TopbarSearch({
  value,
  onChange,
  onSubmit,
  placeholder,
  label,
}: {
  value: string
  onChange: (value: string) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  placeholder: string
  label: string
}) {
  return (
    <form onSubmit={onSubmit} className="relative min-w-0 max-w-md flex-1">
      <Search
        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-surface-400"
        aria-hidden
      />
      <input
        type="search"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        aria-label={label}
        className="h-9 w-full rounded-lg border border-surface-200 bg-surface-50 pl-9 text-sm text-surface-900 placeholder:text-surface-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
      />
    </form>
  )
}
