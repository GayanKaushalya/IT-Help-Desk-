import { Navigate } from 'react-router-dom'

import { LoadingPanel } from '@/components/ui/Spinner'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { homePathFor } from '@/utils/roles'

/**
 * App entry (`/`). Guests always see the login screen; signed-in users go to
 * their landing page (IT operators → IT desk, everyone else → IT / HR cards).
 */
export function RootEntry() {
  const { isAuthenticated, isLoading, user } = useAuth()

  if (isLoading) {
    return <LoadingPanel label="Loading…" />
  }

  if (!isAuthenticated) {
    return <Navigate to={paths.auth.login} replace />
  }

  return <Navigate to={homePathFor(user)} replace />
}
