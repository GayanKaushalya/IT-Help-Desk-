import { apiClient } from '@/services/apiClient'
import type { LoginCredentials, LoginResult, RegisterPayload } from '@/types/auth'
import type { User } from '@/types/models'

/**
 * Every call the SPA makes to the authentication endpoints.
 *
 * Components and hooks go through here rather than touching the HTTP client,
 * so request shapes stay in one place.
 */
export const authService = {
  login: (credentials: LoginCredentials): Promise<LoginResult> =>
    apiClient.post<LoginResult>('/auth/login', credentials),

  register: (payload: RegisterPayload): Promise<LoginResult> =>
    apiClient.post<LoginResult>('/auth/register', payload),

  /** Resolves the signed-in user for the token currently in storage. */
  me: (): Promise<User> => apiClient.get<User>('/auth/me'),

  logout: (): Promise<null> => apiClient.post<null>('/auth/logout'),
}
