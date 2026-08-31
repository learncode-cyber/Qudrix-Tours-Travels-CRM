import type { ReactNode } from 'react'

export function StatCard({
  label,
  value,
  hint,
}: {
  label: string
  value: ReactNode
  hint?: string
}) {
  return (
    <div className="stat-card">
      <div className="stat-label">{label}</div>
      <div className="stat-value">{value}</div>
      {hint ? <div className="stat-hint">{hint}</div> : null}
    </div>
  )
}

export function Loading({ label = 'Loading...' }: { label?: string }) {
  return <div className="state-block loading">{label}</div>
}

export function ErrorBanner({ message }: { message: string }) {
  return <div className="state-block error">{message}</div>
}

export function EmptyState({ message }: { message: string }) {
  return <div className="state-block empty">{message}</div>
}

export function NotAvailable({ label }: { label: string }) {
  return <div className="not-available">{label} not available yet</div>
}

export function Modal({
  title,
  onClose,
  children,
}: {
  title: string
  onClose: () => void
  children: ReactNode
}) {
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3>{title}</h3>
          <button type="button" className="btn btn-ghost btn-icon" onClick={onClose}>
            ×
          </button>
        </div>
        <div className="modal-body">{children}</div>
      </div>
    </div>
  )
}

export function Badge({ text, tone = 'default' }: { text: string; tone?: string }) {
  return <span className={`badge badge-${tone}`}>{text}</span>
}
