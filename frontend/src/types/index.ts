// Shared API entity types for the Qudrix Travel CRM frontend.
// Fields are intentionally optional/defensive where the backend contract
// is still evolving (deals, 360 view, crm dashboard, funnel, follow-ups).

export interface User {
  id: number
  name: string
  email: string
  [key: string]: unknown
}

export interface LoginResponse {
  message: string
  user: User
  token: string
}

export interface ProfileResponse {
  user: User
  permissions?: string[]
}

export interface Customer {
  id: number
  name: string
  email?: string | null
  phone?: string | null
  type?: string | null
  status?: string | null
  address?: string | null
  company?: string | null
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export type LeadStatus =
  | 'new'
  | 'contacted'
  | 'qualified'
  | 'proposal'
  | 'won'
  | 'lost'
  | string

export interface Lead {
  id: number
  name: string
  email?: string | null
  phone?: string | null
  source?: string | null
  status: LeadStatus
  priority?: string | null
  estimated_value?: number | string | null
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface PipelineColumn {
  status: string
  leads?: Lead[]
  [key: string]: unknown
}

export interface PipelineFullResponse {
  data: PipelineColumn[] | Lead[]
  pipeline_value?: number
  [key: string]: unknown
}

export type DealStage =
  | 'new'
  | 'qualified'
  | 'proposal'
  | 'negotiation'
  | 'won'
  | 'lost'
  | string

export interface Deal {
  id: number
  title: string
  customer_id?: number | null
  lead_id?: number | null
  amount?: number | string | null
  stage: DealStage
  probability?: number | null
  expected_close_date?: string | null
  owner_id?: number | null
  notes?: string | null
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface DealPipelineColumn {
  stage: string
  deals?: Deal[]
  [key: string]: unknown
}

export interface Task {
  id: number
  title: string
  description?: string | null
  due_date?: string | null
  status?: string | null
  completed?: boolean
  completed_at?: string | null
  [key: string]: unknown
}

export interface TaskStats {
  total?: number
  completed?: number
  pending?: number
  due_today?: number
  overdue?: number
  [key: string]: unknown
}

export interface Booking {
  id: number
  [key: string]: unknown
}

export interface Quotation {
  id: number
  [key: string]: unknown
}

export interface Communication {
  id: number
  [key: string]: unknown
}

export interface Note {
  id: number
  [key: string]: unknown
}

export interface TimelineEvent {
  id?: number
  type?: string
  title?: string
  description?: string
  date?: string
  created_at?: string
  [key: string]: unknown
}

export interface Customer360Response {
  customer: Customer
  leads?: Lead[]
  deals?: Deal[]
  bookings?: Booking[]
  quotations?: Quotation[]
  communications?: Communication[]
  notes?: Note[]
  tags?: (string | { id: number; name: string })[]
  timeline?: TimelineEvent[]
}

export interface CrmDashboardResponse {
  total_leads?: number
  new_leads_this_month?: number
  conversion_rate?: number
  pipeline_value_by_stage?: Record<string, number>
  deals_won?: number
  deals_lost?: number
  tasks_due_today?: number
  upcoming_follow_ups?: FollowUp[]
}

export interface ConversionFunnelResponse {
  stages: { status: string; count: number }[]
  conversion_rate?: number
}

export interface FollowUp {
  id: number
  type?: string
  title?: string
  due_date?: string
  related_type?: string
  related_id?: number
  [key: string]: unknown
}

export interface Paginated<T> {
  data: T[]
  [key: string]: unknown
}

export interface ApiListResponse<T> {
  data: T[]
}

export interface ApiItemResponse<T> {
  message?: string
  data: T
}
