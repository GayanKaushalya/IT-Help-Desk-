import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { AdminSidebar } from '@/components/layout/AdminSidebar'
import { AdminTopbar } from '@/components/layout/AdminTopbar'
import { NewTicketAlertHost } from '@/features/notifications/NewTicketAlertHost'
import { useAppearance } from '@/hooks/useAppearance'
import { cn } from '@/utils/cn'

/**
 * Separate shell for the IT administration platform.
 */
export function AdminLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()

  return (
    <div className="min-h-screen bg-surface-100">
      <SkipLink />
      <AdminSidebar isOpen={isNavigationOpen} onClose={() => setNavigationOpen(false)} />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <AdminTopbar onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-[88rem] px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>

      <NewTicketAlertHost />
    </div>
  )
}
