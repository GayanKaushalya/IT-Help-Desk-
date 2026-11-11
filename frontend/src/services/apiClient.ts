import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'

import { env } from '@/config/env'
import { ApiError, type ApiErrorBody, type ApiResponse, type FieldErrors } from '@/types/api'

/**
 * Where the bearer token lives. Kept here so the authentication module has a
 * single place to plug into.
 */
const TOKEN_STORAGE_KEY = 'helpdesk.auth.token'

export const tokenStorage = {
  get(): string | null {
    return localStorage.getItem(TOKEN_STORAGE_KEY) ?? sessionStorage.getItem(TOKEN_STORAGE_KEY)
  },

  /**
   * Remembered sessions survive a browser restart; session-only tokens live
   * in sessionStorage and disappear when the tab is closed.
   */
  set(token: string, remember = true): void {
    this.clear()
    const store = remember ? localStorage : sessionStorage
    store.setItem(TOKEN_STORAGE_KEY, token)
  },

  clear(): void {
    localStorage.removeItem(TOKEN_STORAGE_KEY)
    sessionStorage.removeItem(TOKEN_STORAGE_KEY)
  },
}

/** Invoked when the API reports the session is no longer valid. */
type UnauthorizedHandler = () => void

let onUnauthorized: UnauthorizedHandler | null = null

export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  onUnauthorized = handler
}

const http: AxiosInstance = axios.create({
  baseURL: env.apiBaseUrl,
  timeout: 20000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

http.interceptors.request.use((config) => {
  const token = tokenStorage.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // Let the browser set the multipart boundary. The instance default of
  // application/json would otherwise corrupt file uploads.
  if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
    if (typeof config.headers.delete === 'function') {
      config.headers.delete('Content-Type')
    }
  }

  return config
})

/** Collapses Laravel's `field: string[]` errors into one message per field. */
function toFieldErrors(errors: Record<string, string[]> | undefined): FieldErrors {
  if (!errors) return {}

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? 'Invalid value.']),
  )
}

function normaliseError(error: AxiosError<ApiErrorBody>): ApiError {
  if (!error.response) {
    return new ApiError(
      'Unable to reach the server. Check your connection and try again.',
      0,
      'NETWORK_ERROR',
    )
  }

  const { status, data } = error.response

  return new ApiError(
    data?.message ?? 'Something went wrong. Please try again.',
    status,
    data?.code ?? 'HTTP_ERROR',
    toFieldErrors(data?.errors),
  )
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const apiError = normaliseError(error)

    // Only an expired or missing session should drop the user out. A failed
    // sign-in also returns 401 (INVALID_CREDENTIALS) and must stay on the form.
    if (apiError.isUnauthenticated && apiError.code === 'UNAUTHENTICATED') {
      tokenStorage.clear()
      onUnauthorized?.()
    }

    return Promise.reject(apiError)
  },
)

/**
 * Thin wrapper that unwraps the API envelope, so callers receive `data`
 * directly and always fail with an ApiError.
 */
export const apiClient = {
  async get<TData>(url: string, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.get<ApiResponse<TData>>(url, config)
    return response.data.data
  },

  async post<TData>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.post<ApiResponse<TData>>(url, body, config)
    return response.data.data
  },

  async postForm<TData>(url: string, body: FormData, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.post<ApiResponse<TData>>(url, body, {
      timeout: 120_000,
      ...config,
    })
    return response.data.data
  },

  async put<TData>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.put<ApiResponse<TData>>(url, body, config)
    return response.data.data
  },

  async patch<TData>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.patch<ApiResponse<TData>>(url, body, config)
    return response.data.data
  },

  async delete<TData>(url: string, config?: AxiosRequestConfig): Promise<TData> {
    const response = await http.delete<ApiResponse<TData>>(url, config)
    return response.data.data
  },

  /** Escape hatch for endpoints that need the envelope, e.g. pagination meta. */
  async getRaw<TData>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<TData>> {
    const response = await http.get<ApiResponse<TData>>(url, config)
    return response.data
  },

  async download(url: string, config?: AxiosRequestConfig): Promise<{ blob: Blob; filename: string }> {
    try {
      const response = await http.get<Blob>(url, {
        ...config,
        responseType: 'blob',
        headers: {
          ...config?.headers,
          Accept: '*/*',
        },
      })
      const header = String(response.headers['content-disposition'] ?? '')
      const match = /filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i.exec(header)
      const filename = decodeURIComponent(match?.[1] ?? match?.[2] ?? 'download')

      // API errors can arrive as JSON with a 2xx-looking blob when proxies
      // mishandle status; also reject JSON error bodies returned as blobs.
      if (response.data.type === 'application/json') {
        const text = await response.data.text()
        let message = 'The file could not be downloaded.'
        try {
          const parsed = JSON.parse(text) as { message?: string }
          if (parsed.message) message = parsed.message
        } catch {
          // keep default
        }
        throw new ApiError(message, response.status, 'DOWNLOAD_ERROR')
      }

      return { blob: response.data, filename }
    } catch (error) {
      if (error instanceof ApiError) {
        throw error
      }

      if (error instanceof AxiosError && error.response?.data instanceof Blob) {
        try {
          const text = await error.response.data.text()
          const parsed = JSON.parse(text) as ApiErrorBody
          throw new ApiError(
            parsed.message ?? 'The file could not be downloaded.',
            error.response.status,
            parsed.code ?? 'DOWNLOAD_ERROR',
            toFieldErrors(parsed.errors),
          )
        } catch (inner) {
          if (inner instanceof ApiError) throw inner
        }
      }

      throw error
    }
  },
}

export { http }
