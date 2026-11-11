import type { AxiosRequestConfig } from 'axios'

import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type { Ticket, TicketAttachment, TicketComment } from '@/types/models'

export interface TicketListParams {
  search?: string
  status_id?: string
  priority_id?: string
  category_id?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface TicketListResult {
  data: Ticket[]
  meta: PaginationMeta
}

function compactParams(params: TicketListParams): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== ''),
  ) as Record<string, string | number>
}

export const ticketService = {
  index: async (params: TicketListParams = {}): Promise<TicketListResult> => {
    const response = await apiClient.getRaw<Ticket[]>('/tickets', { params: compactParams(params) })

    return {
      data: response.data,
      meta: response.meta ?? {
        current_page: 1,
        per_page: params.per_page ?? 15,
        total: response.data.length,
        last_page: 1,
        from: response.data.length > 0 ? 1 : null,
        to: response.data.length > 0 ? response.data.length : null,
      },
    }
  },

  show: (id: number | string): Promise<Ticket> => apiClient.get<Ticket>(`/tickets/${encodeURIComponent(String(id))}`),

  create: (body: FormData, onUploadProgress?: (percent: number) => void): Promise<Ticket> => {
    const config: AxiosRequestConfig = {
      onUploadProgress: (event) => {
        if (!event.total) return
        onUploadProgress?.(Math.round((event.loaded / event.total) * 100))
      },
    }

    return apiClient.postForm<Ticket>('/tickets', body, config)
  },

  comment: (ticketId: number | string, body: string): Promise<TicketComment> =>
    apiClient.post<TicketComment>(`/tickets/${encodeURIComponent(String(ticketId))}/comments`, { body }),

  attach: (
    ticketId: number | string,
    files: File[],
    onUploadProgress?: (percent: number) => void,
  ): Promise<TicketAttachment[]> => {
    const form = new FormData()
    files.forEach((file) => form.append('attachments[]', file))

    return apiClient.postForm<TicketAttachment[]>(
      `/tickets/${encodeURIComponent(String(ticketId))}/attachments`,
      form,
      {
        onUploadProgress: (event) => {
          if (!event.total) return
          onUploadProgress?.(Math.round((event.loaded / event.total) * 100))
        },
      },
    )
  },

  verify: (ticketId: number | string, decision: 'confirmed' | 'rejected'): Promise<Ticket> =>
    apiClient.post<Ticket>(`/tickets/${encodeURIComponent(String(ticketId))}/verify`, { decision }),

  async downloadAttachment(url: string, filename: string): Promise<void> {
    const path = toApiRelativePath(url)
    const { blob, filename: headerName } = await apiClient.download(path)
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = headerName && headerName !== 'download' ? headerName : filename
    document.body.append(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(objectUrl)
  },
}

/**
 * Attachment download_url may be a relative API path or a legacy absolute
 * APP_URL. Normalise to a path under the axios baseURL (/api/v1).
 */
function toApiRelativePath(url: string): string {
  let pathname = url

  try {
    if (url.startsWith('http://') || url.startsWith('https://')) {
      pathname = new URL(url).pathname
    }
  } catch {
    pathname = url
  }

  if (pathname.startsWith('/api/v1/')) {
    return pathname.slice('/api/v1'.length)
  }

  if (pathname.startsWith('/api/v1')) {
    return pathname.slice('/api/v1'.length) || '/'
  }

  return pathname.startsWith('/') ? pathname : `/${pathname}`
}
