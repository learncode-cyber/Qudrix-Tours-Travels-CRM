import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  createPricingRule,
  deletePricingRule,
  listPricingRules,
  previewPricing,
  updatePricingRule,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { PricingAppliedRule, PricingRule } from '../types'
import { formatCurrency, getErrorMessage, titleCase } from '../utils/format'

const FACTORS = ['season', 'demand', 'group_size', 'customer_segment', 'booking_timing']

const emptyForm = {
  name: '',
  factor: 'demand',
  season_start: '',
  season_end: '',
  min_group_size: '',
  max_group_size: '',
  booking_days_before_travel_min: '',
  booking_days_before_travel_max: '',
  adjustment_type: 'percentage',
  adjustment_value: '',
  priority: '0',
}

const emptyPreviewForm = {
  base_cost: '',
  travel_date: '',
  group_size: '',
  booking_days_before_travel: '',
}

export default function PricingRulesPage() {
  const [rules, setRules] = useState<PricingRule[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const [showPreview, setShowPreview] = useState(false)
  const [previewForm, setPreviewForm] = useState(emptyPreviewForm)
  const [previewBusy, setPreviewBusy] = useState(false)
  const [previewError, setPreviewError] = useState<string | null>(null)
  const [previewResult, setPreviewResult] = useState<{ base_cost: number; applied_rules: PricingAppliedRule[]; final_price: number } | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listPricingRules()
      setRules(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load pricing rules.'))
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
      await createPricingRule({
        name: form.name,
        factor: form.factor,
        season_start: form.season_start || undefined,
        season_end: form.season_end || undefined,
        min_group_size: form.min_group_size ? Number(form.min_group_size) : undefined,
        max_group_size: form.max_group_size ? Number(form.max_group_size) : undefined,
        booking_days_before_travel_min: form.booking_days_before_travel_min
          ? Number(form.booking_days_before_travel_min)
          : undefined,
        booking_days_before_travel_max: form.booking_days_before_travel_max
          ? Number(form.booking_days_before_travel_max)
          : undefined,
        adjustment_type: form.adjustment_type,
        adjustment_value: Number(form.adjustment_value),
        priority: form.priority ? Number(form.priority) : undefined,
      } as Partial<PricingRule>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this pricing rule.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(rule: PricingRule) {
    setBusyId(rule.id)
    setActionError(null)
    try {
      await updatePricingRule(rule.id, { is_active: !rule.is_active })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to update this rule.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(rule: PricingRule) {
    if (!window.confirm(`Delete pricing rule "${rule.name}"?`)) return
    setBusyId(rule.id)
    setActionError(null)
    try {
      await deletePricingRule(rule.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this rule.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handlePreview(e: FormEvent) {
    e.preventDefault()
    setPreviewBusy(true)
    setPreviewError(null)
    setPreviewResult(null)
    try {
      const res = await previewPricing({
        base_cost: Number(previewForm.base_cost),
        travel_date: previewForm.travel_date || undefined,
        group_size: previewForm.group_size ? Number(previewForm.group_size) : undefined,
        booking_days_before_travel: previewForm.booking_days_before_travel
          ? Number(previewForm.booking_days_before_travel)
          : undefined,
      })
      setPreviewResult(res.data.data)
    } catch (err) {
      setPreviewError(getErrorMessage(err, 'Unable to run this preview.'))
    } finally {
      setPreviewBusy(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Pricing rules" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Pricing Rules</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-ghost" onClick={() => setShowPreview(true)}>
            Preview Calculation
          </button>
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
        <EmptyState message="No pricing rules configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Factor</th>
                <th>Adjustment</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rules.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td>
                  <td>{titleCase(r.factor)}</td>
                  <td>
                    {r.adjustment_type === 'percentage'
                      ? `${r.adjustment_value}%`
                      : formatCurrency(r.adjustment_value)}
                  </td>
                  <td>{r.priority ?? 0}</td>
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
        <Modal title="New Pricing Rule" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Factor</span>
              <select value={form.factor} onChange={(e) => setForm({ ...form, factor: e.target.value })}>
                {FACTORS.map((f) => (
                  <option key={f} value={f}>
                    {titleCase(f)}
                  </option>
                ))}
              </select>
            </label>
            {form.factor === 'season' ? (
              <>
                <label className="field">
                  <span>Season Start</span>
                  <input
                    type="date"
                    value={form.season_start}
                    onChange={(e) => setForm({ ...form, season_start: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Season End</span>
                  <input
                    type="date"
                    value={form.season_end}
                    onChange={(e) => setForm({ ...form, season_end: e.target.value })}
                  />
                </label>
              </>
            ) : null}
            {form.factor === 'group_size' ? (
              <>
                <label className="field">
                  <span>Min Group Size</span>
                  <input
                    type="number"
                    min={1}
                    value={form.min_group_size}
                    onChange={(e) => setForm({ ...form, min_group_size: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Max Group Size</span>
                  <input
                    type="number"
                    min={1}
                    value={form.max_group_size}
                    onChange={(e) => setForm({ ...form, max_group_size: e.target.value })}
                  />
                </label>
              </>
            ) : null}
            {form.factor === 'booking_timing' ? (
              <>
                <label className="field">
                  <span>Booking Days Before Travel — Min</span>
                  <input
                    type="number"
                    min={0}
                    value={form.booking_days_before_travel_min}
                    onChange={(e) => setForm({ ...form, booking_days_before_travel_min: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Booking Days Before Travel — Max</span>
                  <input
                    type="number"
                    min={0}
                    value={form.booking_days_before_travel_max}
                    onChange={(e) => setForm({ ...form, booking_days_before_travel_max: e.target.value })}
                  />
                </label>
              </>
            ) : null}
            <p className="field-hint">
              There is no customer-segment picker wired into this frontend yet, so
              customer_segment-factor rules can be created but won't have a segment
              condition attached from here.
            </p>
            <label className="field">
              <span>Adjustment Type</span>
              <select
                value={form.adjustment_type}
                onChange={(e) => setForm({ ...form, adjustment_type: e.target.value })}
              >
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed Amount</option>
              </select>
            </label>
            <label className="field">
              <span>Adjustment Value</span>
              <input
                required
                type="number"
                step="0.01"
                value={form.adjustment_value}
                onChange={(e) => setForm({ ...form, adjustment_value: e.target.value })}
              />
              <p className="field-hint">Negative values apply a discount.</p>
            </label>
            <label className="field">
              <span>Priority</span>
              <input
                type="number"
                min={0}
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
              />
              <p className="field-hint">Lower numbers apply first; adjustments compound in order.</p>
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

      {showPreview ? (
        <Modal title="Preview Pricing Calculation" onClose={() => setShowPreview(false)}>
          <form onSubmit={handlePreview} className="stacked-form">
            {previewError ? <div className="state-block error">{previewError}</div> : null}
            <label className="field">
              <span>Base Cost</span>
              <input
                required
                type="number"
                min={0}
                step="0.01"
                value={previewForm.base_cost}
                onChange={(e) => setPreviewForm({ ...previewForm, base_cost: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Travel Date</span>
              <input
                type="date"
                value={previewForm.travel_date}
                onChange={(e) => setPreviewForm({ ...previewForm, travel_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Group Size</span>
              <input
                type="number"
                min={1}
                value={previewForm.group_size}
                onChange={(e) => setPreviewForm({ ...previewForm, group_size: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Booking Days Before Travel</span>
              <input
                type="number"
                min={0}
                value={previewForm.booking_days_before_travel}
                onChange={(e) =>
                  setPreviewForm({ ...previewForm, booking_days_before_travel: e.target.value })
                }
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowPreview(false)}>
                Close
              </button>
              <button type="submit" className="btn btn-primary" disabled={previewBusy}>
                {previewBusy ? 'Calculating...' : 'Calculate'}
              </button>
            </div>
          </form>

          {previewResult ? (
            <div className="table-wrap">
              <p>
                Base cost {formatCurrency(previewResult.base_cost)} &rarr; final price{' '}
                <strong>{formatCurrency(previewResult.final_price)}</strong>
              </p>
              {previewResult.applied_rules.length === 0 ? (
                <EmptyState message="No rules matched this context." />
              ) : (
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Rule</th>
                      <th>Adjustment</th>
                      <th>Amount</th>
                      <th>Price After</th>
                    </tr>
                  </thead>
                  <tbody>
                    {previewResult.applied_rules.map((r) => (
                      <tr key={r.rule_id}>
                        <td>{r.name}</td>
                        <td>
                          {r.adjustment_type === 'percentage' ? `${r.adjustment_value}%` : formatCurrency(r.adjustment_value)}
                        </td>
                        <td>{formatCurrency(r.amount)}</td>
                        <td>{formatCurrency(r.price_after)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          ) : null}
        </Modal>
      ) : null}
    </div>
  )
}
