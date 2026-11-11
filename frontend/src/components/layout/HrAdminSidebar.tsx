import { ChevronsLeft, ChevronsRight, Users, X } from 'lucide-react'
import { NavLink, useLocation } from 'react-router-dom'

import { sidebarPeek } from '@/components/layout/sidebarPeek'
import { env } from '@/config/env'
import { hrAdminNavigationSections } from '@/features/hr/hrNavigation'
import { useAppearance } from '@/hooks/useAppearance'
import { useAuth } from '@/hooks/useAuth'
import { cn } from '@/utils/cn'

export interface HrAdminSidebarProps {
  isOpen: boolean
  onClose: () => void
}

export function HrAdminSidebar({ isOpen, onClose }: HrAdminSidebarProps) {
  const { user, hasPermission, hasRole } = useAuth()
  const location = useLocation()
  const { sidebarCollapsed, toggleSidebar } = useAppearance()

  const sections = hrAdminNavigationSections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (item.roles && !hasRole(...item.roles)) return false
        return !item.permission || !user || hasPermission(item.permission)
      }),
    }))
    .filter((section) => section.items.length > 0)

  // The most specific matching link wins, so "My Assigned" does not also
  // light up "All Requests" and the dashboard link only matches exactly.
  const allItems = sections.flatMap((section) => section.items)
  const matches = allItems.filter(
    (item) => location.pathname === item.to || location.pathname.startsWith(`${item.to}/`),
  )
  const activeTo = matches.sort((a, b) => b.to.length - a.to.length)[0]?.to

  return (
    <>
      {isOpen && (
        <div className="fixed inset-0 z-30 animate-fade-in bg-black/50 lg:hidden" onClick={onClose} aria-hidden />
      )}

      <aside
        aria-label="HR service desk navigation"
        className={cn(
          'group/nav fixed inset-y-0 left-0 z-40 flex flex-col bg-sidebar',
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
              <Users className="size-4 text-white" />
            </span>
            <div className={cn('min-w-0 leading-tight', sidebarCollapsed && sidebarPeek.block)}>
              <p className="truncate text-sm font-semibold text-white">{env.appName}</p>
              <p className="text-[11px] tracking-wide text-white/50 uppercase">HR Service Desk</p>
            </div>
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
                {section.items.map((item) => {
                  const isActive = item.to === activeTo

                  return (
                    <li key={item.to}>
                      <NavLink
                        to={item.to}
                        onClick={onClose}
                        title={item.label}
                        className={cn(
                          'flex items-center gap-3 rounded-lg py-2 text-sm font-medium',
                          'transition-colors duration-150',
                          sidebarCollapsed ? sidebarPeek.item : 'px-3',
                          isActive
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-white/70 hover:bg-white/10 hover:text-white',
                        )}
                      >
                        <item.icon className="size-4.5 shrink-0" />
                        <span className={cn('truncate', sidebarCollapsed && sidebarPeek.label)}>{item.label}</span>
                      </NavLink>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}
        </nav>

        <div className={cn('shrink-0 border-t border-white/10 py-4', sidebarCollapsed ? sidebarPeek.footer : 'px-5')}>
          <p className={cn('text-[11px] font-medium text-white/70', sidebarCollapsed && sidebarPeek.block)}>
            {user?.role?.name ?? 'HR Staff'}
          </p>
          <p className={cn('mt-0.5 text-[11px] text-white/45', sidebarCollapsed && sidebarPeek.block)}>
            Human resources service management
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
