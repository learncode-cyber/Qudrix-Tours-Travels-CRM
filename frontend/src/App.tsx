import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import AppLayout from './components/AppLayout'
import ProtectedRoute from './components/ProtectedRoute'
import CustomerDetailPage from './pages/CustomerDetailPage'
import CustomersPage from './pages/CustomersPage'
import DashboardPage from './pages/DashboardPage'
import DealsPage from './pages/DealsPage'
import InvoicesPage from './pages/InvoicesPage'
import LeadsPage from './pages/LeadsPage'
import LoginPage from './pages/LoginPage'
import ProposalsPage from './pages/ProposalsPage'
import QuotationDetailPage from './pages/QuotationDetailPage'
import QuotationsPage from './pages/QuotationsPage'
import SalesDashboardPage from './pages/SalesDashboardPage'
import TasksPage from './pages/TasksPage'

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
          </Route>
        </Route>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </AuthProvider>
  )
}
