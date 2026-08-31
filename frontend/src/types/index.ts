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

export type BookingType = 'individual' | 'group' | 'corporate' | string
export type BookingStatus = 'pending' | 'confirmed' | 'cancelled' | 'completed' | string
export type BookingPaymentStatus = 'pending' | 'paid' | 'partial' | string

export interface Booking {
  id: number
  booking_number?: string
  customer_id?: number | null
  package_id?: number | null
  lead_id?: number | null
  booking_type: BookingType
  status: BookingStatus
  travel_date?: string | null
  return_date?: string | null
  number_of_travelers?: number | null
  total_amount?: number | string | null
  currency?: string | null
  payment_status?: BookingPaymentStatus
  visa_required?: boolean
  special_requests?: string | null
  notes?: string | null
  customer?: { id: number; name?: string } | null
  package?: { id: number; name?: string } | null
  travelers?: BookingTraveler[]
  flight_bookings?: FlightBooking[]
  hotel_bookings?: unknown[]
  visa_applications?: VisaApplication[]
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface BookingTraveler {
  id: number
  name?: string
  [key: string]: unknown
}

export interface BookingStats {
  total?: number
  pending?: number
  confirmed?: number
  cancelled?: number
  total_travelers?: number
  total_revenue?: number | string
  upcoming_bookings?: number
  [key: string]: unknown
}

export interface Package {
  id: number
  name: string
  code?: string | null
  type?: string | null
  description?: string | null
  days?: number | null
  nights?: number | null
  destination?: string | null
  base_price?: number | string | null
  is_active?: boolean
  status?: string | null
  [key: string]: unknown
}

export interface Flight {
  id: number
  airline_code?: string | null
  flight_number?: string | null
  departure_airport?: string | null
  arrival_airport?: string | null
  departure_date?: string | null
  arrival_date?: string | null
  departure_time?: string | null
  arrival_time?: string | null
  aircraft_type?: string | null
  total_seats?: number | null
  available_seats?: number | null
  price_per_seat?: number | string | null
  [key: string]: unknown
}

export interface FlightBooking {
  id: number
  flight_id?: number
  booking_id?: number
  booking_traveler_id?: number
  seat_number?: string | null
  cabin_class?: string | null
  fare_type?: string | null
  status?: string | null
  [key: string]: unknown
}

export interface Hotel {
  id: number
  name: string
  city?: string | null
  country?: string | null
  address?: string | null
  phone?: string | null
  email?: string | null
  star_rating?: number | null
  total_rooms?: number | null
  available_rooms?: number | null
  price_per_night?: number | string | null
  currency?: string | null
  [key: string]: unknown
}

export interface HotelRoomType {
  id: number
  hotel_id?: number
  name: string
  capacity?: number | null
  total_rooms?: number | null
  available_rooms?: number | null
  price_per_night?: number | string | null
  currency?: string | null
  [key: string]: unknown
}

export interface Transport {
  id: number
  transport_type?: string | null
  vehicle_name?: string | null
  vehicle_number?: string | null
  pickup_location?: string | null
  dropoff_location?: string | null
  pickup_date?: string | null
  pickup_time?: string | null
  capacity?: number | null
  price_per_seat?: number | string | null
  currency?: string | null
  driver_name?: string | null
  driver_phone?: string | null
  status?: string | null
  [key: string]: unknown
}

export type PricingRuleFactor =
  | 'season'
  | 'demand'
  | 'group_size'
  | 'customer_segment'
  | 'booking_timing'
  | string
export type PricingAdjustmentType = 'percentage' | 'fixed' | string

export interface PricingRule {
  id: number
  name: string
  factor: PricingRuleFactor
  season_start?: string | null
  season_end?: string | null
  min_group_size?: number | null
  max_group_size?: number | null
  booking_days_before_travel_min?: number | null
  booking_days_before_travel_max?: number | null
  customer_segment_id?: number | null
  adjustment_type: PricingAdjustmentType
  adjustment_value: number | string
  priority?: number | null
  is_active: boolean
  [key: string]: unknown
}

export interface PricingAppliedRule {
  rule_id: number
  name: string
  factor: string
  adjustment_type: string
  adjustment_value: number
  amount: number
  price_after: number
}

export interface PricingPreviewResult {
  base_cost: number
  applied_rules: PricingAppliedRule[]
  final_price: number
  calculation_log_id: number
  [key: string]: unknown
}

export type PackageBuilderComponentType = 'hotel' | 'flight' | 'transport'

export interface PackageBuilderComponentInput {
  type: PackageBuilderComponentType
  reference_id: number
  quantity: number
}

export interface PackageBuilderResolvedLine {
  type: PackageBuilderComponentType
  reference_id: number
  description: string
  quantity: number
  unit_price: number
  line_total: number
}

export interface PackageBuilderResult {
  destination: string
  travel_date: string
  group_size: number
  components: PackageBuilderResolvedLine[]
  pricing: PricingPreviewResult
  package?: Package
  quotation?: Quotation
  [key: string]: unknown
}

export type RoomBlockStatus = 'held' | 'partially_released' | 'released' | string

export interface RoomBlock {
  id: number
  hotel_id: number
  hotel_room_type_id: number
  group_booking_id?: number | null
  name?: string | null
  blocked_rooms: number
  released_rooms?: number | null
  start_date?: string | null
  end_date?: string | null
  status: RoomBlockStatus
  notes?: string | null
  hotel?: { id: number; name?: string } | null
  roomType?: { id: number; name?: string } | null
  [key: string]: unknown
}

export type VisaStatus = 'pending' | 'submitted' | 'approved' | 'rejected' | string

export interface VisaApplication {
  id: number
  booking_id?: number | null
  booking_traveler_id?: number | null
  destination_country?: string | null
  embassy?: string | null
  embassy_id?: number | null
  visa_type?: string | null
  application_date?: string | null
  submission_date?: string | null
  appointment_date?: string | null
  approval_date?: string | null
  visa_number?: string | null
  issue_date?: string | null
  expiry_date?: string | null
  status: VisaStatus
  agency_name?: string | null
  notes?: string | null
  [key: string]: unknown
}

export type VisaChecklistItemStatus = 'missing' | 'submitted' | 'verified' | 'rejected' | string

export interface VisaChecklistItem {
  id: number
  document_name: string
  status: VisaChecklistItemStatus
  [key: string]: unknown
}

export interface VisaBookingStatus {
  total_travelers?: number
  approved?: number
  pending?: number
  submitted?: number
  expired?: number
  [key: string]: unknown
}

export interface Embassy {
  id: number
  name: string
  country?: string | null
  city?: string | null
  address?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  website?: string | null
  average_processing_days?: number | null
  notes?: string | null
  [key: string]: unknown
}

export type HajjUmrahPackageStatus = 'active' | 'inactive' | 'discontinued' | string

export interface HajjPackage {
  id: number
  name: string
  description?: string | null
  duration_days: number
  price: number | string
  currency?: string
  max_capacity: number
  rituals_included?: string[] | null
  accommodations?: Record<string, unknown> | null
  status: HajjUmrahPackageStatus
  [key: string]: unknown
}

export interface UmrahPackage {
  id: number
  name: string
  description?: string | null
  duration_days: number
  price: number | string
  currency?: string
  max_capacity: number
  rituals_included?: string[] | null
  accommodations?: Record<string, unknown> | null
  status: HajjUmrahPackageStatus
  [key: string]: unknown
}

export type HajjUmrahGroupStatus = 'planned' | 'confirmed' | 'departed' | 'completed' | 'cancelled' | string
export type HajjUmrahPackageType = 'hajj' | 'umrah'

export interface HajjUmrahGroup {
  id: number
  package_type: HajjUmrahPackageType
  package_id: number
  name: string
  departure_date: string
  return_date: string
  group_leader_id?: number | null
  agent_id?: number | null
  capacity: number
  status: HajjUmrahGroupStatus
  pilgrims_count?: number
  seats_available?: number
  pilgrims?: Pilgrim[]
  [key: string]: unknown
}

export interface HajjUmrahGroupReport {
  group: string
  total_pilgrims: number
  seats_available: number
  by_status: Record<string, number>
  total_amount_due: number
  total_amount_paid: number
  total_balance: number
  unassigned_rooms: number
  [key: string]: unknown
}

export type PilgrimStatus = 'registered' | 'confirmed' | 'travelled' | 'completed' | 'cancelled' | string
export type PilgrimPaymentStatus = 'pending' | 'partial' | 'paid' | string

export interface Pilgrim {
  id: number
  hajj_umrah_group_id: number
  booking_id?: number | null
  customer_id?: number | null
  name: string
  passport_number?: string | null
  passport_expiry?: string | null
  gender?: string | null
  date_of_birth?: string | null
  mahram_name?: string | null
  room_number?: string | null
  hotel_id?: number | null
  transport_assignment?: string | null
  visa_application_id?: number | null
  payment_status: PilgrimPaymentStatus
  amount_due: number | string
  amount_paid: number | string
  status: PilgrimStatus
  [key: string]: unknown
}

export type StudentVisaApplicationStatus =
  | 'inquiry'
  | 'documents_pending'
  | 'applied'
  | 'offer_received'
  | 'visa_appointment_scheduled'
  | 'visa_submitted'
  | 'visa_approved'
  | 'visa_rejected'
  | 'enrolled'
  | string

export type StudentVisaStatus = 'not_applied' | 'submitted' | 'approved' | 'rejected' | string

export interface StudentVisaApplication {
  id: number
  lead_id?: number | null
  customer_id?: number | null
  student_name: string
  date_of_birth?: string | null
  destination_country: string
  university?: string | null
  course?: string | null
  intake?: string | null
  application_status: StudentVisaApplicationStatus
  offer_letter_received?: boolean
  offer_letter_date?: string | null
  embassy_appointment_date?: string | null
  visa_status: StudentVisaStatus
  assigned_counsellor_id?: number | null
  service_fee?: number | string | null
  service_fee_currency?: string | null
  payment_status: PilgrimPaymentStatus
  notes?: string | null
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
