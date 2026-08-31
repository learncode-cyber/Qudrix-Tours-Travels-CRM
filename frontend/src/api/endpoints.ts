import client, { downloadFile } from './client'
import type {
  ApiItemResponse,
  ApiListResponse,
  Booking,
  BookingStats,
  ConversionFunnelResponse,
  CrmDashboardResponse,
  Customer,
  Customer360Response,
  Deal,
  DealPipelineColumn,
  Embassy,
  Flight,
  FlightBooking,
  FollowUp,
  Hotel,
  HotelRoomType,
  Invoice,
  InvoiceStats,
  Lead,
  LoginResponse,
  Package,
  PipelineFullResponse,
  ProfileResponse,
  Proposal,
  ProposalStats,
  Quotation,
  QuotationStats,
  RoomBlock,
  SalesDashboardResponse,
  Task,
  TaskStats,
  VisaApplication,
  VisaBookingStatus,
  VisaChecklistItem,
} from '../types'

// --- Auth ---
export const login = (email: string, password: string) =>
  client.post<LoginResponse>('/login', { email, password })

export const getProfile = () => client.get<ProfileResponse>('/profile')

// --- Customers ---
export const listCustomers = () => client.get<ApiListResponse<Customer>>('/customers')
export const getCustomer = (id: number | string) =>
  client.get<ApiItemResponse<Customer>>(`/customers/${id}`)
export const createCustomer = (payload: Partial<Customer>) =>
  client.post<ApiItemResponse<Customer>>('/customers', payload)
export const updateCustomer = (id: number | string, payload: Partial<Customer>) =>
  client.put<ApiItemResponse<Customer>>(`/customers/${id}`, payload)
export const deleteCustomer = (id: number | string) => client.delete(`/customers/${id}`)
export const getCustomer360 = (id: number | string) =>
  client.get<ApiItemResponse<Customer360Response>>(`/customers/${id}/360`)

// --- Leads ---
export const listLeads = () => client.get<ApiListResponse<Lead>>('/leads')
export const getLead = (id: number | string) => client.get<ApiItemResponse<Lead>>(`/leads/${id}`)
export const createLead = (payload: Partial<Lead>) =>
  client.post<ApiItemResponse<Lead>>('/leads', payload)
export const updateLead = (id: number | string, payload: Partial<Lead>) =>
  client.put<ApiItemResponse<Lead>>(`/leads/${id}`, payload)
export const deleteLead = (id: number | string) => client.delete(`/leads/${id}`)
export const updateLeadStatus = (id: number | string, status: string) =>
  client.put<ApiItemResponse<Lead>>(`/leads/${id}/status`, { status })

// --- Pipeline (leads) ---
export const getFullPipeline = () => client.get<PipelineFullResponse>('/pipeline/full')

// --- Deals ---
export const listDeals = () => client.get<ApiListResponse<Deal>>('/deals')
export const getDeal = (id: number | string) => client.get<ApiItemResponse<Deal>>(`/deals/${id}`)
export const createDeal = (payload: Partial<Deal>) =>
  client.post<ApiItemResponse<Deal>>('/deals', payload)
export const updateDeal = (id: number | string, payload: Partial<Deal>) =>
  client.put<ApiItemResponse<Deal>>(`/deals/${id}`, payload)
export const deleteDeal = (id: number | string) => client.delete(`/deals/${id}`)
export const getDealsPipeline = () =>
  client.get<{ data: DealPipelineColumn[] }>('/deals/pipeline')

// --- Tasks ---
export const listTasks = () => client.get<ApiListResponse<Task>>('/tasks')
export const createTask = (payload: Partial<Task>) =>
  client.post<ApiItemResponse<Task>>('/tasks', payload)
export const completeTask = (id: number | string) =>
  client.put<ApiItemResponse<Task>>(`/tasks/${id}/complete`)
export const getTaskStats = () => client.get<TaskStats>('/tasks/stats')

