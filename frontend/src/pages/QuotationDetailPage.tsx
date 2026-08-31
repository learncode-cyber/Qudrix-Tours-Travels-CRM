import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  approveQuotation,
  createProposalFromQuotation,
  downloadQuotationPdf,
  generateInvoiceFromQuotation,
  getQuotation,
  sendQuotation,
  submitQuotationForApproval,
} from '../api/endpoints'
import { Badge, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Quotation } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function QuotationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [quotation, setQuotation] = useState<Quotation | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [showProposalForm, setShowProposalForm] = useState(false)
  const [proposalTitle, setProposalTitle] = useState('')
  const [proposalExpiry, setProposalExpiry] = useState('')
  const [proposalSaving, setProposalSaving] = useState(false)
  const [proposalError, setProposalError] = useState<string | null>(null)

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await getQuotation(id)
      setQuotation(res.data.data)
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this quotation.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function runAction(action: () => Promise<unknown>) {
    setBusy(true)
    setActionError(null)
    try {
      await action()
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Action failed.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleDownload() {
    if (!quotation) return
    setBusy(true)
    setActionError(null)
    try {
      await downloadQuotationPdf(quotation.id, `${quotation.quotation_number ?? quotation.id}.pdf`)
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to download PDF.'))
    } finally {
      setBusy(false)
    }
  }

  async function handleCreateProposal(e: FormEvent) {
    e.preventDefault()
    if (!quotation) return
    setProposalSaving(true)
    setProposalError(null)
    try {
      const res = await createProposalFromQuotation({
        quotation_id: quotation.id,
        title: proposalTitle,
        expiry_date: proposalExpiry || undefined,
      })
      setShowProposalForm(false)
      navigate(`/proposals`)
      void res
    } catch (err) {
      setProposalError(getErrorMessage(err, 'Unable to create proposal.'))
    } finally {
      setProposalSaving(false)
    }
  }

  if (loading) return <Loading label="Loading quotation..." />
  if (error) return <ErrorBanner message={error} />
  if (!quotation) return <ErrorBanner message="Quotation not found." />

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/quotations" className="back-link">
            ← Back to quotations
          </Link>
          <h1>{quotation.quotation_number ?? `Quotation #${quotation.id}`}</h1>
        </div>
        <Badge text={titleCase(quotation.status)} tone={statusTone(quotation.status)} />
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      <section className="panel">
        <h2>Overview</h2>
        <div className="detail-grid">
          <div>
            <span className="detail-label">Subject</span>
            <span>{quotation.subject ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Currency</span>
            <span>{quotation.currency ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Valid Until</span>
            <span>{formatDate(quotation.valid_until)}</span>
          </div>
          <div>
            <span className="detail-label">Version</span>
            <span>{quotation.version ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Lead</span>
            <span>{quotation.lead?.name ?? (quotation.lead_id ? `#${quotation.lead_id}` : '—')}</span>
          </div>
          <div>
            <span className="detail-label">Customer</span>
            <span>{quotation.customer?.name ?? (quotation.customer_id ? `#${quotation.customer_id}` : '—')}</span>
          </div>
        </div>
        {quotation.description ? (
          <p className="muted" style={{ marginTop: 12 }}>
            {quotation.description}
          </p>
        ) : null}
      </section>

      <section className="panel">
        <h2>Items</h2>
        {!quotation.items || quotation.items.length === 0 ? (
          <p className="muted">No line items on this quotation.</p>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Description</th>
                  <th>Qty</th>
                  <th>Unit Price</th>
                  <th>Tax %</th>
                  <th>Discount</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                {quotation.items.map((item, i) => (
                  <tr key={item.id ?? i}>
                    <td>{item.description}</td>
                    <td>{item.quantity}</td>
                    <td>{formatCurrency(item.unit_price)}</td>
                    <td>{item.tax_rate ?? 0}%</td>
                    <td>{formatCurrency(item.discount)}</td>
                    <td>{formatCurrency(item.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="detail-grid" style={{ marginTop: 16 }}>
          <div>
            <span className="detail-label">Subtotal</span>
            <span>{formatCurrency(quotation.subtotal)}</span>
          </div>
          <div>
            <span className="detail-label">Tax</span>
            <span>{formatCurrency(quotation.tax_amount)}</span>
          </div>
          <div>
            <span className="detail-label">Discount</span>
            <span>{formatCurrency(quotation.discount_amount)}</span>
          </div>
          <div>
            <span className="detail-label">Total</span>
            <span>{formatCurrency(quotation.total_amount)}</span>
          </div>
        </div>
      </section>

      <section className="panel">
        <h2>Actions</h2>
        <div className="row-actions">
          {quotation.status === 'draft' ? (
            <button
              type="button"
              className="btn btn-ghost"
              disabled={busy}
              onClick={() => runAction(() => submitQuotationForApproval(quotation.id))}
            >
              Submit for Approval
            </button>
          ) : null}
          {quotation.status === 'pending_approval' ? (
            <button
              type="button"
              className="btn btn-ghost"
              disabled={busy}
              onClick={() => runAction(() => approveQuotation(quotation.id))}
            >
              Approve
            </button>
          ) : null}
          {quotation.status === 'approved' ? (
            <button
              type="button"
              className="btn btn-ghost"
              disabled={busy}
              onClick={() => runAction(() => sendQuotation(quotation.id))}
            >
              Send
            </button>
          ) : null}
          <button type="button" className="btn btn-ghost" disabled={busy} onClick={handleDownload}>
            Download PDF
          </button>
          {quotation.status === 'accepted' ? (
            <button
              type="button"
              className="btn btn-primary"
              disabled={busy}
              onClick={() => runAction(() => generateInvoiceFromQuotation(quotation.id))}
            >
              Generate Invoice
            </button>
          ) : null}
          <button
            type="button"
            className="btn btn-primary"
            disabled={busy}
            onClick={() => {
              setProposalTitle(quotation.subject ?? '')
              setShowProposalForm(true)
            }}
          >
            Create Proposal
          </button>
        </div>
      </section>

      {showProposalForm ? (
        <Modal title="Create Proposal" onClose={() => setShowProposalForm(false)}>
          <form onSubmit={handleCreateProposal} className="stacked-form">
            {proposalError ? <div className="state-block error">{proposalError}</div> : null}
            <label className="field">
              <span>Title</span>
              <input
                required
                value={proposalTitle}
                onChange={(e) => setProposalTitle(e.target.value)}
              />
            </label>
            <label className="field">
              <span>Expiry Date</span>
              <input
                type="date"
                value={proposalExpiry}
                onChange={(e) => setProposalExpiry(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => setShowProposalForm(false)}
              >
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={proposalSaving}>
                {proposalSaving ? 'Creating...' : 'Create Proposal'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
