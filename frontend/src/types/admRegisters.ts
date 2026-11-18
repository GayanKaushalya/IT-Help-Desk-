import type { ColorToken } from '@/types/models'

/* ------------------------------------------------------------------ *
 * Administration registers, allocations, reports, SLA and CCTV
 * ------------------------------------------------------------------ */

export type InsuranceStatus = 'active' | 'expired' | 'cancelled'
export type RenewalStatus = 'not_due' | 'pending' | 'in_progress' | 'renewed'
export type VehicleStatus = 'available' | 'maintenance' | 'retired'
export type DriverStatus = 'active' | 'on_leave' | 'inactive'
export type FuelCardStatus = 'active' | 'suspended' | 'lost' | 'cancelled'
export type AllocationStatus = 'scheduled' | 'active' | 'completed' | 'cancelled'

export interface PersonRef {
  id: number
  full_name: string
}

export interface InsurancePolicy {
  id: number
  company_name: string | null
  insurance_type: string
  provider: string
  policy_number: string
  insured_asset: string | null
  vehicle_registration: string | null
  start_date: string | null
  expiry_date: string | null
  premium_amount: string | null
  sum_insured: string | null
  renewal_status: RenewalStatus
  status: InsuranceStatus
  notes: string | null
  days_to_expiry: number | null
  is_expired: boolean
  has_document: boolean
  document_name: string | null
  responsible_officer_id: number | null
  responsible_officer?: PersonRef | null
}

export interface Vehicle {
  id: number
  company_name: string | null
  registration_number: string
  make: string | null
  model: string | null
  year: number | null
  vehicle_type: string | null
  fuel_type: string | null
  chassis_number: string | null
  current_mileage: number | null
  driver_id: number | null
  insurance_policy_id: number | null
  revenue_license_expiry: string | null
  status: VehicleStatus
  notes: string | null
  driver?: PersonRef | null
  insurance_policy?: { id: number; policy_number: string; expiry_date: string | null } | null
}

export interface Driver {
  id: number
  company_name: string | null
  full_name: string
  employee_id: string | null
  license_number: string | null
  license_class: string | null
  license_expiry_date: string | null
  license_expired: boolean
  phone: string | null
  email: string | null
  status: DriverStatus
  notes: string | null
}

export interface FuelCard {
  id: number
  company_name: string | null
  card_number: string
  /** Last four characters only; the full number and PIN are never stored. */
  masked_card_ref: string | null
  provider: string | null
  vehicle_id: number | null
  driver_id: number | null
  assigned_user_id: number | null
  monthly_limit: string | null
  issued_at: string | null
  expiry_date: string | null
  status: FuelCardStatus
  notes: string | null
  vehicle?: { id: number; registration_number: string } | null
  driver?: PersonRef | null
  assigned_user?: PersonRef | null
}

export interface VehicleAllocation {
  id: number
  vehicle_id: number
  driver_id: number | null
  adm_request_id: number | null
  /** `YYYY-MM-DDTHH:mm` (local), ready for datetime-local inputs. */
  start_at: string
  end_at: string
  status: AllocationStatus
  purpose: string | null
  destination: string | null
  notes: string | null
  vehicle?: { id: number; registration_number: string } | null
  driver?: PersonRef | null
  request?: { id: number; reference: string } | null
  created_by?: PersonRef | null
}

export interface HistoryEntry {
  id: number
  event: string
  notes: string | null
  changed_at: string | null
  changed_by: PersonRef | null
}

export interface RegisterLookups {
  officers: PersonRef[]
  vehicles: { id: number; registration_number: string; label: string; status: VehicleStatus }[]
  drivers: { id: number; full_name: string; status: DriverStatus }[]
  insurance_policies: { id: number; label: string }[]
  booking_requests: { id: number; label: string }[]
  companies: string[]
  enums: {
    insurance_status: InsuranceStatus[]
    renewal_status: RenewalStatus[]
    vehicle_status: VehicleStatus[]
    driver_status: DriverStatus[]
    fuel_card_status: FuelCardStatus[]
    allocation_status: AllocationStatus[]
  }
}

export interface UserOption {
  id: number
  full_name: string
  username: string
}

/* ------------------------------ reports ------------------------------ */

export interface AdmReport {
  filters: { date_from: string | null; date_to: string | null; category_id: number | null }
  kpis: {
    total_requests: number
    open_requests: number
    resolved_requests: number
    closed_requests: number
    overdue_requests: number
    unassigned_requests: number
    sla_compliance_percent: number | null
    avg_resolution_hours: number | null
    pending_cctv_approvals: number
  }
  by_category: { id: number; slug: string; name: string; total: number; open: number }[]
  by_status: { id: number; slug: string; name: string; color_token: ColorToken; count: number }[]
  monthly_trend: { month: string; label: string; created: number; resolved: number }[]
  insurance: {
    total: number
    active: number
    expired: number
    expiring_30_days: number
    expiring_60_days: number
    expiring_90_days: number
    renewal_in_progress: number
  }
  allocations: {
    total: number
    in_period_active: number
    cancelled: number
    completed: number
    current: number
    upcoming: number
  }
  registers: {
    vehicles: number
    vehicles_available: number
    drivers: number
    drivers_active: number
    fuel_cards: number
    fuel_cards_active: number
    insurance_policies: number
  }
  generated_at: string
}

export interface AdmReportParams {
  date_from?: string
  date_to?: string
  category_id?: string
}

/* -------------------------------- SLA -------------------------------- */

export interface AdmSlaType {
  id: number
  slug: string
  name: string
  sla_hours: number | null
  target_hours: number | null
}

export interface AdmSlaCategory {
  id: number
  slug: string
  name: string
  is_active: boolean
  default_sla_hours: number | null
  target_hours: number | null
  types: AdmSlaType[]
}

/* -------------------------------- CCTV -------------------------------- */

export type CctvDecisionStatus = 'pending' | 'approved' | 'rejected'

export interface CctvApprovalSummary {
  id: number
  status: CctvDecisionStatus
  decided_at: string | null
  decision_notes: string | null
  decided_by: PersonRef | null
}

export interface CctvPanel {
  approval: CctvApprovalSummary | null
  can_decide: boolean
  access_logs: {
    id: number
    action: string
    notes: string | null
    created_at: string | null
    user: PersonRef | null
  }[]
}
