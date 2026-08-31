import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  createUpsellRule,
  deleteUpsellRule,
  getUpsellEffectiveness,
  listUpsellRules,
  updateUpsellRule,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { UpsellEffectivenessRow, UpsellRecommendType, UpsellRule, UpsellTriggerType } from '../types'
import { formatCurrency, getErrorMessage, titleCase } from '../utils/format'

const emptyForm = {
  name: '',
  trigger_type: 'any' as UpsellTriggerType,
  recommend_type: 'hotel' as UpsellRecommendType,
  description: '',
  suggested_price: '',
  currency: 'USD',
  priority: '0',
  requires_availability_check: true,
}

export default function UpsellPage() {
  const [tab, setTab] = useState<'rules' | 'effectiveness'>('rules')

  return (
    <div className="page">
      <div className="page-header">
        <h1>Upsell &amp; Cross-sell</h1>
      </div>
      <div className="view-toggle">
        <button type="button" className={tab === 'rules' ? 'active' : ''} onClick={() => setTab('rules')}>
          Rules
        </button>
        <button type="button" className={tab === 'effectiveness' ? 'active' : ''} onClick={() => setTab('effectiveness')}>
          Effectiveness
        </button>
      </div>
      {tab === 'rules' ? <RulesTab /> : <EffectivenessTab />}
    </div>
  )
}

function RulesTab() {
  const [rules, setRules] = useState<UpsellRule[]>([])
  const [triggerTypes, setTriggerTypes] = useState<UpsellTriggerType[]>([])
  const [recommendTypes, setRecommendTypes] = useState<UpsellRecommendType[]>([])
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
      const res = await listUpsellRules()
      setRules(res.data.data ?? [])
      setTriggerTypes(res.data.trigger_types ?? [])
      setRecommendTypes(res.data.recommend_types ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load upsell rules.'))
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
      await createUpsellRule({
        name: form.name,
        trigger_type: form.trigger_type,
        recommend_type: form.recommend_type,
        description: form.description || undefined,
        suggested_price: form.suggested_price ? Number(form.suggested_price) : undefined,
        currency: form.currency || undefined,
        priority: form.priority ? Number(form.priority) : undefined,
        requires_availability_check: form.requires_availability_check,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this rule.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(rule: UpsellRule) {
    setBusyId(rule.id)
    setActionError(null)
    try {
      await updateUpsellRule(rule.id, { is_active: !rule.is_active })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to update this rule.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(rule: UpsellRule) {
    if (!window.confirm(`Delete upsell rule "${rule.name}"?`)) return
    setBusyId(rule.id)
    setActionError(null)
    try {
      await deleteUpsellRule(rule.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this rule.'))
    } finally {
      setBusyId(null)
    }
  }

  if (notAvailable) return <NotAvailable label="Upsell rules" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Rule
          </button>
        </div>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : rules.length === 0 ? (
        <EmptyState message="No upsell rules configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Trigger</th>
                <th>Recommends</th>
                <th>Suggested Price</th>
                <th>Availability Check</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rules.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td>
                  <td>{titleCase(r.trigger_type)}</td>
                  <td>{titleCase(r.recommend_type)}</td>
                  <td>{r.suggested_price ? formatCurrency(r.suggested_price) : '—'}</td>
                  <td>{r.requires_availability_check ? 'Yes' : 'No'}</td>
                  <td>
                    <Badge text={r.is_active ? 'Active' : 'Inactive'} tone={r.is_active ? 'success' : 'default'} />
                  </td>
                  <td>
                    <div className="row-actions">
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === r.id}
                        onClick={() => toggleActive(r)}
                      >
                        {r.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === r.id}
                        onClick={() => handleDelete(r)}
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
        <Modal title="New Upsell Rule" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Trigger (when booking has...)</span>
              <select
                value={form.trigger_type}
                onChange={(e) => setForm({ ...form, trigger_type: e.target.value as UpsellTriggerType })}
              >
                {triggerTypes.map((t) => (
                  <option key={t} value={t}>
                    {titleCase(t)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Recommend</span>
              <select
                value={form.recommend_type}
                onChange={(e) => setForm({ ...form, recommend_type: e.target.value as UpsellRecommendType })}
              >
                {recommendTypes.map((t) => (
                  <option key={t} value={t}>
                    {titleCase(t)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Description</span>
              <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            </label>
            <label className="field">
              <span>Suggested Price</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={form.suggested_price}
                onChange={(e) => setForm({ ...form, suggested_price: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                maxLength={3}
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value.toUpperCase() })}
              />
            </label>
            <label className="field">
              <span>Priority</span>
              <input
                type="number"
                min={0}
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
              />
            </label>
            <label className="field field-inline">
              <input
                type="checkbox"
                checked={form.requires_availability_check}
                onChange={(e) => setForm({ ...form, requires_availability_check: e.target.checked })}
              />
              <span>Require a real availability check before recommending this</span>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Rule'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}

function EffectivenessTab() {
  const [rows, setRows] = useState<UpsellEffectivenessRow[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getUpsellEffectiveness()
      .then((res) => setRows(res.data.data ?? []))
      .catch((err) => setError(getErrorMessage(err, 'Unable to load upsell effectiveness.')))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (rows.length === 0) return <EmptyState message="No upsell recommendations recorded yet." />

  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>Recommend Type</th>
            <th>Shown</th>
            <th>Accepted</th>
            <th>Acceptance Rate</th>
            <th>Revenue From Upsells</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.recommend_type}>
              <td>{titleCase(row.recommend_type)}</td>
              <td>{row.shown}</td>
              <td>{row.accepted}</td>
              <td>{row.acceptance_rate_percent !== null ? `${row.acceptance_rate_percent}%` : '—'}</td>
              <td>{formatCurrency(row.revenue_from_upsells)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
