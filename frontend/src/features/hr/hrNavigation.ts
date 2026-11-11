import {
  AlarmClock,
  FileBarChart2,
  FileText,
  Inbox,
  LayoutDashboard,
  LayoutGrid,
  ListChecks,
  Monitor,
  ScrollText,
  Search,
  Send,
  ShieldAlert,
  Share2,
  UserRound,
  Users,
} from 'lucide-react'

import type { NavigationSection } from '@/components/layout/navigation'
import { paths } from '@/routes/paths'

/** Sidebar for employees using the HR help desk. */
export const hrEmployeeNavigationSections: NavigationSection[] = [
  {
    id: 'hr',
    label: 'HR Help Desk',
    items: [
      { label: 'HR Home', to: paths.hr.index, icon: Users, end: true },
      { label: 'Submit Request', to: paths.hr.submit, icon: Send, permission: 'hr.requests.create' },
      { label: 'My HR Requests', to: paths.hr.requests, icon: FileText, end: true },
      { label: 'Track Request', to: paths.hr.track, icon: Search },
    ],
  },
  {
    id: 'account',
    label: 'Account',
    items: [
      { label: 'Help Desks', to: paths.desks, icon: LayoutGrid },
      {
        label: 'IT Service Desk',
        to: paths.admin.index,
        icon: Monitor,
        roles: ['technician', 'administrator', 'super-admin'],
      },
      { label: 'Profile', to: paths.hr.profile, icon: UserRound },
    ],
  },
]

/** Sidebar for HR staff. Items are filtered by permission in the sidebar. */
export const hrAdminNavigationSections: NavigationSection[] = [
  {
    id: 'hr-desk',
    label: 'HR service desk',
    items: [
      { label: 'Dashboard', to: paths.hr.admin.index, icon: LayoutDashboard, permission: 'hr.requests.view_any' },
      { label: 'All Requests', to: paths.hr.admin.requests, icon: Inbox, permission: 'hr.requests.view_any' },
      { label: 'My Assigned', to: paths.hr.admin.assigned, icon: ListChecks, permission: 'hr.requests.view_any' },
      {
        label: 'Complaints',
        to: paths.hr.admin.complaints,
        icon: ShieldAlert,
        permission: 'hr.requests.view_confidential',
      },
      { label: 'Referrals', to: paths.hr.admin.referrals, icon: Share2, permission: 'hr.referrals.manage' },
      {
        label: 'Reports',
        to: paths.hr.admin.reports,
        icon: FileBarChart2,
        permission: 'hr.reports.view',
      },
      {
        label: 'Audit Logs',
        to: paths.hr.admin.auditLogs,
        icon: ScrollText,
        permission: 'hr.reports.view',
      },
      {
        label: 'SLA Settings',
        to: paths.hr.admin.sla,
        icon: AlarmClock,
        permission: 'hr.sla.manage',
      },
    ],
  },
  {
    id: 'account',
    label: 'Account',
    items: [{ label: 'Profile', to: paths.hr.profile, icon: UserRound }],
  },
  {
    id: 'desks',
    label: 'Help desks',
    items: [
      {
        label: 'Switch Help Desk',
        to: paths.desks,
        icon: LayoutGrid,
        roles: ['hr-officer', 'hr-manager', 'confidential-complaint-handler', 'administrator', 'super-admin'],
      },
    ],
  },
]
