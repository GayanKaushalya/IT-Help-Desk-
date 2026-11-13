import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type {
  AccountStatus,
  AdminDirectoryLookups,
  AuditLogEntry,
  AuditLookups,
  Branch,
  CatalogueOverview,
  ReportOverview,
  SystemSettings,
  TicketCategory,
  TicketPriority,
  TicketStatus,
  User,
} from '@/types/models'

export interface AdminUserListParams {
  search?: string
  branch_id?: string
  role_id?: string
  account_status?: AccountStatus | ''
  page?: number
  per_page?: number
}

export interface AdminBranchListParams {
  search?: string
  status?: 'active' | 'inactive' | ''
  page?: number
  per_page?: number
}

export interface AdminListResult<T> {
  data: T[]
  meta: PaginationMeta
}

export interface AdminUserPayload {
  full_name: string
  username: string
  employee_id: string
  epf_number: string
  email?: string | null
  phone?: string | null
  job_title?: string | null
  role_id: number
  branch_id: number
  department_id?: number | null
  password?: string
  is_active?: boolean
}

export interface AdminReportParams {
  date_from?: string
  date_to?: string
  branch_id?: string
  category_id?: string
  priority_id?: string
  assigned_to?: string
  status_id?: string
}

export interface AdminAuditLogParams {
  search?: string
  event?: string
  user_id?: string
  entity_type?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface AdminBranchPayload {
  name: string
  code: string
  address?: string | null
  city?: string | null
  phone?: string | null
  is_active?: boolean
}

export interface CategoryPayload {
  name: string
  description?: string | null
  default_priority_id?: number | null
  is_active?: boolean
  requires_item_details?: boolean
  sort_order?: number
}

export interface PriorityPayload {
  name: string
  level: number
  color_token: string
  response_minutes?: number | null
  resolution_minutes?: number | null
  is_active?: boolean
  sort_order?: number
}

export interface StatusPayload {
  name?: string
  color_token?: string
  is_active?: boolean
  sort_order?: number
}

export interface SettingsPayload {
  organisation_name: string
  support_email?: string
  support_phone?: string
  support_hours?: string
  allow_self_registration: boolean
}

function compact(params: object): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== ''),
  ) as Record<string, string | number>
}

async function paginate<T>(path: string, params: Record<string, string | number | undefined | ''>): Promise<AdminListResult<T>> {
  const response = await apiClient.getRaw<T[]>(path, { params: compact(params) })

  return {
    data: response.data,
    meta: response.meta ?? {
      current_page: 1,
      per_page: 15,
      total: response.data.length,
      last_page: 1,
      from: response.data.length > 0 ? 1 : null,
      to: response.data.length > 0 ? response.data.length : null,
    },
  }
}

export const adminService = {
  lookups: (): Promise<AdminDirectoryLookups> => apiClient.get<AdminDirectoryLookups>('/admin/lookups'),

  users: (params: AdminUserListParams = {}): Promise<AdminListResult<User>> =>
    paginate<User>('/admin/users', {
      search: params.search,
      branch_id: params.branch_id,
      role_id: params.role_id,
      account_status: params.account_status,
      page: params.page,
      per_page: params.per_page,
    }),

  user: (id: number | string): Promise<User> =>
    apiClient.get<User>(`/admin/users/${encodeURIComponent(String(id))}`),

  createUser: (payload: AdminUserPayload): Promise<User> => apiClient.post<User>('/admin/users', payload),

  updateUser: (id: number | string, payload: Partial<AdminUserPayload>): Promise<User> =>
    apiClient.patch<User>(`/admin/users/${encodeURIComponent(String(id))}`, payload),

  activateUser: (id: number | string): Promise<User> =>
    apiClient.post<User>(`/admin/users/${encodeURIComponent(String(id))}/activate`),

  deactivateUser: (id: number | string): Promise<User> =>
    apiClient.post<User>(`/admin/users/${encodeURIComponent(String(id))}/deactivate`),

  resetUserPassword: (id: number | string): Promise<User> =>
    apiClient.post<User>(`/admin/users/${encodeURIComponent(String(id))}/reset-password`),

  branches: (params: AdminBranchListParams = {}): Promise<AdminListResult<Branch>> =>
    paginate<Branch>('/admin/branches', {
      search: params.search,
      status: params.status,
      page: params.page,
      per_page: params.per_page,
    }),

  createBranch: (payload: AdminBranchPayload): Promise<Branch> => apiClient.post<Branch>('/admin/branches', payload),

  updateBranch: (id: number | string, payload: Partial<AdminBranchPayload>): Promise<Branch> =>
    apiClient.patch<Branch>(`/admin/branches/${encodeURIComponent(String(id))}`, payload),

  activateBranch: (id: number | string): Promise<Branch> =>
    apiClient.post<Branch>(`/admin/branches/${encodeURIComponent(String(id))}/activate`),

  deactivateBranch: (id: number | string): Promise<Branch> =>
    apiClient.post<Branch>(`/admin/branches/${encodeURIComponent(String(id))}/deactivate`),

  reports: (params: AdminReportParams = {}): Promise<ReportOverview> =>
    apiClient.get<ReportOverview>('/admin/reports', { params: compact(params) }),

  exportReport: (params: AdminReportParams, format: 'csv' | 'pdf'): Promise<{ blob: Blob; filename: string }> =>
    apiClient.download('/admin/reports/export', { params: compact({ ...params, format }), timeout: 60_000 }),

  auditLookups: (): Promise<AuditLookups> => apiClient.get<AuditLookups>('/admin/audit-logs/lookups'),

  auditLogs: (params: AdminAuditLogParams = {}): Promise<AdminListResult<AuditLogEntry>> =>
    paginate<AuditLogEntry>('/admin/audit-logs', {
      search: params.search,
      event: params.event,
      user_id: params.user_id,
      entity_type: params.entity_type,
      date_from: params.date_from,
      date_to: params.date_to,
      page: params.page,
      per_page: params.per_page,
    }),

  catalogue: (): Promise<CatalogueOverview> => apiClient.get<CatalogueOverview>('/admin/catalogue'),

  createCategory: (payload: CategoryPayload): Promise<TicketCategory> =>
    apiClient.post<TicketCategory>('/admin/catalogue/categories', payload),

  updateCategory: (id: number | string, payload: Partial<CategoryPayload>): Promise<TicketCategory> =>
    apiClient.patch<TicketCategory>(`/admin/catalogue/categories/${encodeURIComponent(String(id))}`, payload),

  createPriority: (payload: PriorityPayload): Promise<TicketPriority> =>
    apiClient.post<TicketPriority>('/admin/catalogue/priorities', payload),

  updatePriority: (id: number | string, payload: Partial<PriorityPayload>): Promise<TicketPriority> =>
    apiClient.patch<TicketPriority>(`/admin/catalogue/priorities/${encodeURIComponent(String(id))}`, payload),

  updateStatus: (id: number | string, payload: StatusPayload): Promise<TicketStatus> =>
    apiClient.patch<TicketStatus>(`/admin/catalogue/statuses/${encodeURIComponent(String(id))}`, payload),

  settings: (): Promise<SystemSettings> => apiClient.get<SystemSettings>('/admin/settings'),

  updateSettings: (payload: SettingsPayload): Promise<SystemSettings> =>
    apiClient.put<SystemSettings>('/admin/settings', payload),
}
