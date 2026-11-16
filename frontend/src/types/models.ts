/**
 * Domain models as serialised by the API resources in
 * backend/app/Http/Resources/V1.
 */

/** Semantic colour names shared by the backend seed data and the UI palette. */
export type ColorToken = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export interface Branch {
  id: number
  code: string
  name: string
  city: string | null
  address?: string | null
  phone?: string | null
  is_active: boolean
  sort_order?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface Department {
  id: number
  code: string
  name: string
  is_active: boolean
}

export interface Role {
  id: number
  slug: RoleSlug
  name: string
  level: number
  permissions?: string[]
}

export type RoleSlug =
  | 'employee'
  | 'technician'
  | 'supervisor'
  | 'administrator'
  | 'super-admin'
  | 'hr-officer'
  | 'hr-manager'
  | 'finance-officer'
  | 'confidential-complaint-handler'

export type AccountStatus = 'active' | 'inactive' | 'pending'

export interface UserTicketStatistics {
  total: number
  open: number
  in_progress: number
  awaiting_verification: number
  closed: number
}

export interface AuditLogEntry {
  id: number
  event: string
  action?: string
  entity_type?: string
  entity_id?: number | null
  description: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address?: string | null
  user_agent?: string | null
  created_at: string | null
  actor?: UserSummary | null
}

export interface AuditLookupOption {
  value: string
  label: string
}

export interface AuditLookups {
  events: AuditLookupOption[]
  entity_types: AuditLookupOption[]
}

export interface AppNotification {
  id: number
  type: string
  title: string
  body: string | null
  data: {
    ticket_id?: number
    hr_request_id?: number
    module?: string
    reference?: string
  } | null
  is_read: boolean
  read_at: string | null
  created_at: string | null
}

export interface NotificationFeed {
  unread_count: number
  notifications: AppNotification[]
}

export interface User {
  id: number
  full_name: string
  username: string
  employee_id: string
  epf_number: string
  email: string | null
  phone: string | null
  job_title: string | null
  is_active: boolean
  is_approved: boolean
  account_status?: AccountStatus
  last_login_at: string | null
  approved_at?: string | null
  password_changed_at?: string | null
  created_at: string | null
  updated_at?: string | null
  role?: Role
  branch?: Branch
  department?: Department
  ticket_statistics?: UserTicketStatistics
  recent_tickets?: Ticket[]
  audit_logs?: AuditLogEntry[]
  temporary_password?: string
}

export interface AdminDirectoryLookups {
  roles: Role[]
  branches: Branch[]
  departments: Department[]
}

export interface TicketPriority {
  id: number
  slug: string
  name: string
  level: number
  color_token: ColorToken
  response_minutes: number | null
  resolution_minutes: number | null
  is_default: boolean
  is_active?: boolean
  sort_order?: number
}

export interface TicketStatus {
  id: number
  slug: string
  name: string
  color_token: ColorToken
  is_open: boolean
  is_resolved: boolean
  is_closed: boolean
  requires_verification: boolean
  is_default?: boolean
  is_active?: boolean
  sort_order?: number
}

export interface TicketCategory {
  id: number
  slug: string
  name: string
  description: string | null
  parent_id: number | null
  default_priority_id: number | null
  is_active: boolean
  requires_item_details?: boolean
  sort_order?: number
}

export interface PublicLookups {
  branches: Branch[]
  departments: Department[]
  organisation_name?: string
  allow_self_registration?: boolean
  support_email?: string
  support_phone?: string
  support_hours?: string
}

export interface SystemSettings {
  organisation_name: string
  support_email: string
  support_phone: string
  support_hours: string
  allow_self_registration: string
}

export interface CatalogueOverview {
  categories: TicketCategory[]
  priorities: TicketPriority[]
  statuses: TicketStatus[]
}

export interface KnowledgeArticle {
  id: number
  slug: string
  title: string
  summary: string | null
  body?: string
  topic: string | null
  is_published: boolean
  author?: UserSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface ContactMethod {
  value: string
  label: string
}

export interface AttachmentConstraints {
  max_kilobytes: number
  max_per_ticket: number
  allowed_extensions: string[]
}

export interface Lookups extends PublicLookups {
  categories: TicketCategory[]
  priorities: TicketPriority[]
  statuses: TicketStatus[]
  contact_methods: ContactMethod[]
  issue_types: ContactMethod[]
  item_types: ContactMethod[]
  attachments: AttachmentConstraints
}

export interface TicketAttachment {
  id: number
  original_name: string
  mime_type: string
  size_bytes: number
  download_url: string
}

export interface UserSummary {
  id: number
  full_name: string
  is_staff?: boolean
}

export interface TicketComment {
  id: number
  body: string
  is_internal?: boolean
  is_system: boolean
  created_at: string | null
  edited_at: string | null
  author?: UserSummary | null
  attachments?: TicketAttachment[]
}

export interface TicketTransition {
  id: number
  slug: string
  name: string
}

export interface ChartBucket {
  id: number
  name: string
  slug: string | null
  count: number
  color_token: ColorToken | null
}

export interface TimeSeriesPoint {
  date: string
  count: number
}

export interface StaffWorkload {
  id: number
  full_name: string
  username?: string
  role?: string | null
  is_staff?: boolean
  open_tickets: number
  total_assigned: number
}

export interface TicketTimelineEvent {
  id: string
  type: string
  title: string
  description: string | null
  occurred_at: string | null
}

export interface TicketVerification {
  status: string
  requested_at: string | null
  completed_at: string | null
  is_blocked: boolean
  attempts?: number
  expires_at?: string | null
  method?: string | null
  code?: string | null
}

export interface Ticket {
  id: number
  reference: string
  subject: string
  description?: string
  contact_location?: string | null
  preferred_contact_method?: string | null
  contact_phone?: string | null
  additional_details?: string | null
  issue_type?: string | null
  issue_type_label?: string | null
  item_type?: string | null
  item_type_label?: string | null
  item_other?: string | null
  resolution_summary?: string | null
  resolved_at?: string | null
  resolved_by?: UserSummary | null
  awaiting_verification?: boolean
  verification?: TicketVerification | null
  verification_code?: string
  created_at: string | null
  updated_at: string | null
  category?: TicketCategory
  priority?: TicketPriority
  status?: TicketStatus
  branch?: Branch
  assigned_to_team?: boolean
  assignee?: UserSummary | null
  assignees?: UserSummary[]
  requester?: UserSummary | null
  attachments?: TicketAttachment[]
  comments?: TicketComment[]
  timeline?: TicketTimelineEvent[]
  allowed_transitions?: TicketTransition[]
}

export interface DashboardStatistics {
  total: number
  open: number
  in_progress: number
  awaiting_verification: number
  closed: number
}

export interface DashboardOverview {
  statistics: DashboardStatistics
  recent_tickets: Ticket[]
}

export interface StaffDashboardStatistics {
  total: number
  open: number
  assigned: number
  in_progress: number
  awaiting_user: number
  resolved: number
  awaiting_verification: number
  closed: number
  critical: number
}

export interface StaffDashboardCharts {
  by_status: ChartBucket[]
  by_priority: ChartBucket[]
  by_category: ChartBucket[]
  by_branch: ChartBucket[]
  over_time: TimeSeriesPoint[]
  workload: StaffWorkload[]
}

export interface StaffDashboardOverview {
  statistics: StaffDashboardStatistics
  charts: StaffDashboardCharts
  recent_tickets: Ticket[]
}

export interface ReportDurationMetric {
  minutes: number | null
  label: string
  sample_size: number
}

export interface ReportVerificationKpis {
  issued: number
  verified: number
  pending: number
  expired: number
  cancelled: number
  completion_rate: number
}

export interface ReportKpis {
  ticket_volume: number
  open_tickets: number
  closed_tickets: number
  reopened_tickets: number
  reopen_events: number
  critical_tickets: number
  open_critical_tickets: number
  aged_open_tickets: number
  awaiting_verification: number
  verified_tickets: number
  average_resolution: ReportDurationMetric
  average_first_response: ReportDurationMetric
  verification: ReportVerificationKpis
}

export interface ReportVolumePoint {
  date: string
  created: number
  closed: number
}

export interface ReportFilters {
  date_from: string | null
  date_to: string | null
  branch_id: number | null
  category_id: number | null
  priority_id: number | null
  assigned_to: number | null
  status_id: number | null
  granularity: 'day' | 'week' | 'month'
}

export interface ReportOverview {
  generated_at: string
  filters: ReportFilters
  kpis: ReportKpis
  charts: {
    volume: ReportVolumePoint[]
    by_branch: ChartBucket[]
    by_category: ChartBucket[]
    by_priority: ChartBucket[]
    by_status: ChartBucket[]
    by_staff: ChartBucket[]
    aging: ChartBucket[]
    verification: ChartBucket[]
  }
}

export interface HealthStatus {
  application: string
  environment: string
  api_version: string
  database: {
    connected: boolean
    driver: string
    name: string | null
  }
  server_time: string
}

/* ------------------------------------------------------------------ *
 * HR help desk — mirrors backend/app/Http/Resources/V1/Hr
 * ------------------------------------------------------------------ */

export interface HrRequestType {
  id: number
  hr_category_id: number
  slug: string
  name: string
  description: string | null
  sla_hours: number | null
  /** Staff routes only. */
  default_referral_department?: string | null
  sort_order: number
}

export interface HrCategory {
  id: number
  slug: string
  name: string
  description: string | null
  is_confidential: boolean
  default_sla_hours: number | null
  sort_order: number
  types?: HrRequestType[]
}

export interface HrStatus {
  id: number
  slug: string
  name: string
  color_token: ColorToken
  is_open: boolean
  is_terminal: boolean
  sort_order: number
}

export interface HrUserSummary {
  id: number
  full_name: string
  is_hr_staff: boolean
  username?: string
  role?: string | null
  open_requests?: number
  total_assigned?: number
}

/** Assignable HR desk agents with workload — mirrors IT StaffWorkload. */
export interface HrStaffWorkload {
  id: number
  full_name: string
  username?: string
  role?: string | null
  is_hr_staff?: boolean
  open_requests: number
  total_assigned: number
}

export interface HrAttachment {
  id: number
  original_name: string
  mime_type: string
  size_bytes: number
  created_at: string | null
  /** Relative to the API base (/api/v1). */
  download_url: string
}

export interface HrComment {
  id: number
  body: string
  is_system: boolean
  /** Only present on staff routes. */
  is_internal?: boolean
  created_at: string | null
  author?: HrUserSummary | null
  attachments?: HrAttachment[]
}

export interface HrStatusHistoryEntry {
  id: number
  from_status?: HrStatus | null
  to_status?: HrStatus | null
  /** Staff routes only. */
  remarks?: string | null
  changed_at: string | null
  changed_by?: HrUserSummary | null
}

export interface HrAssignment {
  id: number
  note: string | null
  assigned_at: string | null
  released_at: string | null
  assignee?: HrUserSummary | null
  assigned_by?: HrUserSummary | null
}

export type HrReferralStatus = string

export interface HrReferral {
  id: number
  hr_request_id: number
  department_slug: string
  status: HrReferralStatus
  brief: string | null
  response: string | null
  created_at: string | null
  responded_at: string | null
  closed_at: string | null
  created_by?: HrUserSummary | null
  assignee?: HrUserSummary | null
  responder?: HrUserSummary | null
}

export interface HrRequest {
  id: number
  reference: string
  employee_id: string | null
  employee_name: string | null
  department_name: string | null
  designation: string | null
  /** Present on detail payloads only. */
  description?: string
  is_confidential: boolean
  is_overdue?: boolean
  awaiting_verification?: boolean
  assigned_to_team?: boolean
  due_at: string | null
  created_at: string | null
  updated_at: string | null
  resolution_summary?: string | null
  resolved_at?: string | null
  closed_at?: string | null
  verification?: {
    status?: string | null
    completed_at?: string | null
  } | null
  category?: HrCategory
  request_type?: HrRequestType
  status?: HrStatus
  branch?: { id: number; name: string } | null
  assignee?: HrUserSummary | null
  resolved_by?: HrUserSummary | null
  requester?: HrUserSummary | null
  attachments?: HrAttachment[]
  comments?: HrComment[]
  timeline?: HrStatusHistoryEntry[]
  /** Staff payload extras. */
  assigned_at?: string | null
  escalated_at?: string | null
  escalated_to?: HrUserSummary | null
  assignments?: HrAssignment[]
  referrals?: HrReferral[]
}

export interface HrSlaType {
  id: number
  slug: string
  name: string
  sla_hours: number | null
  target_hours: number | null
  escalate_to_manager: boolean
  default_referral_department: string | null
}

export interface HrSlaCategory {
  id: number
  slug: string
  name: string
  is_confidential: boolean
  default_sla_hours: number | null
  target_hours: number | null
  escalate_to_manager: boolean
  types: HrSlaType[]
}

export interface HrReferralDepartment {
  slug: string
  name: string
}

export interface HrAttachmentConstraints {
  max_kilobytes: number
  max_per_request: number
  allowed_extensions: string[]
}

export interface HrLookups {
  categories: HrCategory[]
  statuses: HrStatus[]
  referral_departments: HrReferralDepartment[]
  attachments: HrAttachmentConstraints
  support_email?: string
  support_phone?: string
  support_hours?: string
}

export interface HrEmployeeDashboardStatistics {
  total: number
  open: number
  in_progress: number
  awaiting_verification: number
  closed: number
}

export interface HrEmployeeDashboardOverview {
  statistics: HrEmployeeDashboardStatistics
  recent_requests: HrRequest[]
}

export interface HrDashboardStatistics {
  total: number
  pending: number
  in_progress: number
  completed: number
  overdue: number
  complaints: number
}

export interface HrDashboardCategoryCount {
  id: number
  slug: string
  name: string
  count: number
}

export interface HrDashboardStatusCount {
  id: number
  slug: string
  name: string
  color_token: ColorToken
  count: number
}

export interface HrDashboardOverview {
  statistics: HrDashboardStatistics
  by_category: HrDashboardCategoryCount[]
  by_status: HrDashboardStatusCount[]
  recent_requests: HrRequest[]
}

export interface HrReportReferralKpis {
  total: number
  pending: number
  completed: number
  declined: number
  completion_rate: number
}

export interface HrReportKpis {
  request_volume: number
  open_requests: number
  completed_requests: number
  overdue_requests: number
  aged_open_requests: number
  complaints: number
  confidential_requests: number
  average_resolution: ReportDurationMetric
  average_first_response: ReportDurationMetric
  referrals: HrReportReferralKpis
}

export interface HrReportFilters {
  date_from: string | null
  date_to: string | null
  branch_id: number | null
  category_id: number | null
  request_type_id: number | null
  assigned_to: number | null
  status_id: number | null
  confidential: boolean | null
  granularity: 'day' | 'week' | 'month'
}

export interface HrReportOverview {
  generated_at: string
  filters: HrReportFilters
  kpis: HrReportKpis
  charts: {
    volume: ReportVolumePoint[]
    complaint_trends: ReportVolumePoint[]
    by_branch: ChartBucket[]
    by_category: ChartBucket[]
    by_request_type: ChartBucket[]
    by_status: ChartBucket[]
    by_staff: ChartBucket[]
    aging: ChartBucket[]
    referrals: ChartBucket[]
  }
}