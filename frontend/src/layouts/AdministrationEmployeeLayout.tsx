import { Building2 } from 'lucide-react'
import { useState } from 'react'
import { Outlet } from 'react-router-dom'

import { SkipLink } from '@/components/common/SkipLink'
import { AdministrationTopbar } from '@/components/layout/AdministrationTopbar'
import { Sidebar } from '@/components/layout/Sidebar'
import { administrationEmployeeNavigationSections } from '@/features/administration/administrationNavigation'
import { useAppearance } from '@/hooks/useAppearance'
import { useDeskTheme } from '@/hooks/useDeskTheme'
import { cn } from '@/utils/cn'

/**
 * Employee shell for the Administration help desk: the standard employee sidebar with Administration
 * navigation, the amber accent, and a link back to the desk chooser.
 */
export function AdministrationEmployeeLayout() {
  const [isNavigationOpen, setNavigationOpen] = useState(false)
  const { sidebarCollapsed } = useAppearance()
  useDeskTheme('administration')

  return (
    <div data-desk="administration" className="min-h-screen bg-surface-100">
      <SkipLink />
      <Sidebar
        isOpen={isNavigationOpen}
        onClose={() => setNavigationOpen(false)}
        sections={administrationEmployeeNavigationSections}
        brandIcon={Building2}
        footerText="Administration Help Desk"
      />

      <div className={cn(sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64')}>
        <AdministrationTopbar variant="employee" onOpenNavigation={() => setNavigationOpen(true)} />

        <main id="main-content" tabIndex={-1} className="mx-auto w-full max-w-7xl px-4 py-6 outline-none lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
