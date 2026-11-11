import { useQuery } from '@tanstack/react-query'

import { systemService } from '@/services/systemService'

/**
 * Data hooks for the system/foundation feature.
 *
 * Components consume these rather than calling the service directly, which
 * keeps fetching, caching and retry policy out of the view layer.
 */

export const systemKeys = {
  health: ['system', 'health'] as const,
  publicLookups: ['system', 'lookups', 'public'] as const,
  lookups: ['system', 'lookups'] as const,
}

export function useSystemHealth() {
  return useQuery({
    queryKey: systemKeys.health,
    queryFn: systemService.health,
    // Surfaces an outage quickly without hammering the API.
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
    retry: 1,
  })
}

export function usePublicLookups() {
  return useQuery({
    queryKey: systemKeys.publicLookups,
    queryFn: systemService.publicLookups,
    // Reference data changes rarely, so avoid refetching on every mount.
    staleTime: 5 * 60_000,
  })
}

export function useLookups() {
  return useQuery({
    queryKey: systemKeys.lookups,
    queryFn: systemService.lookups,
    staleTime: 5 * 60_000,
  })
}
