import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { createAutomation, getAutomationDashboardSummary, listAutomations } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable, StatCard } from '../components/ui'
import type { Automation, AutomationDashboardSummary, AutomationTriggerType } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyForm = {
  name: '',
  trigger_type: 'customer_added' as AutomationTriggerType,
  status: 'draft',
}

export default function AutomationsPage() {
  const [automations, setAutomations] = useState<Automation[]>([])
  const [summary, setSummary] = useState<AutomationDashboardSummary | null>(null)
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
      const [autoRes, summaryRes] = await Promise.all([listAutomations(), getAutomationDashboardSummary()])
      setAutomations(autoRes.data.data ?? [])
      setSummary(summaryRes.data.data)
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load automations.'))
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
      await createAutomation({ name: form.name, trigger_type: form.trigger_type, status: form.status })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this automation.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Automations" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Automations</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Automation
          </button>
        </div>
      </div>

      {summary ? (
        <div className="stat-grid">
          <StatCard label="Total Automations" value={summary.total_automations} />
          <StatCard label="Active" value={summary.active_automations} />
          <StatCard label="Total Runs" value={summary.total_runs} />
        </div>
      ) : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : automations.length === 0 ? (
        <EmptyState message="No automations configured yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Trigger</th>
                <th>Status</th>
                <th>Runs</th>
                <th>Last Run</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {automations.map((a) => (
                <tr key={a.id}>
                  <td>
                    <Link to={`/automations/${a.id}`}>{a.name}</Link>
                  </td>
                  <td>{titleCase(a.trigger_type)}</td>
                  <td>
                    <Badge text={titleCase(a.status)} tone={statusTone(a.status)} />
                  </td>
                  <td>{a.run_count}</td>
                  <td>{formatDate(a.last_run_at)}</td>
                  <td>
                    <Link to={`/automations/${a.id}`} className="btn btn-ghost btn-sm">
                      Manage
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Automation" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Trigger</span>
              <select
                value={form.trigger_type}
                onChange={(e) => setForm({ ...form, trigger_type: e.target.value as AutomationTriggerType })}
              >
                <option value="booking_created">Booking Created</option>
                <option value="customer_added">Customer Added</option>
                <option value="invoice_created">Invoice Created</option>
                <option value="payment_received">Payment Received</option>
                <option value="webhook">Webhook</option>
              </select>
            </label>
            <label className="field">
              <span>Status</span>
              <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="archived">Archived</option>
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Automation'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
