import { useMutation } from '@tanstack/react-query'

import { useAuth } from '@/hooks/useAuth'
import {
  profileService,
  type ChangePasswordPayload,
  type UpdateProfilePayload,
} from '@/services/profileService'

export function useUpdateProfile() {
  const { refreshUser } = useAuth()

  return useMutation({
    mutationFn: (payload: UpdateProfilePayload) => profileService.update(payload),
    onSuccess: async () => {
      await refreshUser()
    },
  })
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (payload: ChangePasswordPayload) => profileService.changePassword(payload),
  })
}
