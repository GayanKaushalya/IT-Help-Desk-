import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'

import { LoadingPanel } from '@/components/ui/Spinner'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import type { RoleSlug } from '@/types/models'
import { homePathFor } from '@/utils/roles'

export interface ProtectedRouteProps {
  children: ReactNode
  /** When set, the user must hold one of these roles. */
  roles?: RoleSlug[]
  /** When set, the user must hold this specific permission. */
  permission?: string
}

/**
 * Route guard mirroring the backend middleware.
 *
 * Role and permission checks here only hide the UI. The API still rejects
 * unauthorized requests independently.
 */
export function ProtectedRoute({ children, roles, permission }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, hasRole, hasPermission, user } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <LoadingPanel label="Restoring your session…" />
  }

  if (!isAuthenticated) {
    return <Navigate to={paths.auth.login} state={{ from: location.pathname }} replace />
  }

  const roleAllowed = !roles || hasRole(...roles)
  const permissionAllowed = !permission || hasPermission(permission)

  if (!roleAllowed || !permissionAllowed) {
    return <Navigate to={homePathFor(user)} replace />
  }

  return children
}
