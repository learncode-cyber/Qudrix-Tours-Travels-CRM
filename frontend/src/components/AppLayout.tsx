import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

const navItems = [
  { to: '/dashboard', label: 'Dashboard', icon: '▦' },
  { to: '/customers', label: 'Customers', icon: '☺' },
  { to: '/leads', label: 'Leads', icon: '⚑' },
  { to: '/deals', label: 'Deals', icon: '◆' },
  { to: '/tasks', label: 'Tasks', icon: '✓' },
  { to: '/packages', label: 'Packages', icon: '◈' },
  { to: '/bookings', label: 'Bookings', icon: '✈' },
  { to: '/flights', label: 'Flights', icon: '➤' },
  { to: '/hotels', label: 'Hotels', icon: '⌂' },
  { to: '/visas', label: 'Visas', icon: '⚕' },
  { to: '/hajj-umrah', label: 'Hajj & Umrah', icon: '☪' },
  { to: '/student-visa', label: 'Student Visa', icon: '🎓' },
  { to: '/package-builder', label: 'Package Builder', icon: '⚙' },
  { to: '/pricing-rules', label: 'Pricing Rules', icon: '%' },
  { to: '/sales', label: 'Sales', icon: '$' },
  { to: '/quotations', label: 'Quotations', icon: '≡' },
  { to: '/proposals', label: 'Proposals', icon: '▤' },
  { to: '/invoices', label: 'Invoices', icon: '¤' },
  { to: '/conversations', label: 'Conversations', icon: '✉' },
  { to: '/notifications', label: 'Notifications', icon: '🔔' },
  { to: '/profile', label: 'Profile', icon: '👤' },
  { to: '/integrations', label: 'Integrations', icon: '🔌' },
  { to: '/ai-providers', label: 'AI Providers', icon: '✨' },
  { to: '/ai-package-assistant', label: 'AI Package Assistant', icon: '🤖' },
  { to: '/sales-strategies', label: 'Sales Strategies', icon: '🎯' },
]

export default function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-brand">
          <span className="brand-mark">Q</span>
          <span className="brand-text">Qudrix CRM</span>
        </div>
        <nav className="sidebar-nav">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) => 'nav-link' + (isActive ? ' active' : '')}
            >
              <span className="nav-icon">{item.icon}</span>
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="main-column">
        <header className="topbar">
          <div className="topbar-title">Qudrix Tours &amp; Travels</div>
          <div className="topbar-user">
            <span className="user-name">{user?.name ?? 'Signed in'}</span>
            <button type="button" className="btn btn-ghost" onClick={logout}>
              Log out
            </button>
          </div>
        </header>
        <main className="page-content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
