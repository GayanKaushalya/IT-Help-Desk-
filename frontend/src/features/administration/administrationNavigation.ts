import {
  Building2,
  Bus,
  Car,
  Cctv,
  ClipboardList,
  FileBarChart2,
  FileText,
  Fuel,
  Inbox,
  LayoutDashboard,
  LayoutGrid,
  ListChecks,
  Monitor,
  Package,
  PartyPopper,
  Send,
  Settings,
  ShieldCheck,
  Truck,
  UserRound,
  UserSquare2,
  Users,
  Wrench,
  FileCheck2,
} from 'lucide-react'

import type { NavigationSection } from '@/components/layout/navigation'
import { paths } from '@/routes/paths'

const STAFF_PERMISSION = 'adm.requests.view_any'

/** Sidebar for employees using the Administration help desk. */
export const administrationEmployeeNavigationSections: NavigationSection[] = [
  {
    id: 'administration',
    label: 'Administration Help Desk',
    items: [
      { label: 'Administration Home', to: paths.administration.index, icon: Building2, end: true },
      { label: 'Submit Request', to: paths.administration.submit, icon: Send, permission: 'adm.requests.create' },
      { label: 'My Requests', to: paths.administration.requests, icon: FileText, end: true },
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
      { label: 'Profile', to: paths.administration.profile, icon: UserRound },
    ],
  },
]

/**
 * Sidebar for Administration staff. Items are filtered by permission in the sidebar.
 * Category links open All Requests pre-filtered by category; registers, reports
 * and settings are placeholders until their phases ship.
 */
export const administrationAdminNavigationSections: NavigationSection[] = [
  {
    id: 'overview',
    label: 'Overview',
    items: [
      { label: 'Dashboard', to: paths.administration.admin.index, icon: LayoutDashboard, permission: STAFF_PERMISSION },
      { label: 'All Requests', to: paths.administration.admin.requests, icon: Inbox, permission: STAFF_PERMISSION },
      { label: 'My Assigned', to: paths.administration.admin.assigned, icon: ListChecks, permission: STAFF_PERMISSION },
    ],
  },
  {
    id: 'operations',
    label: 'Operations',
    items: [
      {
        label: 'Insurance',
        to: paths.administration.admin.category('insurance-management'),
        icon: ShieldCheck,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Fleet & Vehicles',
        to: paths.administration.admin.category('fleet-vehicle-management'),
        icon: Car,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Fuel Cards',
        to: paths.administration.admin.category('fuel-card-management'),
        icon: Fuel,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'CCTV & Surveillance',
        to: paths.administration.admin.category('cctv-surveillance'),
        icon: Cctv,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Security',
        to: paths.administration.admin.category('security-management'),
        icon: UserSquare2,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Courier & Dispatch',
        to: paths.administration.admin.category('courier-document-dispatch'),
        icon: Truck,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'PickMe & Transport',
        to: paths.administration.admin.category('pickme-transport-booking'),
        icon: Bus,
        permission: STAFF_PERMISSION,
      },
    ],
  },
  {
    id: 'office',
    label: 'Office',
    items: [
      {
        label: 'Building & Maintenance',
        to: paths.administration.admin.category('building-maintenance'),
        icon: Wrench,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Supplies & Equipment',
        to: paths.administration.admin.category('office-supplies-equipment'),
        icon: Package,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'Facilities & Events',
        to: paths.administration.admin.category('office-facilities-events'),
        icon: PartyPopper,
        permission: STAFF_PERMISSION,
      },
      {
        label: 'General Administration',
        to: paths.administration.admin.category('general-administration'),
        icon: ClipboardList,
        permission: STAFF_PERMISSION,
      },
    ],
  },
  {
    id: 'management',
    label: 'Management',
    items: [
      {
        label: 'Insurance Register',
        to: paths.administration.admin.insurance,
        icon: FileCheck2,
        permission: 'adm.registers.view',
      },
      { label: 'Vehicles', to: paths.administration.admin.vehicles, icon: Car, permission: 'adm.registers.view' },
      { label: 'Drivers', to: paths.administration.admin.drivers, icon: Users, permission: 'adm.registers.view' },
      { label: 'Fuel Card Register', to: paths.administration.admin.fuelCards, icon: Fuel, permission: 'adm.registers.view' },
      { label: 'Reports', to: paths.administration.admin.reports, icon: FileBarChart2, permission: 'adm.reports.view' },
      { label: 'Settings', to: paths.administration.admin.settings, icon: Settings, permission: 'adm.settings.manage' },
    ],
  },
  {
    id: 'account',
    label: 'Account',
    items: [{ label: 'Profile', to: paths.administration.profile, icon: UserRound }],
  },
  {
    id: 'desks',
    label: 'Help desks',
    items: [
      {
        label: 'Switch Help Desk',
        to: paths.desks,
        icon: LayoutGrid,
        roles: [
          'administration-officer',
          'administration-manager',
          'insurance-officer',
          'fleet-officer',
          'security-officer',
          'cctv-approver',
          'administrator',
          'super-admin',
        ],
      },
    ],
  },
]
