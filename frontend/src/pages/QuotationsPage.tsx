import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  approveQuotation,
  createQuotation,
  downloadQuotationPdf,
  generateInvoiceFromQuotation,
  listLeads,
  listQuotations,
  sendQuotation,
  submitQuotationForApproval,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Lead, Quotation, QuotationItem } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const STATUS_FILTERS = [
  'all',
  'draft',
  'pending_approval',
  'approved',
  'sent',
  'accepted',
  'rejected',
]

const emptyItem = (): QuotationItem => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount: 0,
})

const emptyForm = {
  lead_id: '',
  subject: '',
  valid_until: '',
  currency: 'USD',
  items: [emptyItem()],
}

function lineTotal(item: QuotationItem): number {
  const qty = Number(item.quantity) || 0
  const price = Number(item.unit_price) || 0
  const tax = Number(item.tax_rate) || 0
  const discount = Number(item.discount) || 0
  const base = qty * price
  const afterDiscount = base - discount
  return afterDiscount + afterDiscount * (tax / 100)
}

export default function QuotationsPage() {
  const [quotations, setQuotations] = useState<Quotation[]>([])
  const [leads, setLeads] = useState<Lead[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [statusFilter, setStatusFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listQuotations()
      setQuotations(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load quotations.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    listLeads()
      .then((res) => setLeads(res.data.data ?? []))
      .catch(() => setLeads([]))
  }, [])

  const filtered = useMemo(() => {
    if (statusFilter === 'all') return quotations
    return quotations.filter((q) => q.status === statusFilter)
  }, [quotations, statusFilter])

  const runningTotal = useMemo(
    () => form.items.reduce((sum, item) => sum + lineTotal(item), 0),
    [form.items],
  )

  function updateItem(index: number, patch: Partial<QuotationItem>) {
    setForm((f) => ({
      ...f,
      items: f.items.map((it, i) => (i === index ? { ...it, ...patch } : it)),
    }))
  }

  function addItem() {
    setForm((f) => ({ ...f, items: [...f.items, emptyItem()] }))
  }

  function removeItem(index: number) {
    setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== index) }))
  }

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createQuotation({
        lead_id: form.lead_id ? Number(form.lead_id) : undefined,
        subject: form.subject,
        valid_until: form.valid_until || undefined,
        currency: form.currency,
        items: form.items.map((it) => ({
          description: it.description,
          quantity: Number(it.quantity) || 0,
          unit_price: Number(it.unit_price) || 0,
          tax_rate: Number(it.tax_rate) || 0,
          discount: Number(it.discount) || 0,
        })),
      } as Partial<Quotation>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create quotation.'))
    } finally {
      setSaving(false)
    }
  }

  async function runAction(id: number, action: () => Promise<unknown>) {
    setBusyId(id)
    setActionError(null)
    try {
      await action()
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Action failed.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDownload(q: Quotation) {
    setBusyId(q.id)
    setActionError(null)
    try {
      await downloadQuotationPdf(q.id, `${q.quotation_number ?? q.id}.pdf`)
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to download PDF.'))
    } finally {
      setBusyId(null)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Quotations</h1>
        </div>
        <NotAvailable label="Quotations" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Quotations</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Quotation
          </button>
        </div>
      </div>

      <div className="view-toggle">
        {STATUS_FILTERS.map((s) => (
          <button
            key={s}
            type="button"
            className={statusFilter === s ? 'active' : ''}
            onClick={() => setStatusFilter(s)}
          >
            {titleCase(s)}
          </button>
        ))}
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState message="No quotations found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Subject</th>
                <th>Lead / Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Valid Until</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((q) => (
                <tr key={q.id}>
                  <td>
                    <Link to={`/quotations/${q.id}`}>{q.quotation_number ?? q.id}</Link>
                  </td>
                  <td>{q.subject ?? '—'}</td>
                  <td>
                    {q.customer?.name ??
                      q.lead?.name ??
                      (q.customer_id ? `Customer #${q.customer_id}` : q.lead_id ? `Lead #${q.lead_id}` : '—')}
                  </td>
                  <td>
                    <Badge text={titleCase(q.status)} tone={statusTone(q.status)} />
                  </td>
                  <td>{formatCurrency(q.total_amount)}</td>
                  <td>{formatDate(q.valid_until)}</td>
                  <td>
                    <div className="row-actions">
                      {q.status === 'draft' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === q.id}
                          onClick={() =>
                            runAction(q.id, () => submitQuotationForApproval(q.id))
                          }
                        >
                          Submit for Approval
                        </button>
                      ) : null}
                      {q.status === 'pending_approval' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === q.id}
                          onClick={() => runAction(q.id, () => approveQuotation(q.id))}
                        >
                          Approve
                        </button>
                      ) : null}
                      {q.status === 'approved' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === q.id}
                          onClick={() => runAction(q.id, () => sendQuotation(q.id))}
                        >
                          Send
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === q.id}
                        onClick={() => handleDownload(q)}
                      >
                        PDF
                      </button>
                      {q.status === 'accepted' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === q.id}
                          onClick={() =>
                            runAction(q.id, () => generateInvoiceFromQuotation(q.id))
                          }
                        >
                          Generate Invoice
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Quotation" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Lead</span>
              <select
                required
                value={form.lead_id}
                onChange={(e) => setForm({ ...form, lead_id: e.target.value })}
              >
                <option value="">Select a lead...</option>
                {leads.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Subject</span>
              <input
                required
                value={form.subject}
                onChange={(e) => setForm({ ...form, subject: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Valid Until</span>
              <input
                type="date"
                value={form.valid_until}
                onChange={(e) => setForm({ ...form, valid_until: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value })}
                placeholder="USD"
              />
            </label>

            <div className="field">
              <span>Items</span>
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Description</th>
                      <th>Qty</th>
                      <th>Unit Price</th>
                      <th>Tax %</th>
                      <th>Discount</th>
                      <th>Line Total</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {form.items.map((item, i) => (
                      <tr key={i}>
                        <td>
                          <input
                            required
                            value={item.description}
                            onChange={(e) => updateItem(i, { description: e.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            min={0}
                            style={{ width: 70 }}
                            value={item.quantity}
                            onChange={(e) => updateItem(i, { quantity: e.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            min={0}
                            style={{ width: 90 }}
                            value={item.unit_price}
                            onChange={(e) => updateItem(i, { unit_price: e.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            min={0}
                            style={{ width: 70 }}
                            value={item.tax_rate ?? 0}
                            onChange={(e) => updateItem(i, { tax_rate: e.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            min={0}
                            style={{ width: 80 }}
                            value={item.discount ?? 0}
                            onChange={(e) => updateItem(i, { discount: e.target.value })}
                          />
                        </td>
                        <td>{formatCurrency(lineTotal(item))}</td>
                        <td>
                          <button
                            type="button"
                            className="btn btn-ghost btn-icon"
                            disabled={form.items.length === 1}
                            onClick={() => removeItem(i)}
                          >
                            ×
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <button type="button" className="btn btn-ghost btn-sm" onClick={addItem}>
                + Add Item
              </button>
            </div>

            <div className="detail-grid">
              <div>
                <span className="detail-label">Running Total</span>
                <span>{formatCurrency(runningTotal)}</span>
              </div>
            </div>

            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Quotation'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
