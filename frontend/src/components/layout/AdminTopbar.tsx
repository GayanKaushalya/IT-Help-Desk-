import { Menu, Shield } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'

import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { TopbarSearch } from '@/components/layout/TopbarSearch'
import { UserMenu } from '@/components/layout/UserMenu'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { env } from '@/config/env'
import { paths } from '@/routes/paths'

export interface AdminTopbarProps {
  onOpenNavigation: () => void
}

export function AdminTopbar({ onOpenNavigation }: AdminTopbarProps) {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')

  function handleSearch(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault()
    const value = query.trim()
    if (!value) return
    navigate(`${paths.admin.tickets}?search=${encodeURIComponent(value)}`)
  }

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-surface-200 bg-panel/95 px-4 backdrop-blur sm:gap-3 lg:px-8">
      <button
        type="button"
        onClick={onOpenNavigation}
        aria-label="Open navigation"
        className="rounded-md p-2 text-surface-500 transition-colors hover:bg-surface-100 hover:text-surface-900 lg:hidden"
      >
        <Menu className="size-5" />
      </button>

      <div className="hidden items-center gap-2 min-[420px]:flex lg:hidden">
        <span className="flex size-8 items-center justify-center rounded-lg bg-primary-600">
          <Shield className="size-4 text-white" />
        </span>
        <span className="text-sm font-semibold tracking-tight text-surface-900">{env.appName}</span>
      </div>

      <TopbarSearch
        value={query}
        onChange={setQuery}
        onSubmit={handleSearch}
        placeholder="Search ticket number or subject…"
        label="Search tickets"
      />

      <div className="ml-auto flex items-center gap-1 sm:gap-2">
        <span className="hidden rounded-full bg-surface-100 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-surface-600 uppercase lg:inline">
          Operations
        </span>

        <ThemeToggle />
        <NotificationBell desk="it" audience="staff" />

        <div className="mx-1 hidden h-6 w-px bg-surface-200 sm:block" />
        <UserMenu profilePath={paths.profile} />
      </div>
    </header>
  )
}
