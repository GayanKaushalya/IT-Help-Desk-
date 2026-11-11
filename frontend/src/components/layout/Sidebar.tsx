import { ChevronsLeft, ChevronsRight, LifeBuoy, X } from 'lucide-react'
import type { ComponentType } from 'react'
import { NavLink } from 'react-router-dom'

import { navigationSections, type NavigationSection } from '@/components/layout/navigation'
import { sidebarPeek } from '@/components/layout/sidebarPeek'
import { env } from '@/config/env'
import { useAppearance } from '@/hooks/useAppearance'
import { useAuth } from '@/hooks/useAuth'
import { cn } from '@/utils/cn'

export interface SidebarProps {
  /** Mobile drawer state; the sidebar is always visible from lg upwards. */
  isOpen: boolean
  onClose: () => void
  /** Overrides the default IT navigation, e.g. for the HR employee area. */
  sections?: NavigationSection[]
  /** Brand icon shown in the header; defaults to the IT life-buoy. */
  brandIcon?: ComponentType<{ className?: string }>
  footerText?: string
}

export function Sidebar({
  isOpen,
  onClose,
  sections: sectionsProp = navigationSections,
  brandIcon: BrandIcon = LifeBuoy,
  footerText = 'Enterprise IT Service Management',
}: SidebarProps) {
  const { user, hasRole, hasPermission } = useAuth()
  const { sidebarCollapsed, toggleSidebar } = useAppearance()

  const sections = sectionsProp
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (!user) return !item.roles && !item.permission
        if (item.roles && !hasRole(...item.roles)) return false
        if (item.permission && !hasPermission(item.permission)) return false
        return true
      }),
    }))
    .filter((section) => section.items.length > 0)

  return (
    <>
      {isOpen && (
        <div
          className="fixed inset-0 z-30 animate-fade-in bg-black/40 lg:hidden"
          onClick={onClose}
          aria-hidden
        />
      )}

      <aside
        aria-label="Employee navigation"
        className={cn(
          'group/nav fixed inset-y-0 left-0 z-40 flex flex-col border-r border-white/10 bg-sidebar',
          'transition-[width,transform,box-shadow] duration-200 ease-out lg:translate-x-0',
          isOpen ? 'translate-x-0' : '-translate-x-full',
          sidebarCollapsed ? sidebarPeek.rail : 'w-64',
        )}
      >
        <div
          className={cn(
            'flex h-16 shrink-0 items-center border-b border-white/10',
            sidebarCollapsed ? sidebarPeek.header : 'justify-between gap-2 px-5',
          )}
        >
          <div className="flex items-center gap-2.5">
            <span className="flex size-8 items-center justify-center rounded-lg bg-primary-600">
              <BrandIcon className="size-4.5 text-white" />
            </span>
            <span className={cn('text-sm font-semibold tracking-tight text-white', sidebarCollapsed && sidebarPeek.label)}>
              {env.appName}
            </span>
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close navigation"
            className="rounded-md p-1 text-surface-400 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
          >
            <X className="size-5" />
          </button>
        </div>

        <nav className={cn('flex-1 space-y-6 overflow-y-auto py-5', sidebarCollapsed ? sidebarPeek.nav : 'px-3')}>
          {sections.map((section) => (
            <div key={section.id}>
              <p
                className={cn(
                  'px-3 pb-2 text-[11px] font-semibold tracking-wider text-white/40 uppercase',
                  sidebarCollapsed && sidebarPeek.block,
                )}
              >
                {section.label}
              </p>

              <ul className="space-y-0.5">
                {section.items.map((item) => (
                  <li key={item.to}>
                    <NavLink
                      to={item.to}
                      end={item.end}
                      onClick={onClose}
                      title={item.label}
                      className={({ isActive }) =>
                        cn(
                          'flex items-center gap-3 rounded-lg py-2 text-sm font-medium',
                          'transition-colors duration-150',
                          sidebarCollapsed ? sidebarPeek.item : 'px-3',
                          isActive
                            ? 'bg-primary-600 text-white'
                            : 'text-white/70 hover:bg-white/10 hover:text-white',
                        )
                      }
                    >
                      <item.icon className="size-4.5 shrink-0" />
                      <span className={cn('truncate', sidebarCollapsed && sidebarPeek.label)}>{item.label}</span>
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </nav>

        <div className={cn('shrink-0 border-t border-white/10 py-4', sidebarCollapsed ? sidebarPeek.footer : 'px-5')}>
          <p className={cn('text-[11px] text-white/45', sidebarCollapsed && sidebarPeek.block)}>
            {footerText}
          </p>
          <button
            type="button"
            onClick={toggleSidebar}
            aria-label={sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation'}
            className={cn(
              'hidden rounded-md p-2 text-surface-400 transition-colors hover:bg-white/10 hover:text-white lg:flex',
              sidebarCollapsed ? sidebarPeek.collapseBtn : 'mt-2',
            )}
          >
            {sidebarCollapsed ? <ChevronsRight className="size-4" /> : <ChevronsLeft className="size-4" />}
          </button>
        </div>
      </aside>
    </>
  )
}