// --- CRM analytics ---
// All three wrap their payload in {data: ...}, same as every other
// endpoint in this API — matches ApiItemResponse/ApiListResponse below.
export const getCrmDashboard = () =>
  client.get<ApiItemResponse<CrmDashboardResponse>>('/crm/dashboard')
export const getConversionFunnel = () =>
  client.get<ApiItemResponse<ConversionFunnelResponse>>('/crm/conversion-funnel')
export const getFollowUpsCalendar = (from?: string, to?: string) =>
  client.get<ApiListResponse<FollowUp>>('/crm/follow-ups/calendar', { params: { from, to } })

// --- Quotations ---
export const listQuotations = () => client.get<ApiListResponse<Quotation>>('/quotations')
export const getQuotation = (id: number | string) =>
  client.get<ApiItemResponse<Quotation>>(`/quotations/${id}`)
export const createQuotation = (payload: Partial<Quotation>) =>
  client.post<ApiItemResponse<Quotation>>('/quotations', payload)
export const updateQuotation = (id: number | string, payload: Partial<Quotation>) =>
  client.put<ApiItemResponse<Quotation>>(`/quotations/${id}`, payload)
export const submitQuotationForApproval = (id: number | string) =>
  client.post<ApiItemResponse<Quotation>>(`/quotations/${id}/submit-for-approval`)
export const approveQuotation = (id: number | string) =>
  client.post<ApiItemResponse<Quotation>>(`/quotations/${id}/approve`)
export const sendQuotation = (id: number | string) =>
  client.post<ApiItemResponse<Quotation>>(`/quotations/${id}/send`)
export const getQuotationStats = () => client.get<ApiItemResponse<QuotationStats>>('/quotations/stats')
export const downloadQuotationPdf = (id: number | string, filename: string) =>
  downloadFile(`/quotations/${id}/pdf`, filename)
export const generateInvoiceFromQuotation = (id: number | string) =>
  client.post<ApiItemResponse<Invoice>>(`/quotations/${id}/generate-invoice`)
export const getCustomerQuotations = (customerId: number | string) =>
  client.get<ApiListResponse<Quotation>>(`/customers/${customerId}/quotations`)

// --- Proposals ---
export const listProposals = () => client.get<ApiListResponse<Proposal>>('/proposals')
export const getProposal = (id: number | string) =>
  client.get<ApiItemResponse<Proposal>>(`/proposals/${id}`)
export const createProposalFromQuotation = (payload: {
  quotation_id: number | string
  title: string
  expiry_date?: string
}) => client.post<ApiItemResponse<Proposal>>('/proposals/from-quotation', payload)
export const sendProposal = (id: number | string) =>
  client.post<ApiItemResponse<Proposal>>(`/proposals/${id}/send`)
export const signProposal = (id: number | string) =>
  client.post<ApiItemResponse<Proposal>>(`/proposals/${id}/sign`)
export const rejectProposal = (id: number | string) =>
  client.post<ApiItemResponse<Proposal>>(`/proposals/${id}/reject`)
export const getProposalStats = () => client.get<ApiItemResponse<ProposalStats>>('/proposals/stats')

// --- Invoices ---
export const listInvoices = () => client.get<ApiListResponse<Invoice>>('/invoices')
export const getInvoice = (id: number | string) =>
  client.get<ApiItemResponse<Invoice>>(`/invoices/${id}`)
export const createInvoice = (payload: Partial<Invoice>) =>
  client.post<ApiItemResponse<Invoice>>('/invoices', payload)
export const recordInvoicePayment = (id: number | string, amount: number) =>
  client.post<ApiItemResponse<Invoice>>(`/invoices/${id}/record-payment`, { amount })
export const sendInvoice = (id: number | string) =>
  client.post<ApiItemResponse<Invoice>>(`/invoices/${id}/send`)
