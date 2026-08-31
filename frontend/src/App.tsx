import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import AppLayout from './components/AppLayout'
import ProtectedRoute from './components/ProtectedRoute'
import BookingDetailPage from './pages/BookingDetailPage'
import BookingsCalendarPage from './pages/BookingsCalendarPage'
import AiPackageAssistantPage from './pages/AiPackageAssistantPage'
import AiProvidersPage from './pages/AiProvidersPage'
import BookingsPage from './pages/BookingsPage'
import PackagesPage from './pages/PackagesPage'
import ConversationsPage from './pages/ConversationsPage'
import CustomerDetailPage from './pages/CustomerDetailPage'
import CustomersPage from './pages/CustomersPage'
import DashboardPage from './pages/DashboardPage'
import DealsPage from './pages/DealsPage'
import FlightsPage from './pages/FlightsPage'
import HajjUmrahGroupDetailPage from './pages/HajjUmrahGroupDetailPage'
import HajjUmrahPage from './pages/HajjUmrahPage'
import HotelDetailPage from './pages/HotelDetailPage'
import HotelsPage from './pages/HotelsPage'
import IntegrationDetailPage from './pages/IntegrationDetailPage'
import IntegrationsPage from './pages/IntegrationsPage'
import InvoicesPage from './pages/InvoicesPage'
import LeadsPage from './pages/LeadsPage'
import LoginPage from './pages/LoginPage'
import NotificationsPage from './pages/NotificationsPage'
import PackageBuilderPage from './pages/PackageBuilderPage'
import PricingRulesPage from './pages/PricingRulesPage'
import ProfilePage from './pages/ProfilePage'
import ProposalsPage from './pages/ProposalsPage'
import SalesStrategiesPage from './pages/SalesStrategiesPage'
import QuotationDetailPage from './pages/QuotationDetailPage'
import QuotationsPage from './pages/QuotationsPage'
import SalesDashboardPage from './pages/SalesDashboardPage'
import StudentVisaPage from './pages/StudentVisaPage'
import TasksPage from './pages/TasksPage'
import VisasPage from './pages/VisasPage'

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route element={<ProtectedRoute />}>
          <Route element={<AppLayout />}>
            <Route path="/dashboard" element={<DashboardPage />} />
            <Route path="/customers" element={<CustomersPage />} />
            <Route path="/customers/:id" element={<CustomerDetailPage />} />
            <Route path="/leads" element={<LeadsPage />} />
            <Route path="/deals" element={<DealsPage />} />
            <Route path="/tasks" element={<TasksPage />} />
            <Route path="/sales" element={<SalesDashboardPage />} />
            <Route path="/quotations" element={<QuotationsPage />} />
            <Route path="/quotations/:id" element={<QuotationDetailPage />} />
            <Route path="/proposals" element={<ProposalsPage />} />
            <Route path="/invoices" element={<InvoicesPage />} />
            <Route path="/packages" element={<PackagesPage />} />
            <Route path="/bookings" element={<BookingsPage />} />
            <Route path="/bookings/calendar" element={<BookingsCalendarPage />} />
            <Route path="/bookings/:id" element={<BookingDetailPage />} />
            <Route path="/flights" element={<FlightsPage />} />
            <Route path="/hotels" element={<HotelsPage />} />
            <Route path="/hotels/:id" element={<HotelDetailPage />} />
            <Route path="/visas" element={<VisasPage />} />
            <Route path="/hajj-umrah" element={<HajjUmrahPage />} />
            <Route path="/hajj-umrah/groups/:id" element={<HajjUmrahGroupDetailPage />} />
            <Route path="/student-visa" element={<StudentVisaPage />} />
            <Route path="/pricing-rules" element={<PricingRulesPage />} />
            <Route path="/package-builder" element={<PackageBuilderPage />} />
            <Route path="/notifications" element={<NotificationsPage />} />
            <Route path="/conversations" element={<ConversationsPage />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="/integrations" element={<IntegrationsPage />} />
            <Route path="/integrations/:id" element={<IntegrationDetailPage />} />
            <Route path="/ai-providers" element={<AiProvidersPage />} />
            <Route path="/ai-package-assistant" element={<AiPackageAssistantPage />} />
            <Route path="/sales-strategies" element={<SalesStrategiesPage />} />
          </Route>
        </Route>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </AuthProvider>
  )
}
