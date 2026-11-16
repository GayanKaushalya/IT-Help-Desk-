import type { AxiosRequestConfig } from 'axios'

import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type {
  AuditLogEntry,
  AuditLookups,
  HrComment,
  HrDashboardOverview,
  HrEmployeeDashboardOverview,
  HrLookups,
  HrReferral,
  HrReportOverview,
  HrRequest,
  HrSlaCategory,
  HrStaffWorkload,
  HrUserSummary,
} from '@/types/models'

export interface HrReportParams {
  date_from?: string
  date_to?: string
  branch_id?: string
  category_id?: string
  request_type_id?: string
  assigned_to?: string
  status_id?: string
  confidential?: string
}

export interface HrRequestListParams {
  search?: string
  status_id?: string
  category_id?: string
  request_type_id?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface HrStaffRequestListParams extends HrRequestListParams {
  branch_id?: string
  assigned_to?: string
  unassigned?: boolean
  overdue?: boolean
  confidential?: boolean
}

export interface HrRequestListResult {
  data: HrRequest[]
  meta: PaginationMeta
}

export interface HrAuditLogParams {
  search?: string
  event?: string
  user_id?: string
  entity_type?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface HrAuditListResult {
  data: AuditLogEntry[]
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

async function paginate(path: string, params: HrStaffRequestListParams): Promise<HrRequestListResult> {
  const response = await apiClient.getRaw<HrRequest[]>(path, { params: compactParams(params) })

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

export const hrService = {
  lookups: (): Promise<HrLookups> => apiClient.get<HrLookups>('/hr/lookups'),

  /* ---------------------------- employee ---------------------------- */

  dashboard: (): Promise<HrEmployeeDashboardOverview> =>
    apiClient.get<HrEmployeeDashboardOverview>('/hr/dashboard'),

  index: (params: HrRequestListParams = {}): Promise<HrRequestListResult> => paginate('/hr/requests', params),

  show: (id: number | string): Promise<HrRequest> => apiClient.get<HrRequest>(`/hr/requests/${enc(id)}`),

  track: (reference: string): Promise<HrRequest> =>
    apiClient.get<HrRequest>(`/hr/requests/track/${enc(reference.trim())}`),

  create: (body: FormData, onUploadProgress?: (percent: number) => void): Promise<HrRequest> => {
    const config: AxiosRequestConfig = {
      onUploadProgress: (event) => {
        if (!event.total) return
        onUploadProgress?.(Math.round((event.loaded / event.total) * 100))
      },
    }

    return apiClient.postForm<HrRequest>('/hr/requests', body, config)
  },

  comment: (id: number | string, body: string): Promise<HrComment> =>
    apiClient.post<HrComment>(`/hr/requests/${enc(id)}/comments`, { body }),

  verify: (id: number | string, decision: 'confirmed' | 'rejected'): Promise<HrRequest> =>
    apiClient.post<HrRequest>(`/hr/requests/${enc(id)}/verify`, { decision }),

  /* ------------------------------ staff ------------------------------ */

  staff: {
    dashboard: (): Promise<HrDashboardOverview> => apiClient.get<HrDashboardOverview>('/hr/staff/dashboard'),

    agents: (): Promise<HrStaffWorkload[]> => apiClient.get<HrStaffWorkload[]>('/hr/staff/agents'),

    reports: (params: HrReportParams = {}): Promise<HrReportOverview> =>
      apiClient.get<HrReportOverview>('/hr/staff/reports', { params: compactParams(params) }),

    exportReport: (
      params: HrReportParams,
      format: 'csv' | 'pdf',
    ): Promise<{ blob: Blob; filename: string }> =>
      apiClient.download('/hr/staff/reports/export', {
        params: compactParams({ ...params, format }),
        timeout: 60_000,
      }),

    sla: (): Promise<HrSlaCategory[]> => apiClient.get<HrSlaCategory[]>('/hr/staff/sla'),

    updateCategorySla: (
      categoryId: number,
      payload: { target_hours: number; escalate_to_manager?: boolean },
    ): Promise<HrSlaCategory[]> =>
      apiClient.patch<HrSlaCategory[]>(`/hr/staff/sla/categories/${enc(categoryId)}`, payload),

    updateTypeSla: (
      typeId: number,
      payload: { target_hours: number; escalate_to_manager?: boolean },
    ): Promise<HrSlaCategory[]> =>
      apiClient.patch<HrSlaCategory[]>(`/hr/staff/sla/types/${enc(typeId)}`, payload),

    auditLookups: (): Promise<AuditLookups> => apiClient.get<AuditLookups>('/hr/staff/audit-logs/lookups'),

    auditLogs: async (params: HrAuditLogParams = {}): Promise<HrAuditListResult> => {
      const response = await apiClient.getRaw<AuditLogEntry[]>('/hr/staff/audit-logs', {
        params: compactParams(params),
      })

      return {
        data: response.data,
        meta: response.meta ?? {
          current_page: 1,
          per_page: params.per_page ?? 20,
          total: response.data.length,
          last_page: 1,
          from: response.data.length > 0 ? 1 : null,
          to: response.data.length > 0 ? response.data.length : null,
        },
      }
    },

    requests: (params: HrStaffRequestListParams = {}): Promise<HrRequestListResult> =>
      paginate('/hr/staff/requests', params),

    assigned: (params: HrStaffRequestListParams = {}): Promise<HrRequestListResult> =>
      paginate('/hr/staff/requests/assigned', params),

    show: (id: number | string): Promise<HrRequest> =>
      apiClient.get<HrRequest>(`/hr/staff/requests/${enc(id)}`),

    assign: (id: number | string, assignedTo: number, note?: string): Promise<HrRequest> =>
      apiClient.post<HrRequest>(`/hr/staff/requests/${enc(id)}/assign`, {
        assigned_to: assignedTo,
        ...(note ? { note } : {}),
      }),

    updateStatus: (id: number | string, statusId: number, remarks?: string): Promise<HrRequest> =>
      apiClient.patch<HrRequest>(`/hr/staff/requests/${enc(id)}/status`, {
        status_id: statusId,
        ...(remarks ? { remarks } : {}),
      }),

    resolve: (id: number | string, resolutionSummary: string, close = false): Promise<HrRequest> =>
      apiClient.post<HrRequest>(`/hr/staff/requests/${enc(id)}/resolve`, {
        resolution_summary: resolutionSummary,
        close,
      }),

    closeAsVerified: (id: number | string): Promise<HrRequest> =>
      apiClient.post<HrRequest>(`/hr/staff/requests/${enc(id)}/close-as-verified`),

    refer: (
      id: number | string,
      payload: { department: string; brief?: string; assigned_to?: number },
    ): Promise<HrReferral> =>
      apiClient.post<HrReferral>(`/hr/staff/requests/${enc(id)}/refer`, payload),

    respondToReferral: (id: number | string, referralId: number, response: string): Promise<HrReferral> =>
      apiClient.post<HrReferral>(`/hr/staff/requests/${enc(id)}/referrals/${referralId}/respond`, { response }),

    comment: (id: number | string, body: string, isInternal = false): Promise<HrComment> =>
      apiClient.post<HrComment>(`/hr/staff/requests/${enc(id)}/comments`, {
        body,
        is_internal: isInternal,
      }),
  },
}
