import { Users } from 'lucide-react'
import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { HrTopbar } from '@/components/layout/HrTopbar'
import { Sidebar } from '@/components/layout/Sidebar'
import { hrEmployeeNavigationSections } from '@/features/hr/hrNavigation'
import { useAppearance } from '@/hooks/useAppearance'
import { useDeskTheme } from '@/hooks/useDeskTheme'
import { cn } from '@/utils/cn'

/**
 * Employee shell for the HR help desk: the standard employee sidebar with HR
 * navigation, the green accent, and a link back to the desk chooser.
 */
export function HrEmployeeLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()
  useDeskTheme('hr')

  return (
    <div data-desk="hr" className="min-h-screen bg-surface-100">
      <SkipLink />
      <Sidebar
        isOpen={isNavigationOpen}
        onClose={() => setNavigationOpen(false)}
        sections={hrEmployeeNavigationSections}
        brandIcon={Users}
        footerText="HR Help Desk"
      />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <HrTopbar variant="employee" onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-7xl px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
