import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { createSupportTicket, listSupportTickets } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { SupportTicket, SupportTicketPriority } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyForm = {
  subject: '',
  description: '',
  category: '',
  priority: 'normal' as SupportTicketPriority,
  customer_id: '',
}

export default function SupportTicketsPage() {
  const [tickets, setTickets] = useState<SupportTicket[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listSupportTickets()
      setTickets(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load support tickets.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createSupportTicket({
        subject: form.subject,
        description: form.description,
        category: form.category || undefined,
        priority: form.priority,
        customer_id: form.customer_id ? Number(form.customer_id) : undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this ticket.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Support tickets" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Support Tickets</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Ticket
          </button>
        </div>
      </div>
      <p className="field-hint">
        AI triage is a suggestion an agent reviews and applies — only a triage classified
        "critical" escalates automatically, and it only adds human attention, never resolves or
        replies on its own.
      </p>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : tickets.length === 0 ? (
        <EmptyState message="No support tickets yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Escalated</th>
                <th>Opened</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {tickets.map((t) => (
                <tr key={t.id}>
                  <td>
                    <Link to={`/support-tickets/${t.id}`}>{t.subject}</Link>
                  </td>
                  <td>{titleCase(t.category)}</td>
                  <td>{titleCase(t.priority)}</td>
                  <td>
                    <Badge text={titleCase(t.status)} tone={statusTone(t.status)} />
                  </td>
                  <td>{t.escalated ? <Badge text="Escalated" tone="danger" /> : '—'}</td>
                  <td>{formatDate(t.created_at)}</td>
                  <td>
                    <Link to={`/support-tickets/${t.id}`} className="btn btn-ghost btn-sm">
                      Open
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Support Ticket" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Subject</span>
              <input required value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                required
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Category</span>
              <input value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} />
            </label>
            <label className="field">
              <span>Priority</span>
              <select
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value as SupportTicketPriority })}
              >
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </label>
            <label className="field">
              <span>Customer ID (optional)</span>
              <input value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Ticket'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
