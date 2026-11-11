import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { Sidebar } from '@/components/layout/Sidebar'
import { Topbar } from '@/components/layout/Topbar'
import { useAppearance } from '@/hooks/useAppearance'
import { cn } from '@/utils/cn'

/**
 * Shell for every signed-in screen: fixed sidebar from lg upwards, drawer
 * below that, with the page rendered into the content column.
 */
export function AppLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()

  return (
    <div className="min-h-screen bg-surface-100">
      <SkipLink />
      <Sidebar isOpen={isNavigationOpen} onClose={() => setNavigationOpen(false)} />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <Topbar onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-7xl px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
