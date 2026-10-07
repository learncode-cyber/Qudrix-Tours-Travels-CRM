import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import ProtectedRoute from './components/ProtectedRoute'
import AppLayout from './layouts/AppLayout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import CustomersList from './pages/customers/CustomersList'
import CustomerForm from './pages/customers/CustomerForm'
import CustomerDetail from './pages/customers/CustomerDetail'
import LeadsList from './pages/leads/LeadsList'
import QuotationsList from './pages/quotations/QuotationsList'
import BookingsList from './pages/bookings/BookingsList'
import ProposalsList from './pages/proposals/ProposalsList'
import InvoicesList from './pages/invoices/InvoicesList'
import PaymentsList from './pages/payments/PaymentsList'
import PaymentForm from './pages/payments/PaymentForm'
import AgentsList from './pages/agents/AgentsList'
import AgentForm from './pages/agents/AgentForm'
import AgentDetail from './pages/agents/AgentDetail'
import VendorsList from './pages/vendors/VendorsList'
import VendorForm from './pages/vendors/VendorForm'
import BookingDetail from './pages/bookings/BookingDetail'
import HajjUmrahPackagesList from './pages/hajj-umrah/HajjUmrahPackagesList'
import HotelsList from './pages/hotels/HotelsList'
import StudentVisaList from './pages/student-visa/StudentVisaList'
import TrackingConfigPage from './pages/settings/TrackingConfigPage'
import SuppliersList from './pages/suppliers/SuppliersList'
import FlightsList from './pages/flights/FlightsList'
import TransportList from './pages/transport/TransportList'
import ComplaintsList from './pages/complaints/ComplaintsList'
import AnalyticsDashboard from './pages/analytics/AnalyticsDashboard'
import TenantSettingsPage from './pages/settings/TenantSettingsPage'
import ApiKeysPage from './pages/settings/ApiKeysPage'
import AIProvidersPage from './pages/ai/AIProvidersPage'
import AutomationsList from './pages/automation/AutomationsList'
import AutomationDetail from './pages/automation/AutomationDetail'
import WebhooksList from './pages/webhooks/WebhooksList'
import WebhookDetail from './pages/webhooks/WebhookDetail'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route
            path="/"
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="customers" element={<CustomersList />} />
            <Route path="customers/new" element={<CustomerForm />} />
            <Route path="customers/:id" element={<CustomerDetail />} />
            <Route path="leads" element={<LeadsList />} />
            <Route path="quotations" element={<QuotationsList />} />
            <Route path="proposals" element={<ProposalsList />} />
            <Route path="invoices" element={<InvoicesList />} />
            <Route path="payments" element={<PaymentsList />} />
            <Route path="payments/new" element={<PaymentForm />} />
            <Route path="bookings" element={<BookingsList />} />
            <Route path="bookings/:id" element={<BookingDetail />} />
            <Route path="hajj-umrah" element={<HajjUmrahPackagesList />} />
            <Route path="hotels" element={<HotelsList />} />
            <Route path="student-visas" element={<StudentVisaList />} />
            <Route path="settings/tracking" element={<TrackingConfigPage />} />
            <Route path="suppliers" element={<SuppliersList />} />
            <Route path="flights" element={<FlightsList />} />
            <Route path="transport" element={<TransportList />} />
            <Route path="complaints" element={<ComplaintsList />} />
            <Route path="analytics" element={<AnalyticsDashboard />} />
            <Route path="settings/organization" element={<TenantSettingsPage />} />
            <Route path="settings/api-keys" element={<ApiKeysPage />} />
            <Route path="ai-providers" element={<AIProvidersPage />} />
            <Route path="automations" element={<AutomationsList />} />
            <Route path="automations/:id" element={<AutomationDetail />} />
            <Route path="webhooks" element={<WebhooksList />} />
            <Route path="webhooks/:id" element={<WebhookDetail />} />
            <Route path="agents" element={<AgentsList />} />
            <Route path="agents/new" element={<AgentForm />} />
            <Route path="agents/:id" element={<AgentDetail />} />
            <Route path="vendors" element={<VendorsList />} />
            <Route path="vendors/new" element={<VendorForm />} />
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
