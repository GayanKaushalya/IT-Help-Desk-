import { apiClient } from '@/services/apiClient'
import type { HealthStatus, Lookups, PublicLookups } from '@/types/models'

/**
 * Endpoints that are available before any module-specific functionality.
 */
export const systemService = {
  health: (): Promise<HealthStatus> => apiClient.get<HealthStatus>('/health'),

  /** Branches and departments; callable without a session. */
  publicLookups: (): Promise<PublicLookups> => apiClient.get<PublicLookups>('/lookups/public'),

  /** Full reference catalogue; requires an authenticated, active account. */
  lookups: (): Promise<Lookups> => apiClient.get<Lookups>('/lookups'),
}
