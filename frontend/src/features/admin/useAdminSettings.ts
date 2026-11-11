import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { systemKeys } from '@/features/system/useSystemStatus'
import { adminService, type SettingsPayload } from '@/services/adminService'

export const settingsKeys = {
  all: ['admin', 'settings'] as const,
}

export function useAdminSettings() {
  return useQuery({
    queryKey: settingsKeys.all,
    queryFn: adminService.settings,
  })
}

export function useUpdateAdminSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SettingsPayload) => adminService.updateSettings(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: settingsKeys.all })
      void queryClient.invalidateQueries({ queryKey: systemKeys.publicLookups })
    },
  })
}
