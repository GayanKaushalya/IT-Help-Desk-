import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type {
  AdmReport,
  AdmReportParams,
  AdmSlaCategory,
  CctvPanel,
  Driver,
  FuelCard,
  HistoryEntry,
  InsurancePolicy,
  RegisterLookups,
  UserOption,
  Vehicle,
  VehicleAllocation,
} from '@/types/admRegisters'

const BASE = '/administration/staff/registers'

export interface RegisterListParams {
  search?: string
  status?: string
  renewal_status?: string
  company_name?: string
  vehicle_id?: string
  driver_id?: string
  expired?: boolean
  expiring_within?: string
  date_from?: string
  date_to?: string
  page?: number
  per_page?: number
}

export interface RegisterListResult<T> {
  data: T[]
  meta: PaginationMeta
}

/** Drops empty filters; booleans become 1 (Laravel's boolean rule rejects "true"). */
function compact(params: object): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null && value !== '' && value !== false)
      .map(([key, value]) => [key, value === true ? 1 : value]),
  ) as Record<string, string | number>
}

async function paginate<T>(path: string, params: RegisterListParams): Promise<RegisterListResult<T>> {
  const response = await apiClient.getRaw<T[]>(path, { params: compact(params) })

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

/** Payload sent to the API; the form modals own the field shapes. */
export type RegisterPayload = Record<string, string | number | boolean | null | undefined>

function crud<T>(resource: string) {
  const path = `${BASE}/${resource}`

  return {
    list: (params: RegisterListParams = {}): Promise<RegisterListResult<T>> => paginate<T>(path, params),
    create: (payload: RegisterPayload): Promise<T> => apiClient.post<T>(path, payload),
    update: (id: number, payload: RegisterPayload): Promise<T> => apiClient.patch<T>(`${path}/${id}`, payload),
    remove: (id: number): Promise<null> => apiClient.delete<null>(`${path}/${id}`),
  }
}

export const admRegistersService = {
  lookups: (): Promise<RegisterLookups> => apiClient.get<RegisterLookups>(`${BASE}/lookups`),

  users: (search: string): Promise<UserOption[]> =>
    apiClient.get<UserOption[]>(`${BASE}/lookups/users`, { params: compact({ search }) }),

  insurance: {
    ...crud<InsurancePolicy>('insurance'),
    history: (id: number): Promise<HistoryEntry[]> => apiClient.get<HistoryEntry[]>(`${BASE}/insurance/${id}/history`),
    uploadDocument: (id: number, file: File): Promise<InsurancePolicy> => {
      const body = new FormData()
      body.append('document', file)

      return apiClient.postForm<InsurancePolicy>(`${BASE}/insurance/${id}/document`, body)
    },
    downloadDocument: (id: number) => apiClient.download(`${BASE}/insurance/${id}/document`),
  },

  vehicles: crud<Vehicle>('vehicles'),
  drivers: crud<Driver>('drivers'),

  fuelCards: {
    ...crud<FuelCard>('fuel-cards'),
    history: (id: number): Promise<HistoryEntry[]> => apiClient.get<HistoryEntry[]>(`${BASE}/fuel-cards/${id}/history`),
  },

  allocations: {
    list: (params: RegisterListParams = {}): Promise<RegisterListResult<VehicleAllocation>> =>
      paginate<VehicleAllocation>(`${BASE}/allocations`, params),
    create: (payload: RegisterPayload): Promise<VehicleAllocation> =>
      apiClient.post<VehicleAllocation>(`${BASE}/allocations`, payload),
    update: (id: number, payload: RegisterPayload): Promise<VehicleAllocation> =>
      apiClient.patch<VehicleAllocation>(`${BASE}/allocations/${id}`, payload),
    cancel: (id: number): Promise<VehicleAllocation> =>
      apiClient.post<VehicleAllocation>(`${BASE}/allocations/${id}/cancel`),
  },

  report: (params: AdmReportParams = {}): Promise<AdmReport> =>
    apiClient.get<AdmReport>('/administration/staff/reports', { params: compact(params) }),

  sla: {
    list: (): Promise<AdmSlaCategory[]> => apiClient.get<AdmSlaCategory[]>('/administration/staff/sla'),
    updateCategory: (id: number, targetHours: number, applyToTypes: boolean): Promise<AdmSlaCategory[]> =>
      apiClient.patch<AdmSlaCategory[]>(`/administration/staff/sla/categories/${id}`, {
        target_hours: targetHours,
        apply_to_types: applyToTypes,
      }),
    updateType: (id: number, targetHours: number): Promise<AdmSlaCategory[]> =>
      apiClient.patch<AdmSlaCategory[]>(`/administration/staff/sla/types/${id}`, { target_hours: targetHours }),
  },

  cctv: {
    show: (requestId: number | string): Promise<CctvPanel> =>
      apiClient.get<CctvPanel>(`/administration/staff/requests/${requestId}/cctv`),
    approve: (requestId: number | string, notes?: string): Promise<CctvPanel> =>
      apiClient.post<CctvPanel>(`/administration/staff/requests/${requestId}/cctv/approve`, {
        decision_notes: notes || null,
      }),
    reject: (requestId: number | string, notes: string): Promise<CctvPanel> =>
      apiClient.post<CctvPanel>(`/administration/staff/requests/${requestId}/cctv/reject`, {
        decision_notes: notes,
      }),
  },
}
