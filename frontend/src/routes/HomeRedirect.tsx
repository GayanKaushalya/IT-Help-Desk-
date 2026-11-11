import { Navigate } from 'react-router-dom'

import { useAuth } from '@/hooks/useAuth'
import { homePathFor } from '@/utils/roles'

/** Sends the signed-in user to the landing page for their role. */
export function HomeRedirect() {
  const { user } = useAuth()

  return <Navigate to={homePathFor(user)} replace />
}
