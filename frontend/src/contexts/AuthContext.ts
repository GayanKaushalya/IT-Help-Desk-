import { createContext } from 'react'

import type { LoginCredentials, RegisterPayload } from '@/types/auth'
import type { RoleSlug, User } from '@/types/models'

export interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  /** True while an existing token is being verified with the API. */
  isLoading: boolean
  login: (credentials: LoginCredentials) => Promise<User>
  register: (payload: RegisterPayload) => Promise<User>
  logout: () => Promise<void>
  refreshUser: () => Promise<User | null>
  clearSession: () => void
  hasRole: (...roles: RoleSlug[]) => boolean
  hasPermission: (permission: string) => boolean
}

/** Kept apart from AuthProvider so the provider file only exports a component. */
export const AuthContext = createContext<AuthContextValue | null>(null)
