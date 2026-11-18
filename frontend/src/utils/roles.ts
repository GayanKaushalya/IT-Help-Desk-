import { paths } from '@/routes/paths'
import type { RoleSlug, User } from '@/types/models'

/** Roles that may enter the IT administration area. */
export const STAFF_ROLES: RoleSlug[] = [
  'technician',
  'supervisor',
  'administrator',
  'super-admin',
]

/** IT Support, IT Admin and Super Admin - the only roles that may open /admin. */
export const DESK_ROLES: RoleSlug[] = ['technician', 'administrator', 'super-admin']

/** Roles that may open the HR administration area (/hr/admin). */
export const HR_DESK_ROLES: RoleSlug[] = [
  'hr-officer',
  'hr-manager',
  'confidential-complaint-handler',
  'administrator',
  'super-admin',
]

/** Roles that may open the Administration help desk area (/administration/admin). */
export const ADM_DESK_ROLES: RoleSlug[] = [
  'administration-officer',
  'administration-manager',
  'insurance-officer',
  'fleet-officer',
  'security-officer',
  'cctv-approver',
  'administrator',
  'super-admin',
]

/** Roles that may manage users, catalogue data and system settings. */
export const ADMIN_ROLES: RoleSlug[] = ['administrator', 'super-admin']

export function isStaffRole(slug: RoleSlug | undefined): boolean {
  return slug !== undefined && STAFF_ROLES.includes(slug)
}

export function isDeskRole(slug: RoleSlug | undefined): boolean {
  return slug !== undefined && DESK_ROLES.includes(slug)
}

export function isHrDeskRole(slug: RoleSlug | undefined): boolean {
  return slug !== undefined && HR_DESK_ROLES.includes(slug)
}

export function isAdministrationDeskRole(slug: RoleSlug | undefined): boolean {
  return slug !== undefined && ADM_DESK_ROLES.includes(slug)
}

/**
 * Landing page after a successful sign-in.
 *
 * Technicians open the IT service desk directly. Administrators also land on
 * IT, but may switch into the HR service desk with full admin rights.
 * Everyone else always sees the IT / HR help desk cards.
 */
export function homePathFor(user: User | null): string {
  const slug = user?.role?.slug

  // Dedicated IT operators go straight into the IT service desk.
  if (slug === 'technician' || slug === 'administrator') {
    return paths.admin.index
  }

  return paths.desks
}

/** Deep links that may skip the desk chooser after login. */
export function isDeepLinkPath(path: string | undefined): boolean {
  if (!path || path === paths.root || path === paths.auth.login || path === paths.desks) {
    return false
  }

  return (
    path.startsWith('/tickets/') ||
    path.startsWith('/hr/requests/') ||
    path.startsWith('/administration/requests/') ||
    path.startsWith('/admin/tickets/') ||
    path.startsWith('/hr/admin/requests/') ||
    path.startsWith('/administration/admin/requests/') ||
    path.startsWith('/knowledge-base/')
  )
}
