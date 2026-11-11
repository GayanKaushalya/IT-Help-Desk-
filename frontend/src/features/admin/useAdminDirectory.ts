import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { systemKeys } from '@/features/system/useSystemStatus'
import {
  adminService,
  type AdminBranchListParams,
  type AdminBranchPayload,
  type AdminUserListParams,
  type AdminUserPayload,
} from '@/services/adminService'

export const adminDirectoryKeys = {
  lookups: ['admin', 'lookups'] as const,
  users: (params: AdminUserListParams) => ['admin', 'users', 'list', params] as const,
  user: (id: string) => ['admin', 'users', id] as const,
  branches: (params: AdminBranchListParams) => ['admin', 'branches', 'list', params] as const,
}

export function useAdminLookups() {
  return useQuery({
    queryKey: adminDirectoryKeys.lookups,
    queryFn: adminService.lookups,
    staleTime: 5 * 60_000,
  })
}

export function useAdminUsers(params: AdminUserListParams) {
  return useQuery({
    queryKey: adminDirectoryKeys.users(params),
    queryFn: () => adminService.users(params),
    placeholderData: keepPreviousData,
  })
}

export function useAdminUser(id: string | undefined) {
  return useQuery({
    queryKey: adminDirectoryKeys.user(id ?? ''),
    queryFn: () => adminService.user(id as string),
    enabled: Boolean(id),
    retry: false,
  })
}

function useInvalidateDirectory(userId?: string) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: ['admin'] })
    void queryClient.invalidateQueries({ queryKey: systemKeys.lookups })
    void queryClient.invalidateQueries({ queryKey: systemKeys.publicLookups })
    if (userId) {
      void queryClient.invalidateQueries({ queryKey: adminDirectoryKeys.user(userId) })
    }
  }
}

export function useCreateAdminUser() {
  const invalidate = useInvalidateDirectory()

  return useMutation({
    mutationFn: (payload: AdminUserPayload) => adminService.createUser(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateAdminUser(userId: string) {
  const invalidate = useInvalidateDirectory(userId)

  return useMutation({
    mutationFn: (payload: Partial<AdminUserPayload>) => adminService.updateUser(userId, payload),
    onSuccess: invalidate,
  })
}

export function useActivateAdminUser(userId: string) {
  const invalidate = useInvalidateDirectory(userId)

  return useMutation({
    mutationFn: () => adminService.activateUser(userId),
    onSuccess: invalidate,
  })
}

export function useDeactivateAdminUser(userId: string) {
  const invalidate = useInvalidateDirectory(userId)

  return useMutation({
    mutationFn: () => adminService.deactivateUser(userId),
    onSuccess: invalidate,
  })
}

export function useResetAdminUserPassword(userId: string) {
  const invalidate = useInvalidateDirectory(userId)

  return useMutation({
    mutationFn: () => adminService.resetUserPassword(userId),
    onSuccess: invalidate,
  })
}

export function useAdminBranches(params: AdminBranchListParams) {
  return useQuery({
    queryKey: adminDirectoryKeys.branches(params),
    queryFn: () => adminService.branches(params),
    placeholderData: keepPreviousData,
  })
}

export function useCreateAdminBranch() {
  const invalidate = useInvalidateDirectory()

  return useMutation({
    mutationFn: (payload: AdminBranchPayload) => adminService.createBranch(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateAdminBranch(branchId: string) {
  const invalidate = useInvalidateDirectory()

  return useMutation({
    mutationFn: (payload: Partial<AdminBranchPayload>) => adminService.updateBranch(branchId, payload),
    onSuccess: invalidate,
  })
}

export function useActivateAdminBranch(branchId: string) {
  const invalidate = useInvalidateDirectory()

  return useMutation({
    mutationFn: () => adminService.activateBranch(branchId),
    onSuccess: invalidate,
  })
}

export function useDeactivateAdminBranch(branchId: string) {
  const invalidate = useInvalidateDirectory()

  return useMutation({
    mutationFn: () => adminService.deactivateBranch(branchId),
    onSuccess: invalidate,
  })
}
