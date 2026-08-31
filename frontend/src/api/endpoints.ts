import client from './client'
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
  Lead,
  LoginResponse,
  PipelineFullResponse,
  ProfileResponse,
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
