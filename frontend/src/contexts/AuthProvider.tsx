import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'

import { AuthContext, type AuthContextValue } from '@/contexts/AuthContext'
import { paths } from '@/routes/paths'
import { setUnauthorizedHandler, tokenStorage } from '@/services/apiClient'
import { authService } from '@/services/authService'
import { ApiError } from '@/types/api'
import type { LoginCredentials, RegisterPayload } from '@/types/auth'
import type { RoleSlug, User } from '@/types/models'

/**
 * Owns the signed-in user for the whole application.
 *
 * On startup a stored token is verified against /auth/me. If the API later
 * reports UNAUTHENTICATED, the session is dropped and the user is returned
 * to the login screen.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(() => Boolean(tokenStorage.get()))
  const restoring = useRef(true)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const clearSession = useCallback(() => {
    tokenStorage.clear()
    setUser(null)
  }, [])

  const applySession = useCallback(
    (nextUser: User, token: string, remember: boolean) => {
      // Drop cached data from any previous account so screens do not show stale tickets.
      queryClient.clear()
      tokenStorage.set(token, remember)
      setUser(nextUser)
    },
    [queryClient],
  )

  const login = useCallback(
    async (credentials: LoginCredentials) => {
      const result = await authService.login(credentials)
      applySession(result.user, result.token, credentials.remember)
      return result.user
    },
    [applySession],
  )

  const register = useCallback(
    async (payload: RegisterPayload) => {
      const result = await authService.register(payload)
      applySession(result.user, result.token, false)
      return result.user
    },
    [applySession],
  )

  const refreshUser = useCallback(async () => {
    const currentUser = await authService.me()
    setUser(currentUser)
    return currentUser
  }, [])

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } catch (error) {
      // The token is discarded locally even if the revoke call fails, so a
      // network blip cannot leave the user stuck in a signed-in shell.
      if (!(error instanceof ApiError && error.isUnauthenticated)) {
        console.warn('Sign-out request failed; the local session was still cleared.')
      }
    } finally {
      queryClient.clear()
      clearSession()
    }
  }, [clearSession, queryClient])

  const hasRole = useCallback(
    (...roles: RoleSlug[]) => (user?.role ? roles.includes(user.role.slug) : false),
    [user],
  )

  const hasPermission = useCallback(
    (permission: string) => {
      if (!user?.role) return false
      if (user.role.slug === 'super-admin') return true

      return user.role.permissions?.includes(permission) ?? false
    },
    [user],
  )

  useEffect(() => {
    let cancelled = false

    async function restoreSession(): Promise<void> {
      const token = tokenStorage.get()

      if (!token) {
        restoring.current = false
        setIsLoading(false)
        return
      }

      try {
        const currentUser = await authService.me()
        if (!cancelled) {
          setUser(currentUser)
        }
      } catch {
        if (!cancelled) {
          clearSession()
        }
      } finally {
        if (!cancelled) {
          restoring.current = false
          setIsLoading(false)
        }
      }
    }

    void restoreSession()

    return () => {
      cancelled = true
    }
  }, [clearSession])

  useEffect(() => {
    setUnauthorizedHandler(() => {
      queryClient.clear()
      clearSession()

      if (!restoring.current) {
        navigate(paths.auth.login, { replace: true, state: { reason: 'expired' } })
      }
    })

    return () => setUnauthorizedHandler(null)
  }, [clearSession, navigate, queryClient])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isAuthenticated: user !== null,
      isLoading,
      login,
      register,
      logout,
      refreshUser,
      clearSession,
      hasRole,
      hasPermission,
    }),
    [user, isLoading, login, register, logout, refreshUser, clearSession, hasRole, hasPermission],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
