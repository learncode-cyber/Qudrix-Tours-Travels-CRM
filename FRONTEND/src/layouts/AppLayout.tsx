import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { LayoutDashboard, Users, UserPlus, FileText, Calendar, LogOut, FileSignature, Receipt, Wallet, Briefcase, Truck, Landmark, Hotel, GraduationCap, Settings, Plane, Bus, Building2, AlertTriangle, BarChart3, Building, KeyRound, Bot, Workflow, Zap } from 'lucide-react'
import { useAuth } from '../contexts/AuthContext'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/customers', label: 'Customers', icon: Users },
  { to: '/leads', label: 'Leads', icon: UserPlus },
  { to: '/quotations', label: 'Quotations', icon: FileText },
  { to: '/proposals', label: 'Proposals', icon: FileSignature },
  { to: '/invoices', label: 'Invoices', icon: Receipt },
  { to: '/payments', label: 'Payments', icon: Wallet },
  { to: '/bookings', label: 'Bookings', icon: Calendar },
  { to: '/hajj-umrah', label: 'Hajj / Umrah', icon: Landmark },
  { to: '/student-visas', label: 'Student Visa / Al-Azhar', icon: GraduationCap },
  { to: '/hotels', label: 'Hotels', icon: Hotel },
  { to: '/flights', label: 'Flights', icon: Plane },
  { to: '/transport', label: 'Transport', icon: Bus },
  { to: '/suppliers', label: 'Suppliers', icon: Building2 },
  { to: '/complaints', label: 'Complaints', icon: AlertTriangle },
  { to: '/analytics', label: 'Analytics', icon: BarChart3 },
  { to: '/settings/organization', label: 'Organization', icon: Building },
  { to: '/settings/api-keys', label: 'API Keys', icon: KeyRound },
  { to: '/ai-providers', label: 'AI Providers', icon: Bot },
  { to: '/automations', label: 'Automations', icon: Workflow },
  { to: '/webhooks', label: 'Webhooks', icon: Zap },
  { to: '/agents', label: 'Agents', icon: Briefcase },
  { to: '/vendors', label: 'Vendors', icon: Truck },
  { to: '/settings/tracking', label: 'Tracking settings', icon: Settings },
]

export default function AppLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/login')
  }

  return (
    <div className="flex min-h-screen bg-canvas">
      <aside className="w-60 shrink-0 bg-navy text-white flex flex-col">
        <div className="px-5 py-5 border-b border-white/10">
          <p className="text-lg font-semibold tracking-tight">QUDRIX</p>
          <p className="text-xs text-white/50">Travel CRM</p>
        </div>
        <nav className="flex-1 py-3">
          {NAV_ITEMS.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-5 py-2.5 text-sm border-l-2 transition-colors ${
                  isActive
                    ? 'border-teal bg-white/5 text-white font-medium'
                    : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white'
                }`
              }
            >
              <Icon size={17} strokeWidth={1.75} />
              {label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex-1 flex flex-col min-w-0">
        <header className="h-14 shrink-0 bg-surface border-b border-line flex items-center justify-between px-6">
          <div />
          <div className="flex items-center gap-3">
            <span className="text-sm text-ink-soft">{user?.name}</span>
            <button
              onClick={handleLogout}
              className="flex items-center gap-1.5 text-sm text-ink-soft hover:text-ink"
            >
              <LogOut size={15} />
              Sign out
            </button>
          </div>
        </header>

        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