export const getInvoiceStats = () => client.get<ApiItemResponse<InvoiceStats>>('/invoices/stats')
export const downloadInvoicePdf = (id: number | string, filename: string) =>
  downloadFile(`/invoices/${id}/pdf`, filename)

// --- Sales dashboard ---
export const getSalesDashboard = () =>
  client.get<ApiItemResponse<SalesDashboardResponse>>('/sales/dashboard')

// --- Packages (simple lookup for booking form) ---
export const listPackages = () => client.get<ApiListResponse<Package>>('/packages')
export const getPackage = (id: number | string) =>
  client.get<ApiItemResponse<Package>>(`/packages/${id}`)
export const createPackage = (payload: Partial<Package>) =>
  client.post<ApiItemResponse<Package>>('/packages', payload)
export const updatePackage = (id: number | string, payload: Partial<Package>) =>
  client.put<ApiItemResponse<Package>>(`/packages/${id}`, payload)
export const deletePackage = (id: number | string) => client.delete(`/packages/${id}`)

// --- Bookings ---
export const listBookings = () => client.get<ApiListResponse<Booking>>('/bookings')
export const getBooking = (id: number | string) =>
  client.get<ApiItemResponse<Booking>>(`/bookings/${id}`)
export const createBooking = (payload: Partial<Booking>) =>
  client.post<ApiItemResponse<Booking>>('/bookings', payload)
export const updateBooking = (id: number | string, payload: Partial<Booking>) =>
  client.put<ApiItemResponse<Booking>>(`/bookings/${id}`, payload)
export const deleteBooking = (id: number | string) => client.delete(`/bookings/${id}`)
export const confirmBooking = (id: number | string) =>
  client.post<ApiItemResponse<Booking>>(`/bookings/${id}/confirm`)
export const cancelBooking = (id: number | string) =>
  client.post<ApiItemResponse<Booking>>(`/bookings/${id}/cancel`)
export const getBookingStats = () => client.get<ApiItemResponse<BookingStats>>('/bookings/stats')
export const getBookingsCalendar = (from?: string, to?: string) =>
  client.get<ApiListResponse<Booking>>('/bookings/calendar', { params: { from, to } })

// --- Flights ---
export const listFlights = () => client.get<ApiListResponse<Flight>>('/flights')
export const getFlight = (id: number | string) =>
  client.get<ApiItemResponse<Flight>>(`/flights/${id}`)
export const createFlight = (payload: Partial<Flight>) =>
  client.post<ApiItemResponse<Flight>>('/flights', payload)
export const updateFlight = (id: number | string, payload: Partial<Flight>) =>
  client.put<ApiItemResponse<Flight>>(`/flights/${id}`, payload)
export const deleteFlight = (id: number | string) => client.delete(`/flights/${id}`)
export const bookFlightSeat = (payload: {
  flight_id: number | string
  booking_id: number | string
  // The API takes an array of traveler IDs and auto-assigns seats — it
  // does not accept a single traveler + explicit seat number.
  travelers: (number | string)[]
  cabin_class?: string
  baggage_allowance?: string
  fare_type?: string
}) => client.post<{ message: string; data: { pnr: string } }>('/flights/book', payload)
export const cancelFlightBooking = (id: number | string) =>
  client.post<ApiItemResponse<FlightBooking>>(`/flight-bookings/${id}/cancel`)

// --- Hotels ---
export const listHotels = () => client.get<ApiListResponse<Hotel>>('/hotels')
export const getHotel = (id: number | string) =>
  client.get<ApiItemResponse<Hotel>>(`/hotels/${id}`)
export const createHotel = (payload: Partial<Hotel>) =>
  client.post<ApiItemResponse<Hotel>>('/hotels', payload)
export const updateHotel = (id: number | string, payload: Partial<Hotel>) =>
  client.put<ApiItemResponse<Hotel>>(`/hotels/${id}`, payload)
export const deleteHotel = (id: number | string) => client.delete(`/hotels/${id}`)
export const listHotelRoomTypes = (hotelId: number | string) =>
  client.get<ApiListResponse<HotelRoomType>>(`/hotels/${hotelId}/room-types`)
