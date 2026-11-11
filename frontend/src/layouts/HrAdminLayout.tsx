import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { HrAdminSidebar } from '@/components/layout/HrAdminSidebar'
import { HrTopbar } from '@/components/layout/HrTopbar'
import { NewHrRequestAlertHost } from '@/features/notifications/NewHrRequestAlertHost'
import { useAppearance } from '@/hooks/useAppearance'
import { useDeskTheme } from '@/hooks/useDeskTheme'
import { cn } from '@/utils/cn'

/**
 * Shell for the HR administration platform. Same structure as AdminLayout,
 * with the green HR accent applied through data-desk="hr".
 */
export function HrAdminLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()
  useDeskTheme('hr')

  return (
    <div data-desk="hr" className="min-h-screen bg-surface-100">
      <SkipLink />
      <HrAdminSidebar isOpen={isNavigationOpen} onClose={() => setNavigationOpen(false)} />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <HrTopbar variant="admin" onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-[88rem] px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>

      <NewHrRequestAlertHost />
    </div>
  )
}
