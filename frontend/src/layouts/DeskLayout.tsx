import { LifeBuoy } from 'lucide-react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { UserMenu } from '@/components/layout/UserMenu'
import { env } from '@/config/env'

/**
 * Minimal shell for the help desk chooser: no module navigation, so it does
 * not favour the IT or HR desk.
 */
export function DeskLayout() {
  return (
    <div className="min-h-screen bg-surface-100">
      <SkipLink />

      <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-surface-200 bg-panel/95 px-4 backdrop-blur lg:px-8">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-lg bg-primary-600">
            <LifeBuoy className="size-4 text-white" />
          </span>
          <span className="text-sm font-semibold tracking-tight text-surface-900">{env.appName}</span>
        </div>

        <div className="ml-auto flex items-center gap-1 sm:gap-2">
          <ThemeToggle />
          <div className="mx-1 hidden h-6 w-px bg-surface-200 sm:block" />
          <UserMenu />
        </div>
      </header>

      <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-5xl px-4 py-10 outline-none lg:px-8 lg:py-16">
        <Outlet />
      </main>
    </div>
  )
}
