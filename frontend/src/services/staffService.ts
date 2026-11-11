import type { AxiosRequestConfig } from 'axios'

import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type {
  StaffDashboardOverview,
  StaffWorkload,
  Ticket,
  TicketAttachment,
  TicketComment,
} from '@/types/models'

export interface StaffTicketListParams {
  search?: string
  status_id?: string
  priority_id?: string
  category_id?: string
  branch_id?: string
  assigned_to?: string
  unassigned?: boolean
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface StaffTicketListResult {
  data: Ticket[]
  meta: PaginationMeta
}

function compactParams(params: StaffTicketListParams): Record<string, string | number | boolean> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => {
      if (value === undefined || value === '' || value === false) return false
      return true
    }),
  ) as Record<string, string | number | boolean>
}

async function paginate(path: string, params: StaffTicketListParams): Promise<StaffTicketListResult> {
  const response = await apiClient.getRaw<Ticket[]>(path, { params: compactParams(params) })

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
}

export const staffService = {
  dashboard: (): Promise<StaffDashboardOverview> =>
    apiClient.get<StaffDashboardOverview>('/staff/dashboard'),

  agents: (): Promise<StaffWorkload[]> => apiClient.get<StaffWorkload[]>('/staff/agents'),

  tickets: (params: StaffTicketListParams = {}): Promise<StaffTicketListResult> =>
    paginate('/staff/tickets', params),

  assigned: (params: StaffTicketListParams = {}): Promise<StaffTicketListResult> =>
    paginate('/staff/tickets/assigned', params),

  show: (id: number | string): Promise<Ticket> =>
    apiClient.get<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}`),

  assign: (id: number | string, assignedTo: number, note?: string): Promise<Ticket> =>
    apiClient.post<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}/assign`, {
      assigned_to: assignedTo,
      ...(note ? { note } : {}),
    }),

  updateStatus: (id: number | string, statusId: number, resolutionSummary?: string): Promise<Ticket> =>
    apiClient.patch<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}/status`, {
      status_id: statusId,
      ...(resolutionSummary ? { resolution_summary: resolutionSummary } : {}),
    }),

  updatePriority: (id: number | string, priorityId: number): Promise<Ticket> =>
    apiClient.patch<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}/priority`, {
      priority_id: priorityId,
    }),

  resolve: (id: number | string, resolutionSummary: string): Promise<Ticket> =>
    apiClient.post<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}/resolve`, {
      resolution_summary: resolutionSummary,
    }),

  clearResolution: (id: number | string): Promise<Ticket> =>
    apiClient.post<Ticket>(`/staff/tickets/${encodeURIComponent(String(id))}/clear-resolution`),

  comment: (id: number | string, body: string, isInternal = false): Promise<TicketComment> =>
    apiClient.post<TicketComment>(`/staff/tickets/${encodeURIComponent(String(id))}/comments`, {
      body,
      is_internal: isInternal,
    }),

  attach: (
    id: number | string,
    files: File[],
    onUploadProgress?: (percent: number) => void,
  ): Promise<TicketAttachment[]> => {
    const form = new FormData()
    files.forEach((file) => form.append('attachments[]', file))

    const config: AxiosRequestConfig = {
      onUploadProgress: (event) => {
        if (!event.total) return
        onUploadProgress?.(Math.round((event.loaded / event.total) * 100))
      },
    }

    return apiClient.postForm<TicketAttachment[]>(
      `/staff/tickets/${encodeURIComponent(String(id))}/attachments`,
      form,
      config,
    )
  },
}
