import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { createLead, getFullPipeline, updateLeadStatus } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Lead, PipelineColumn } from '../types'
import { formatCurrency, getErrorMessage, titleCase } from '../utils/format'

const DEFAULT_STAGE_ORDER = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']

const emptyForm = {
  name: '',
  email: '',
  phone: '',
  source: '',
  status: 'new',
  priority: '',
  estimated_value: '',
}

function isPipelineColumn(item: unknown): item is PipelineColumn {
  return !!item && typeof item === 'object' && 'status' in item
}

// The real API response (PipelineController::getFullPipeline) is an
// object keyed by status — {new: {count, total_value, leads}, ...} —
// not an array. This normalizes that (and a couple of defensive
// fallback shapes) into the array of columns the UI renders.
function normalizeToColumns(raw: unknown): PipelineColumn[] {
  if (Array.isArray(raw)) {
    if (raw.length === 0) return []
    if (isPipelineColumn(raw[0])) return raw as PipelineColumn[]
    const grouped = new Map<string, Lead[]>()
    for (const lead of raw as Lead[]) {
      const arr = grouped.get(lead.status) ?? []
      arr.push(lead)
      grouped.set(lead.status, arr)
    }
    return Array.from(grouped.entries()).map(([status, leads]) => ({ status, leads }))
  }
  if (raw && typeof raw === 'object') {
    return Object.entries(raw as Record<string, { leads?: Lead[] }>).map(([status, value]) => ({
      status,
      leads: value?.leads ?? [],
    }))
  }
  return []
}

export default function LeadsPage() {
  const [columns, setColumns] = useState<PipelineColumn[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [view, setView] = useState<'kanban' | 'list'>('kanban')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [movingId, setMovingId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const res = await getFullPipeline()
      setColumns(normalizeToColumns(res.data.data))
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load leads pipeline.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const stageOrder = useMemo(() => {
    const present = columns.map((c) => c.status)
    const known = DEFAULT_STAGE_ORDER.filter((s) => present.includes(s))
    const extra = present.filter((s) => !DEFAULT_STAGE_ORDER.includes(s))
    return [...known, ...extra]
  }, [columns])

  const allLeads = useMemo(() => columns.flatMap((c) => c.leads ?? []), [columns])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createLead({
        ...form,
        estimated_value: form.estimated_value ? Number(form.estimated_value) : undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create lead.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleMove(lead: Lead, nextStatus: string) {
    setMovingId(lead.id)
    try {
      await updateLeadStatus(lead.id, nextStatus)
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to update lead status.'))
    } finally {
      setMovingId(null)
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Leads</h1>
        <div className="header-actions">
          <div className="view-toggle">
            <button
              type="button"
              className={view === 'kanban' ? 'active' : ''}
              onClick={() => setView('kanban')}
            >
              Kanban
            </button>
            <button
              type="button"
              className={view === 'list' ? 'active' : ''}
              onClick={() => setView('list')}
            >
              List
            </button>
          </div>
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Lead
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : allLeads.length === 0 ? (
        <EmptyState message="No leads yet." />
      ) : view === 'kanban' ? (
        <div className="kanban-board">
          {stageOrder.map((stage) => {
            const col = columns.find((c) => c.status === stage)
            const leads = col?.leads ?? []
            return (
              <div className="kanban-column" key={stage}>
                <div className="kanban-column-header">
                  <span>{titleCase(stage)}</span>
                  <span className="kanban-count">{leads.length}</span>
                </div>
                <div className="kanban-cards">
                  {leads.map((lead) => (
                    <div className="kanban-card" key={lead.id}>
                      <div className="kanban-card-title">{lead.name}</div>
                      <div className="kanban-card-meta">
                        {lead.email ?? lead.phone ?? 'No contact info'}
                      </div>
                      {lead.estimated_value ? (
                        <div className="kanban-card-value">
                          {formatCurrency(lead.estimated_value)}
                        </div>
                      ) : null}
                      <select
                        className="stage-select"
                        value={lead.status}
                        disabled={movingId === lead.id}
                        onChange={(e) => handleMove(lead, e.target.value)}
                      >
                        {stageOrder.map((s) => (
                          <option key={s} value={s}>
                            {titleCase(s)}
                          </option>
                        ))}
                      </select>
                    </div>
                  ))}
                </div>
              </div>
            )
          })}
        </div>
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Source</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Est. Value</th>
                <th>Move</th>
              </tr>
            </thead>
            <tbody>
              {allLeads.map((lead) => (
                <tr key={lead.id}>
                  <td>{lead.name}</td>
                  <td>{lead.email ?? lead.phone ?? '—'}</td>
                  <td>{lead.source ?? '—'}</td>
                  <td>{titleCase(lead.status)}</td>
                  <td>{lead.priority ?? '—'}</td>
                  <td>{formatCurrency(lead.estimated_value)}</td>
                  <td>
                    <select
                      className="stage-select"
                      value={lead.status}
                      disabled={movingId === lead.id}
                      onChange={(e) => handleMove(lead, e.target.value)}
                    >
                      {stageOrder.map((s) => (
                        <option key={s} value={s}>
                          {titleCase(s)}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Lead" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Phone</span>
              <input
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Source</span>
              <input
                value={form.source}
                onChange={(e) => setForm({ ...form, source: e.target.value })}
                placeholder="website / referral / ad"
              />
            </label>
            <label className="field">
              <span>Status</span>
              <select
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
              >
                {DEFAULT_STAGE_ORDER.map((s) => (
                  <option key={s} value={s}>
                    {titleCase(s)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Priority</span>
              <input
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
                placeholder="low / medium / high"
              />
            </label>
            <label className="field">
              <span>Estimated Value</span>
              <input
                type="number"
                value={form.estimated_value}
                onChange={(e) => setForm({ ...form, estimated_value: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Lead'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
