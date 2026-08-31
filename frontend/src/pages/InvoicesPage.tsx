import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import {
  createInvoice,
  downloadInvoicePdf,
  listCustomers,
  listInvoices,
  listQuotations,
  recordInvoicePayment,
  sendInvoice,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Customer, Invoice, InvoiceItem, Quotation } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyItem = (): InvoiceItem => ({
  description: '',
  quantity: 1,
  unit_price: 0,
  tax_rate: 0,
  discount: 0,
})

const emptyForm = {
  customer_id: '',
  quotation_id: '',
  due_date: '',
  currency: 'USD',
  notes: '',
  items: [emptyItem()],
}

function lineTotal(item: InvoiceItem): number {
  const qty = Number(item.quantity) || 0
  const price = Number(item.unit_price) || 0
  const tax = Number(item.tax_rate) || 0
  const discount = Number(item.discount) || 0
  const base = qty * price
  const afterDiscount = base - discount
  return afterDiscount + afterDiscount * (tax / 100)
}

function isOverdue(inv: Invoice): boolean {
  if (inv.status === 'paid') return false
  if (!inv.due_date) return inv.status === 'overdue'
  return inv.status === 'overdue' || new Date(inv.due_date).getTime() < Date.now()
}

function balanceDue(inv: Invoice): number {
  const total = Number(inv.total_amount) || 0
  const paid = Number(inv.paid_amount) || 0
  return Math.max(0, total - paid)
}

export default function InvoicesPage() {
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [customers, setCustomers] = useState<Customer[]>([])
  const [quotations, setQuotations] = useState<Quotation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [paymentTarget, setPaymentTarget] = useState<Invoice | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('')
  const [paymentSaving, setPaymentSaving] = useState(false)
  const [paymentError, setPaymentError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listInvoices()
      setInvoices(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load invoices.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    listCustomers()
      .then((res) => setCustomers(res.data.data ?? []))
      .catch(() => setCustomers([]))
    listQuotations()
      .then((res) => setQuotations(res.data.data ?? []))
      .catch(() => setQuotations([]))
  }, [])

  const runningTotal = useMemo(
    () => form.items.reduce((sum, item) => sum + lineTotal(item), 0),
    [form.items],
  )

  function updateItem(index: number, patch: Partial<InvoiceItem>) {
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
      await createInvoice({
        customer_id: form.customer_id ? Number(form.customer_id) : undefined,
        quotation_id: form.quotation_id ? Number(form.quotation_id) : undefined,
        currency: form.currency,
        issue_date: new Date().toISOString().slice(0, 10),
        due_date: form.due_date || undefined,
        notes: form.notes || undefined,
        items: form.items.map((it) => ({
          description: it.description,
          quantity: Number(it.quantity) || 0,
          unit_price: Number(it.unit_price) || 0,
          tax_rate: Number(it.tax_rate) || 0,
          discount: Number(it.discount) || 0,
        })),
      } as Partial<Invoice>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create invoice.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleSend(inv: Invoice) {
    setBusyId(inv.id)
    setActionError(null)
    try {
      await sendInvoice(inv.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to send invoice.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDownload(inv: Invoice) {
    setBusyId(inv.id)
    setActionError(null)
    try {
      await downloadInvoicePdf(inv.id, `${inv.invoice_number ?? inv.id}.pdf`)
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to download PDF.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleRecordPayment(e: FormEvent) {
    e.preventDefault()
    if (!paymentTarget) return
    setPaymentSaving(true)
    setPaymentError(null)
    try {
      await recordInvoicePayment(paymentTarget.id, Number(paymentAmount) || 0)
      setPaymentTarget(null)
      setPaymentAmount('')
      await load()
    } catch (err) {
      setPaymentError(getErrorMessage(err, 'Unable to record payment.'))
    } finally {
      setPaymentSaving(false)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Invoices</h1>
        </div>
        <NotAvailable label="Invoices" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Invoices</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Invoice
          </button>
        </div>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : invoices.length === 0 ? (
        <EmptyState message="No invoices yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Balance Due</th>
                <th>Due Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((inv) => (
                <tr key={inv.id} className={isOverdue(inv) ? 'row-overdue' : ''}>
                  <td>{inv.invoice_number ?? inv.id}</td>
                  <td>{inv.customer?.name ?? (inv.customer_id ? `#${inv.customer_id}` : '—')}</td>
                  <td>
                    <Badge text={titleCase(inv.status)} tone={statusTone(inv.status)} />
                  </td>
                  <td>{formatCurrency(inv.total_amount)}</td>
                  <td>{formatCurrency(inv.paid_amount)}</td>
                  <td>{formatCurrency(balanceDue(inv))}</td>
                  <td>{formatDate(inv.due_date)}</td>
                  <td>
                    <div className="row-actions">
                      {inv.status === 'draft' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === inv.id}
                          onClick={() => handleSend(inv)}
                        >
                          Send
                        </button>
                      ) : null}
                      {inv.status !== 'paid' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === inv.id}
                          onClick={() => {
                            setPaymentTarget(inv)
                            setPaymentAmount('')
                            setPaymentError(null)
                          }}
                        >
                          Record Payment
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={busyId === inv.id}
                        onClick={() => handleDownload(inv)}
                      >
                        PDF
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
        <Modal title="New Invoice" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Customer</span>
              <select
                required
                value={form.customer_id}
                onChange={(e) => setForm({ ...form, customer_id: e.target.value })}
              >
                <option value="">Select a customer...</option>
                {customers.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Quotation (optional)</span>
              <select
                value={form.quotation_id}
                onChange={(e) => setForm({ ...form, quotation_id: e.target.value })}
              >
                <option value="">None</option>
                {quotations.map((q) => (
                  <option key={q.id} value={q.id}>
                    {q.quotation_number ?? `#${q.id}`}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Due Date</span>
              <input
                type="date"
                value={form.due_date}
                onChange={(e) => setForm({ ...form, due_date: e.target.value })}
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
            <label className="field">
              <span>Notes</span>
              <textarea
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
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
                {saving ? 'Saving...' : 'Create Invoice'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {paymentTarget ? (
        <Modal title={`Record Payment — ${paymentTarget.invoice_number ?? paymentTarget.id}`} onClose={() => setPaymentTarget(null)}>
          <form onSubmit={handleRecordPayment} className="stacked-form">
            {paymentError ? <div className="state-block error">{paymentError}</div> : null}
            <div className="detail-grid">
              <div>
                <span className="detail-label">Total</span>
                <span>{formatCurrency(paymentTarget.total_amount)}</span>
              </div>
              <div>
                <span className="detail-label">Already Paid</span>
                <span>{formatCurrency(paymentTarget.paid_amount)}</span>
              </div>
              <div>
                <span className="detail-label">Balance Due</span>
                <span>{formatCurrency(balanceDue(paymentTarget))}</span>
              </div>
            </div>
            <label className="field">
              <span>Payment Amount</span>
              <input
                required
                type="number"
                min={0}
                step="0.01"
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setPaymentTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={paymentSaving}>
                {paymentSaving ? 'Saving...' : 'Record Payment'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
