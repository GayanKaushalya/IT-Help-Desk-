import type { User } from '@/types/models'

export interface LoginCredentials {
  username: string
  password: string
  remember: boolean
}

export interface RegisterPayload {
  full_name: string
  branch_id: number
  employee_id: string
  username: string
  password: string
  password_confirmation: string
}

/** Payload returned by POST /auth/login and POST /auth/register. */
export interface LoginResult {
  user: User
  token: string
  token_type: string
  /** ISO timestamp after which the token stops being accepted. */
  expires_at: string | null
}
