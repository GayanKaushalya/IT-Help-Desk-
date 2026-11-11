import { apiClient } from '@/services/apiClient'
import type { User } from '@/types/models'

export interface UpdateProfilePayload {
  email?: string | null
  phone?: string | null
  job_title?: string | null
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export const profileService = {
  show: (): Promise<User> => apiClient.get<User>('/profile'),

  update: (payload: UpdateProfilePayload): Promise<User> => apiClient.patch<User>('/profile', payload),

  changePassword: (payload: ChangePasswordPayload): Promise<User> =>
    apiClient.post<User>('/profile/password', payload),
}
