import type { FieldErrors } from '@/types/api'

export const USERNAME_PATTERN = /^[A-Za-z][A-Za-z0-9._-]{2,59}$/
export const EMPLOYEE_CODE_PATTERN = /^[A-Za-z0-9][A-Za-z0-9./_-]{1,39}$/
export const FULL_NAME_PATTERN = /^[\p{L}][\p{L}\s.'-]{1,148}$/u

export interface PasswordChecks {
  length: boolean
  lower: boolean
  upper: boolean
  number: boolean
  symbol: boolean
}

export interface PasswordStrength {
  checks: PasswordChecks
  score: number
  label: 'Too weak' | 'Weak' | 'Fair' | 'Strong'
}

export function assessPassword(password: string): PasswordStrength {
  const checks: PasswordChecks = {
    length: password.length >= 10,
    lower: /[a-z]/.test(password),
    upper: /[A-Z]/.test(password),
    number: /\d/.test(password),
    symbol: /[^A-Za-z0-9]/.test(password),
  }

  const score = Object.values(checks).filter(Boolean).length
  const label =
    score <= 2 ? 'Too weak' : score === 3 ? 'Weak' : score === 4 ? 'Fair' : 'Strong'

  return { checks, score, label }
}

export interface RegistrationFormValues {
  full_name: string
  branch_id: string
  employee_id: string
  username: string
  password: string
  password_confirmation: string
}

export function validateRegistration(values: RegistrationFormValues): FieldErrors {
  const errors: FieldErrors = {}

  if (values.full_name.trim().length < 3) {
    errors.full_name = 'Enter your full name (at least 3 characters).'
  } else if (!FULL_NAME_PATTERN.test(values.full_name.trim())) {
    errors.full_name = 'Use letters, spaces, hyphens or apostrophes only.'
  }

  if (!values.branch_id) {
    errors.branch_id = 'Select your branch.'
  }

  if (!values.employee_id.trim()) {
    errors.employee_id = 'Enter your employee ID.'
  } else if (!EMPLOYEE_CODE_PATTERN.test(values.employee_id.trim())) {
    errors.employee_id = 'Employee ID may contain letters, numbers, dots, slashes, hyphens and underscores.'
  }

  if (!values.username.trim()) {
    errors.username = 'Choose a username.'
  } else if (!USERNAME_PATTERN.test(values.username.trim())) {
    errors.username = 'Start with a letter. Use letters, numbers, dots, hyphens or underscores.'
  }

  const strength = assessPassword(values.password)
  if (!values.password) {
    errors.password = 'Create a password.'
  } else if (strength.score < 5) {
    errors.password = 'Use at least 10 characters with upper and lower case, a number and a symbol.'
  }

  if (!values.password_confirmation) {
    errors.password_confirmation = 'Confirm your password.'
  } else if (values.password !== values.password_confirmation) {
    errors.password_confirmation = 'The password confirmation does not match.'
  }

  return errors
}
