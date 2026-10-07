import axios from 'axios'

// NOTE: the backend has an inconsistency (documented, not fixed here to
// avoid restructuring the whole routes/api.php bracket structure):
// auth endpoints (/register, /login) live under a '/v1' prefix, but
// every other resource route (customers, leads, quotations, ...) does
// NOT have that prefix. Both base URLs point at the same Laravel app.
const ROOT = import.meta.env.VITE_API_BASE_URL?.replace(/\/v1\/?$/, '') || 'http://localhost:8000/api'

export const AUTH_BASE = `${ROOT}/v1`
export const API_BASE = ROOT
// FIX (2026-09-22, webhook UI): routes/api-public.php's `admin/api/*`
// groups (webhooks, webhooks-advanced, webhooks-monitoring) are loaded via
// bootstrap/app.php's `then` callback specifically so they are NOT
// wrapped in the automatic `/api` prefix Laravel adds for the `api:` file
// (see that file's own comment — doubling it would have broken these
// exact routes). API_BASE already ends in `/api`, so calls to these admin
// routes must go through this base instead, without it.
export const ADMIN_BASE = ROOT.replace(/\/api\/?$/, '')

export const api = axios.create({ baseURL: API_BASE })
export const authApi = axios.create({ baseURL: AUTH_BASE })
export const adminApi = axios.create({ baseURL: ADMIN_BASE })

function attachAuthHeader(config: any) {
  const token = localStorage.getItem('qudrix_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}

api.interceptors.request.use(attachAuthHeader)
authApi.interceptors.request.use(attachAuthHeader)
adminApi.interceptors.request.use(attachAuthHeader)

function handle401(error: any) {
  if (error.response?.status === 401) {
    localStorage.removeItem('qudrix_token')
    if (window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
  }
  return Promise.reject(error)
}

api.interceptors.response.use((r) => r, handle401)
authApi.interceptors.response.use((r) => r, handle401)
adminApi.interceptors.response.use((r) => r, handle401)
