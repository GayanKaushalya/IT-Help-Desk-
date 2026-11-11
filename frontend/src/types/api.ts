/**
 * Shapes returned by the Laravel API. These mirror App\Support\ApiResponse
 * on the backend; change both together.
 */

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface ApiResponse<TData> {
  success: true
  message: string | null
  data: TData
  meta?: PaginationMeta
}

export interface ApiErrorBody {
  success: false
  message: string
  errors?: Record<string, string[]>
  code?: string
}

/** Field name to the first validation message for that field. */
export type FieldErrors = Record<string, string>

/**
 * Normalised error thrown by the API client, so callers never have to inspect
 * axios internals or guess at the response shape.
 */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly fieldErrors: FieldErrors

  constructor(message: string, status: number, code: string, fieldErrors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
  }

  get isValidationError(): boolean {
    return this.status === 422
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  get isForbidden(): boolean {
    return this.status === 403
  }

  get isNetworkError(): boolean {
    return this.status === 0
  }
}
