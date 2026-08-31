import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { createDeal, getDealsPipeline, listDeals, updateDeal } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Deal, DealPipelineColumn } from '../types'
import { formatCurrency, getErrorMessage, titleCase } from '../utils/format'

const DEFAULT_STAGE_ORDER = ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost']

const emptyForm = {
  title: '',
  amount: '',
  stage: 'new',
  probability: '',
  expected_close_date: '',
  notes: '',
}

function groupByStage(deals: Deal[]): DealPipelineColumn[] {
  const grouped = new Map<string, Deal[]>()
  for (const deal of deals) {
    const arr = grouped.get(deal.stage) ?? []
    arr.push(deal)
    grouped.set(deal.stage, arr)
  }
  return Array.from(grouped.entries()).map(([stage, dealsInStage]) => ({
    stage,
    deals: dealsInStage,
  }))
}

// The real API response (DealController::pipeline) is an object keyed
// by stage — {new: {count, total_value, deals}, ...} — not an array.
function normalizePipelineColumns(raw: unknown): DealPipelineColumn[] {
  if (Array.isArray(raw)) return raw as DealPipelineColumn[]
  if (raw && typeof raw === 'object') {
    return Object.entries(raw as Record<string, { deals?: Deal[] }>).map(([stage, value]) => ({
      stage,
      deals: value?.deals ?? [],
    }))
  }
  return []
}

export default function DealsPage() {
  const [columns, setColumns] = useState<DealPipelineColumn[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [view, setView] = useState<'kanban' | 'list'>('kanban')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [movingId, setMovingId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      try {
        const res = await getDealsPipeline()
        setColumns(normalizePipelineColumns(res.data.data))
      } catch {
        const res = await listDeals()
        setColumns(groupByStage(res.data.data ?? []))
      }
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load deals.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const stageOrder = useMemo(() => {
    const present = columns.map((c) => c.stage)
    const known = DEFAULT_STAGE_ORDER.filter((s) => present.includes(s))
    const extra = present.filter((s) => !DEFAULT_STAGE_ORDER.includes(s))
    return [...known, ...extra]
  }, [columns])

  const allDeals = useMemo(() => columns.flatMap((c) => c.deals ?? []), [columns])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createDeal({
        ...form,
        amount: form.amount ? Number(form.amount) : undefined,
        probability: form.probability ? Number(form.probability) : undefined,
        expected_close_date: form.expected_close_date || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create deal.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleMove(deal: Deal, nextStage: string) {
    setMovingId(deal.id)
    try {
      await updateDeal(deal.id, { stage: nextStage })
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to update deal stage.'))
    } finally {
      setMovingId(null)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Deals</h1>
        </div>
        <div className="not-available">Deals feature not available yet from the backend.</div>
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Deals</h1>
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
            + New Deal
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : allDeals.length === 0 ? (
        <EmptyState message="No deals yet." />
      ) : view === 'kanban' ? (
        <div className="kanban-board">
          {stageOrder.map((stage) => {
            const col = columns.find((c) => c.stage === stage)
            const deals = col?.deals ?? []
            return (
              <div className="kanban-column" key={stage}>
                <div className="kanban-column-header">
                  <span>{titleCase(stage)}</span>
                  <span className="kanban-count">{deals.length}</span>
                </div>
                <div className="kanban-cards">
                  {deals.map((deal) => (
                    <div className="kanban-card" key={deal.id}>
                      <div className="kanban-card-title">{deal.title}</div>
                      {deal.amount ? (
                        <div className="kanban-card-value">{formatCurrency(deal.amount)}</div>
                      ) : null}
                      {deal.probability !== null && deal.probability !== undefined ? (
                        <div className="kanban-card-meta">Probability: {deal.probability}%</div>
                      ) : null}
                      <select
                        className="stage-select"
                        value={deal.stage}
                        disabled={movingId === deal.id}
                        onChange={(e) => handleMove(deal, e.target.value)}
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
                <th>Title</th>
                <th>Amount</th>
                <th>Stage</th>
                <th>Probability</th>
                <th>Close Date</th>
                <th>Move</th>
              </tr>
            </thead>
            <tbody>
              {allDeals.map((deal) => (
                <tr key={deal.id}>
                  <td>{deal.title}</td>
                  <td>{formatCurrency(deal.amount)}</td>
                  <td>{titleCase(deal.stage)}</td>
                  <td>{deal.probability ?? '—'}</td>
                  <td>{deal.expected_close_date ?? '—'}</td>
                  <td>
                    <select
                      className="stage-select"
                      value={deal.stage}
                      disabled={movingId === deal.id}
                      onChange={(e) => handleMove(deal, e.target.value)}
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
        <Modal title="New Deal" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Title</span>
              <input
                required
                value={form.title}
                onChange={(e) => setForm({ ...form, title: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Amount</span>
              <input
                type="number"
                value={form.amount}
                onChange={(e) => setForm({ ...form, amount: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Stage</span>
              <select
                value={form.stage}
                onChange={(e) => setForm({ ...form, stage: e.target.value })}
              >
                {DEFAULT_STAGE_ORDER.map((s) => (
                  <option key={s} value={s}>
                    {titleCase(s)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Probability (%)</span>
              <input
                type="number"
                value={form.probability}
                onChange={(e) => setForm({ ...form, probability: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Expected Close Date</span>
              <input
                type="date"
                value={form.expected_close_date}
                onChange={(e) => setForm({ ...form, expected_close_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Notes</span>
              <textarea
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Deal'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
