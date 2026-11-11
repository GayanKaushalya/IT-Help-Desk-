import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'

import { LoadingPanel } from '@/components/ui/Spinner'
import { useAuth } from '@/hooks/useAuth'
import { homePathFor } from '@/utils/roles'

/**
 * Keeps signed-in users off the login and registration screens.
 */
export function GuestRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading, user } = useAuth()

  if (isLoading) {
    return <LoadingPanel label="Checking your session…" />
  }

  if (isAuthenticated) {
    return <Navigate to={homePathFor(user)} replace />
  }

  return children
}
