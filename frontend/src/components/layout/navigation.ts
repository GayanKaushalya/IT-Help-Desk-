import { BookOpen, Building2, LayoutDashboard, LayoutGrid, LifeBuoy, Palette, Ticket, UserRound, Users } from 'lucide-react'
import type { ComponentType } from 'react'

import { paths } from '@/routes/paths'
import type { RoleSlug } from '@/types/models'

export interface NavigationItem {
  label: string
  to: string
  icon: ComponentType<{ className?: string }>
  /** Roles allowed to see the link; omitted means everyone. */
  roles?: RoleSlug[]
  /** Permission required to see the link; omitted means no extra check. */
  permission?: string
  /** Match the route exactly; use for index links so they do not stay active on child routes. */
  end?: boolean
}

export interface NavigationSection {
  id: string
  label: string
  items: NavigationItem[]
}

export const navigationSections: NavigationSection[] = [
  {
    id: 'workspace',
    label: 'Workspace',
    items: [
      { label: 'Dashboard', to: paths.dashboard, icon: LayoutDashboard },
      { label: 'My Tickets', to: paths.tickets.index, icon: Ticket, permission: 'tickets.view_own' },
      { label: 'Create Ticket', to: paths.tickets.create, icon: LifeBuoy, permission: 'tickets.create' },
      { label: 'Knowledge Base', to: paths.knowledgeBase, icon: BookOpen },
      { label: 'Profile', to: paths.profile, icon: UserRound },
    ],
  },
  {
    id: 'desks',
    label: 'Help desks',
    items: [
      { label: 'Help Desks', to: paths.desks, icon: LayoutGrid },
      { label: 'HR Help Desk', to: paths.hr.index, icon: Users, permission: 'hr.requests.view_own' },
      { label: 'Administration Help Desk', to: paths.administration.index, icon: Building2, permission: 'adm.requests.view_own' },
    ],
  },
  {
    id: 'reference',
    label: 'Reference',
    items: [{ label: 'Design System', to: paths.designSystem, icon: Palette }],
  },
]
