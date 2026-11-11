import { useLocation, useNavigate } from 'react-router-dom'

import { Button } from '@/components/ui/Button'
import { paths } from '@/routes/paths'

export function NotFoundPage() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const isAdmin = pathname.startsWith('/admin')

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
      <p className="text-sm font-semibold text-primary-600">404</p>
      <h1 className="mt-2 text-2xl font-semibold tracking-tight text-surface-900">Page not found</h1>
      <p className="mt-2 max-w-sm text-sm text-surface-500">
        The page you are looking for does not exist or you no longer have access to it.
      </p>

      <Button className="mt-6" onClick={() => void navigate(isAdmin ? paths.admin.index : paths.dashboard)}>
        Back to dashboard
      </Button>
    </div>
  )
}
