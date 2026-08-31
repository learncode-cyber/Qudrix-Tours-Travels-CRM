import client, { downloadFile } from './client'
import type {
  ApiItemResponse,
  ApiListResponse,
  ConversionFunnelResponse,
  CrmDashboardResponse,
  Customer,
  Customer360Response,
  Deal,
  DealPipelineColumn,
  FollowUp,
  Invoice,
  InvoiceStats,
  Lead,
  LoginResponse,
  PipelineFullResponse,
  ProfileResponse,
  Proposal,
  ProposalStats,
  Quotation,
  QuotationStats,
  SalesDashboardResponse,
  Task,
  TaskStats,
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
