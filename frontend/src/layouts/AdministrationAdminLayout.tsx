import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { AdministrationAdminSidebar } from '@/components/layout/AdministrationAdminSidebar'
import { AdministrationTopbar } from '@/components/layout/AdministrationTopbar'
import { NewAdmRequestAlertHost } from '@/features/notifications/NewAdmRequestAlertHost'
import { useAppearance } from '@/hooks/useAppearance'
import { useDeskTheme } from '@/hooks/useDeskTheme'
import { cn } from '@/utils/cn'

/**
 * Shell for the Administration service desk. Same structure as HrAdminLayout,
 * with the amber Administration accent applied through data-desk="administration".
 */
export function AdministrationAdminLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()
  useDeskTheme('administration')

  return (
    <div data-desk="administration" className="min-h-screen bg-surface-100">
      <SkipLink />
      <AdministrationAdminSidebar isOpen={isNavigationOpen} onClose={() => setNavigationOpen(false)} />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <AdministrationTopbar variant="admin" onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-[88rem] px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>

      <NewAdmRequestAlertHost />
    </div>
  )
}
