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
  customer_type?: string | null
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

export type QuotationStatus =
  | 'draft'
  | 'pending_approval'
  | 'approved'
  | 'sent'
  | 'accepted'
  | 'rejected'
  | string

export interface QuotationItem {
  id?: number
  description: string
  quantity: number | string
  unit_price: number | string
  tax_rate?: number | string | null
  discount?: number | string | null
  package_id?: number | string | null
  total?: number | string | null
  [key: string]: unknown
}

export interface Quotation {
  id: number
  quotation_number?: string
  lead_id?: number | null
  customer_id?: number | null
  lead?: { id: number; name?: string } | null
  customer?: { id: number; name?: string } | null
  subject?: string
  description?: string | null
  status: QuotationStatus
  currency?: string
  subtotal?: number | string | null
  tax_amount?: number | string | null
  discount_amount?: number | string | null
  total_amount?: number | string | null
  valid_until?: string | null
  notes?: string | null
  version?: number
  items?: QuotationItem[]
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface QuotationStats {
  draft?: number
  sent?: number
  accepted?: number
  rejected?: number
  [key: string]: unknown
}

export type ProposalStatus = 'draft' | 'sent' | 'signed' | 'rejected' | string

export interface Proposal {
  id: number
  proposal_number?: string
  quotation_id?: number | null
  lead_id?: number | null
  status: ProposalStatus
  title?: string
  proposal_date?: string | null
  expiry_date?: string | null
  sent_date?: string | null
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface ProposalStats {
  draft?: number
  sent?: number
  signed?: number
  rejected?: number
  [key: string]: unknown
}

export type InvoiceStatus = 'draft' | 'sent' | 'partially_paid' | 'paid' | 'overdue' | string

export interface InvoiceItem {
  id?: number
  description: string
  quantity: number | string
  unit_price: number | string
  tax_rate?: number | string | null
  discount?: number | string | null
  total?: number | string | null
  [key: string]: unknown
}

export interface Invoice {
  id: number
  invoice_number?: string
  customer_id?: number | null
  booking_id?: number | null
  quotation_id?: number | null
  customer?: { id: number; name?: string } | null
  status: InvoiceStatus
  subtotal?: number | string | null
  tax_amount?: number | string | null
  discount_amount?: number | string | null
  total_amount?: number | string | null
  paid_amount?: number | string | null
  currency?: string
  issue_date?: string | null
  due_date?: string | null
  notes?: string | null
  items?: InvoiceItem[]
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface InvoiceStats {
  total?: number
  outstanding?: number
  paid?: number
  overdue?: number
  [key: string]: unknown
}

export interface TopPackage {
  package_id?: number
  name?: string
  count?: number
  revenue?: number | string
  [key: string]: unknown
}

export interface SalesDashboardResponse {
  revenue_this_month?: number | string
  quotation_conversion_rate?: number
  invoice_collection_rate?: number
  outstanding_amount?: number | string
  top_packages?: TopPackage[]
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
