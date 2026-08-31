import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { createSalesStrategy, deleteSalesStrategy, listSalesStrategies, updateSalesStrategy } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { SalesStrategy, SalesStrategyKey } from '../types'
import { getErrorMessage, titleCase } from '../utils/format'

const emptyForm = {
  key: 'consultative' as SalesStrategyKey,
  name: '',
  description: '',
  prompt_guidance: '',
  tone: '',
  priority: '0',
}

export default function SalesStrategiesPage() {
  const [strategies, setStrategies] = useState<SalesStrategy[]>([])
  const [availableKeys, setAvailableKeys] = useState<SalesStrategyKey[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listSalesStrategies()
      setStrategies(res.data.data ?? [])
      setAvailableKeys(res.data.available_keys ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load sales strategies.'))
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
      await createSalesStrategy({
        key: form.key,
        name: form.name,
        description: form.description || undefined,
        prompt_guidance: form.prompt_guidance,
        tone: form.tone || undefined,
        priority: form.priority ? Number(form.priority) : undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this strategy.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(s: SalesStrategy) {
    setBusyId(s.id)
    setActionError(null)
    try {
      await updateSalesStrategy(s.id, { is_active: !s.is_active })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to update this strategy.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(s: SalesStrategy) {
    if (!window.confirm(`Delete strategy "${s.name}"?`)) return
    setBusyId(s.id)
    setActionError(null)
    try {
      await deleteSalesStrategy(s.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this strategy.'))
    } finally {
      setBusyId(null)
    }
  }

  if (notAvailable) return <NotAvailable label="Sales strategies" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Sales Strategies</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Strategy
          </button>
        </div>
      </div>
      <p className="field-hint">
        The AI Copilot follows the highest-priority active strategy (preferring one bound to the
        lead's customer segment) when suggesting how a rep should approach a conversation.
      </p>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : strategies.length === 0 ? (
        <EmptyState message="No sales strategies configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Methodology</th>
                <th>Tone</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {strategies.map((s) => (
                <tr key={s.id}>
                  <td>{s.name}</td>
                  <td>{titleCase(s.key)}</td>
                  <td>{s.tone ?? '—'}</td>
                  <td>{s.priority ?? 0}</td>
                  <td>
                    <Badge text={s.is_active ? 'Active' : 'Inactive'} tone={s.is_active ? 'success' : 'default'} />
                  </td>
                  <td>
                    <div className="row-actions">
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === s.id}
                        onClick={() => toggleActive(s)}
                      >
                        {s.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === s.id}
                        onClick={() => handleDelete(s)}
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Sales Strategy" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Methodology</span>
              <select value={form.key} onChange={(e) => setForm({ ...form, key: e.target.value as SalesStrategyKey })}>
                {availableKeys.map((k) => (
                  <option key={k} value={k}>
                    {titleCase(k)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            </label>
            <label className="field">
              <span>Prompt Guidance</span>
              <textarea
                required
                placeholder="Ask open questions before proposing anything. Focus on understanding needs first."
                value={form.prompt_guidance}
                onChange={(e) => setForm({ ...form, prompt_guidance: e.target.value })}
              />
              <p className="field-hint">This text is given directly to the AI Copilot as its methodology guidance.</p>
            </label>
            <label className="field">
              <span>Tone</span>
              <input placeholder="warm, direct, formal..." value={form.tone} onChange={(e) => setForm({ ...form, tone: e.target.value })} />
            </label>
            <label className="field">
              <span>Priority</span>
              <input
                type="number"
                min={0}
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
              />
              <p className="field-hint">Lower numbers are preferred when multiple strategies are active.</p>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Strategy'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
