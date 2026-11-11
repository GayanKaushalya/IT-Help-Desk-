import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { systemKeys } from '@/features/system/useSystemStatus'
import {
  adminService,
  type CategoryPayload,
  type PriorityPayload,
  type StatusPayload,
} from '@/services/adminService'

export const catalogueKeys = {
  overview: ['admin', 'catalogue'] as const,
}

export function useAdminCatalogue() {
  return useQuery({
    queryKey: catalogueKeys.overview,
    queryFn: adminService.catalogue,
  })
}

function useInvalidateCatalogue() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: catalogueKeys.overview })
    void queryClient.invalidateQueries({ queryKey: systemKeys.lookups })
  }
}

export function useCreateCategory() {
  const invalidate = useInvalidateCatalogue()

  return useMutation({
    mutationFn: (payload: CategoryPayload) => adminService.createCategory(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateCategory() {
  const invalidate = useInvalidateCatalogue()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number | string; payload: Partial<CategoryPayload> }) =>
      adminService.updateCategory(id, payload),
    onSuccess: invalidate,
  })
}

export function useCreatePriority() {
  const invalidate = useInvalidateCatalogue()

  return useMutation({
    mutationFn: (payload: PriorityPayload) => adminService.createPriority(payload),
    onSuccess: invalidate,
  })
}

export function useUpdatePriority() {
  const invalidate = useInvalidateCatalogue()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number | string; payload: Partial<PriorityPayload> }) =>
      adminService.updatePriority(id, payload),
    onSuccess: invalidate,
  })
}

export function useUpdateStatus() {
  const invalidate = useInvalidateCatalogue()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number | string; payload: StatusPayload }) =>
      adminService.updateStatus(id, payload),
    onSuccess: invalidate,
  })
}
