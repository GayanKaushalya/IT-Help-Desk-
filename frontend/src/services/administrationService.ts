import type { AxiosRequestConfig } from 'axios'

import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type {
  AdmComment,
  AdmDashboardOverview,
  AdmEmployeeDashboardOverview,
  AdmLookups,
  AdmRequest,
  AdmStaffWorkload,
  AdmAttachment,
} from '@/types/models'

export interface AdmRequestListParams {
  search?: string
  status_id?: string
  category_id?: string
  request_type_id?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface AdmStaffRequestListParams extends AdmRequestListParams {
  branch_id?: string
  assigned_to?: string
  unassigned?: boolean
  overdue?: boolean
}

export interface AdmRequestListResult {
  data: AdmRequest[]
  meta: PaginationMeta
}

/** Drops empty filters and sends booleans as 1 (Laravel's boolean rule rejects the string "true"). */
function compactParams(params: object): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== '' && value !== false)
      .map(([key, value]) => [key, value === true ? 1 : value]),
  ) as Record<string, string | number>
}

async function paginate(path: string, params: AdmStaffRequestListParams): Promise<AdmRequestListResult> {
  const response = await apiClient.getRaw<AdmRequest[]>(path, { params: compactParams(params) })

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

const enc = (value: number | string): string => encodeURIComponent(String(value))

export const administrationService = {
  lookups: (): Promise<AdmLookups> => apiClient.get<AdmLookups>('/administration/lookups'),

  /* ---------------------------- employee ---------------------------- */

  dashboard: (): Promise<AdmEmployeeDashboardOverview> =>
    apiClient.get<AdmEmployeeDashboardOverview>('/administration/dashboard'),

  index: (params: AdmRequestListParams = {}): Promise<AdmRequestListResult> =>
    paginate('/administration/requests', params),

  show: (id: number | string): Promise<AdmRequest> =>
    apiClient.get<AdmRequest>(`/administration/requests/${enc(id)}`),

  track: (reference: string): Promise<AdmRequest> =>
    apiClient.get<AdmRequest>(`/administration/requests/track/${enc(reference.trim())}`),

  create: (body: FormData, onUploadProgress?: (percent: number) => void): Promise<AdmRequest> => {
    const config: AxiosRequestConfig = {
      onUploadProgress: (event) => {
        if (!event.total) return
        onUploadProgress?.(Math.round((event.loaded / event.total) * 100))
      },
    }

    return apiClient.postForm<AdmRequest>('/administration/requests', body, config)
  },

  comment: (id: number | string, body: string): Promise<AdmComment> =>
    apiClient.post<AdmComment>(`/administration/requests/${enc(id)}/comments`, { body }),

  verify: (id: number | string, decision: 'confirmed' | 'rejected'): Promise<AdmRequest> =>
    apiClient.post<AdmRequest>(`/administration/requests/${enc(id)}/verify`, { decision }),

  /* ------------------------------ staff ------------------------------ */

  staff: {
    dashboard: (): Promise<AdmDashboardOverview> =>
      apiClient.get<AdmDashboardOverview>('/administration/staff/dashboard'),

    agents: (): Promise<AdmStaffWorkload[]> =>
      apiClient.get<AdmStaffWorkload[]>('/administration/staff/agents'),

    requests: (params: AdmStaffRequestListParams = {}): Promise<AdmRequestListResult> =>
      paginate('/administration/staff/requests', params),

    assigned: (params: AdmStaffRequestListParams = {}): Promise<AdmRequestListResult> =>
      paginate('/administration/staff/requests/assigned', params),

    show: (id: number | string): Promise<AdmRequest> =>
      apiClient.get<AdmRequest>(`/administration/staff/requests/${enc(id)}`),

    assign: (id: number | string, assignedTo: number, note?: string): Promise<AdmRequest> =>
      apiClient.post<AdmRequest>(`/administration/staff/requests/${enc(id)}/assign`, {
        assigned_to: assignedTo,
        ...(note ? { note } : {}),
      }),

    updateStatus: (id: number | string, statusId: number, remarks?: string): Promise<AdmRequest> =>
      apiClient.patch<AdmRequest>(`/administration/staff/requests/${enc(id)}/status`, {
        status_id: statusId,
        ...(remarks ? { remarks } : {}),
      }),

    resolve: (id: number | string, resolutionSummary: string, close = false): Promise<AdmRequest> =>
      apiClient.post<AdmRequest>(`/administration/staff/requests/${enc(id)}/resolve`, {
        resolution_summary: resolutionSummary,
        close,
      }),

    closeAsVerified: (id: number | string): Promise<AdmRequest> =>
      apiClient.post<AdmRequest>(`/administration/staff/requests/${enc(id)}/close-as-verified`),

    comment: (id: number | string, body: string, isInternal = false): Promise<AdmComment> =>
      apiClient.post<AdmComment>(`/administration/staff/requests/${enc(id)}/comments`, {
        body,
        is_internal: isInternal,
      }),

    attach: (id: number | string, files: File[]): Promise<AdmAttachment[]> => {
      const body = new FormData()
      files.forEach((file) => body.append('attachments[]', file))

      return apiClient.postForm<AdmAttachment[]>(`/administration/staff/requests/${enc(id)}/attachments`, body)
    },
  },
}
