import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import type { ReactNode } from 'react'

import { AppearanceProvider } from '@/contexts/AppearanceProvider'
import { AuthProvider } from '@/contexts/AuthProvider'
import { ToastProvider } from '@/contexts/ToastProvider'
import { useLiveRefreshFocusTracking } from '@/hooks/useLiveRefreshGuard'
import { AppRoutes } from '@/routes/AppRoutes'
import { ApiError } from '@/types/api'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Prefer cached data longer so SQL Server round-trips do not freeze navigation.
      staleTime: 45_000,
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
      retry: (failureCount, error) => {
        if (error instanceof ApiError && (error.isUnauthenticated || error.isForbidden)) {
          return false
        }

        return failureCount < 1
      },
    },
  },
})

function LiveRefreshGuard({ children }: { children: ReactNode }) {
  useLiveRefreshFocusTracking()
  return children
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppearanceProvider>
        <BrowserRouter>
          <AuthProvider>
            <ToastProvider>
              <LiveRefreshGuard>
                <AppRoutes />
              </LiveRefreshGuard>
            </ToastProvider>
          </AuthProvider>
        </BrowserRouter>
      </AppearanceProvider>
    </QueryClientProvider>
  )
}
