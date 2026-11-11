import {
  Building2,
  FileBarChart2,
  Inbox,
  LayoutDashboard,
  LayoutGrid,
  ListChecks,
  ScrollText,
  Settings,
  Shapes,
  Users,
} from 'lucide-react'

import type { NavigationSection } from '@/components/layout/navigation'
import { paths } from '@/routes/paths'

export const adminNavigationSections: NavigationSection[] = [
  {
    id: 'desk',
    label: 'Service desk',
    items: [
      { label: 'Dashboard', to: paths.admin.index, icon: LayoutDashboard, permission: 'tickets.view_any' },
      { label: 'Tickets', to: paths.admin.tickets, icon: Inbox, permission: 'tickets.view_any' },
      {
        label: 'My Assigned Tickets',
        to: paths.admin.assigned,
        icon: ListChecks,
        permission: 'tickets.view_any',
      },
    ],
  },
  {
    id: 'organisation',
    label: 'Organisation',
    items: [
      { label: 'Users', to: paths.admin.users, icon: Users, permission: 'users.view' },
      { label: 'Branches', to: paths.admin.branches, icon: Building2, permission: 'branches.manage' },
      { label: 'Categories', to: paths.admin.categories, icon: Shapes, permission: 'categories.manage' },
    ],
  },
  {
    id: 'governance',
    label: 'Governance',
    items: [
      { label: 'Reports', to: paths.admin.reports, icon: FileBarChart2, permission: 'reports.view' },
      { label: 'Audit Logs', to: paths.admin.auditLogs, icon: ScrollText, permission: 'audit.view' },
      { label: 'Settings', to: paths.admin.settings, icon: Settings, permission: 'settings.manage' },
    ],
  },
  {
    id: 'desks',
    label: 'Help desks',
    items: [
      {
        label: 'Switch Help Desk',
        to: paths.desks,
        icon: LayoutGrid,
        roles: ['technician', 'supervisor', 'administrator', 'super-admin'],
      },
      {
        label: 'HR Service Desk',
        to: paths.hr.admin.index,
        icon: Users,
        permission: 'hr.requests.view_any',
        roles: ['administrator', 'super-admin'],
      },
      {
        label: 'HR Help Desk',
        to: paths.hr.index,
        icon: Users,
        permission: 'hr.requests.create',
        roles: ['technician', 'supervisor'],
      },
    ],
  },
]