export const createHotelRoomType = (hotelId: number | string, payload: Partial<HotelRoomType>) =>
  client.post<ApiItemResponse<HotelRoomType>>(`/hotels/${hotelId}/room-types`, payload)
export const bookHotelRooms = (payload: {
  hotel_id: number | string
  hotel_room_type_id?: number | string
  booking_id: number | string
  check_in_date: string
  check_out_date: string
  number_of_rooms: number
  room_type: string
}) => client.post<ApiItemResponse<unknown>>('/hotels/book', payload)

// --- Room blocks ---
export const listRoomBlocks = () => client.get<ApiListResponse<RoomBlock>>('/room-blocks')
export const getRoomBlock = (id: number | string) =>
  client.get<ApiItemResponse<RoomBlock>>(`/room-blocks/${id}`)
export const createRoomBlock = (payload: Partial<RoomBlock>) =>
  client.post<ApiItemResponse<RoomBlock>>('/room-blocks', payload)
export const deleteRoomBlock = (id: number | string) => client.delete(`/room-blocks/${id}`)
export const releaseRoomBlock = (id: number | string, rooms: number) =>
  client.post<ApiItemResponse<RoomBlock>>(`/room-blocks/${id}/release`, { rooms })

// --- Visas ---
export const listVisas = () => client.get<ApiListResponse<VisaApplication>>('/visas')
export const getVisa = (id: number | string) =>
  client.get<ApiItemResponse<VisaApplication>>(`/visas/${id}`)
export const createVisa = (payload: Partial<VisaApplication>) =>
  client.post<ApiItemResponse<VisaApplication>>('/visas', payload)
export const updateVisa = (id: number | string, payload: Partial<VisaApplication>) =>
  client.put<ApiItemResponse<VisaApplication>>(`/visas/${id}`, payload)
export const deleteVisa = (id: number | string) => client.delete(`/visas/${id}`)
export const submitVisa = (id: number | string) =>
  client.post<ApiItemResponse<VisaApplication>>(`/visas/${id}/submit`)
export const approveVisa = (id: number | string) =>
  client.post<ApiItemResponse<VisaApplication>>(`/visas/${id}/approve`)
export const assignVisa = (id: number | string, payload: Record<string, unknown>) =>
  client.post<ApiItemResponse<VisaApplication>>(`/visas/${id}/assign`, payload)
export const getVisaChecklist = (id: number | string) =>
  client.get<ApiListResponse<VisaChecklistItem>>(`/visas/${id}/checklist`)
export const updateVisaChecklistItem = (
  id: number | string,
  itemId: number | string,
  status: string,
) => client.put<ApiItemResponse<VisaChecklistItem>>(`/visas/${id}/checklist/${itemId}`, { status })
export const getVisaBookingStatus = (bookingId: number | string) =>
  client.get<ApiItemResponse<VisaBookingStatus>>(`/visas/booking/${bookingId}/status`)
export const checkVisaExpiryReminders = (days?: number) =>
  client.post<ApiItemResponse<{ visa_reminders_created: number; passport_reminders_created: number }>>(
    '/visas/check-expiry-reminders',
    days ? { days } : {},
  )

// --- Embassies ---
export const listEmbassies = () => client.get<ApiListResponse<Embassy>>('/embassies')
export const getEmbassy = (id: number | string) =>
  client.get<ApiItemResponse<Embassy>>(`/embassies/${id}`)
export const createEmbassy = (payload: Partial<Embassy>) =>
  client.post<ApiItemResponse<Embassy>>('/embassies', payload)
export const updateEmbassy = (id: number | string, payload: Partial<Embassy>) =>
  client.put<ApiItemResponse<Embassy>>(`/embassies/${id}`, payload)
export const deleteEmbassy = (id: number | string) => client.delete(`/embassies/${id}`)
