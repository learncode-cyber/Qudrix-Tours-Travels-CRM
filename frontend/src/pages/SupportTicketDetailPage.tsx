import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  applySupportTicketTriage,
  getSupportTicket,
  listSupportTicketTriages,
  replySupportTicket,
  triageSupportTicket,
  updateSupportTicketStatus,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading } from '../components/ui'
import type { SupportTicket, TicketAiTriage } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function SupportTicketDetailPage() {
  const { id } = useParams()
  const [ticket, setTicket] = useState<SupportTicket | null>(null)
  const [triages, setTriages] = useState<TicketAiTriage[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [triageError, setTriageError] = useState<string | null>(null)
  const [triaging, setTriaging] = useState(false)
  const [applyingId, setApplyingId] = useState<number | null>(null)
  const [replyText, setReplyText] = useState('')
  const [replyBusy, setReplyBusy] = useState(false)

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [ticketRes, triageRes] = await Promise.all([getSupportTicket(id), listSupportTicketTriages(id)])
      setTicket(ticketRes.data.data)
      setTriages(triageRes.data.data ?? [])
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this ticket.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function handleStatusChange(status: string) {
    if (!id) return
    try {
      await updateSupportTicketStatus(id, status)
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to update ticket status.'))
    }
  }

  async function handleReply(e: FormEvent) {
    e.preventDefault()
    if (!id || !replyText.trim()) return
    setReplyBusy(true)
    try {
      await replySupportTicket(id, replyText)
      setReplyText('')
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to post this reply.'))
    } finally {
      setReplyBusy(false)
    }
  }

  async function handleTriage() {
    if (!id) return
    setTriaging(true)
    setTriageError(null)
    try {
      await triageSupportTicket(id)
      await load()
    } catch (err) {
      setTriageError(getErrorMessage(err, 'AI triage failed.'))
    } finally {
      setTriaging(false)
    }
  }

  async function handleApply(triageId: number) {
    if (!id) return
    setApplyingId(triageId)
    setTriageError(null)
    try {
      await applySupportTicketTriage(id, triageId)
      await load()
    } catch (err) {
      setTriageError(getErrorMessage(err, 'Unable to apply this triage.'))
    } finally {
      setApplyingId(null)
    }
  }

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (!ticket) return <EmptyState message="Ticket not found." />

  return (
    <div className="page">
      <div className="page-header">
        <h1>{ticket.subject}</h1>
        <Link to="/support-tickets" className="btn btn-ghost btn-sm">
          Back to Tickets
        </Link>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '16px', alignItems: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div className="panel">
            <p>{ticket.description}</p>
            <div className="field-hint">
              <Badge text={titleCase(ticket.status)} tone={statusTone(ticket.status)} /> ·{' '}
              {titleCase(ticket.priority)} · {titleCase(ticket.category)} · Opened {formatDate(ticket.created_at)}
            </div>
            {ticket.escalated ? (
              <div className="state-block error" style={{ marginTop: '0.75rem' }}>
                Escalated ({ticket.escalation_source === 'ai_critical' ? 'AI critical triage' : 'human'})
                {ticket.escalation_note ? `: ${ticket.escalation_note}` : ''}
              </div>
            ) : null}
          </div>

          <div className="panel">
            <h3>Status</h3>
            <div className="row-actions">
              {['open', 'in_progress', 'resolved', 'closed'].map((s) => (
                <button
                  key={s}
                  type="button"
                  className={`btn btn-sm ${ticket.status === s ? 'btn-primary' : 'btn-ghost'}`}
                  onClick={() => handleStatusChange(s)}
                  disabled={ticket.status === s}
                >
                  {titleCase(s)}
                </button>
              ))}
            </div>
          </div>

          <div className="panel">
            <h3>Replies</h3>
            {ticket.replies && ticket.replies.length > 0 ? (
              <ul className="simple-list" style={{ flexDirection: 'column' }}>
                {ticket.replies.map((r) => (
                  <li key={r.id}>
                    <div className="field-hint">
                      {r.is_internal_note ? 'Internal note' : 'Reply'} · {formatDate(r.created_at)}
                    </div>
                    <div>{r.message}</div>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyState message="No replies yet." />
            )}
            <form onSubmit={handleReply} className="stacked-form">
              <textarea
                placeholder="Write a reply..."
                value={replyText}
                onChange={(e) => setReplyText(e.target.value)}
              />
              <button type="submit" className="btn btn-primary btn-sm" disabled={replyBusy || !replyText.trim()}>
                {replyBusy ? 'Sending...' : 'Send Reply'}
              </button>
            </form>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div className="panel">
            <h3>AI Triage</h3>
            <p className="field-hint">
              Severity, category, and a draft reply are suggestions a human reviews. Only a
              "critical" severity auto-escalates — it never answers or resolves the ticket.
            </p>
            {triageError ? <ErrorBanner message={triageError} /> : null}
            <button type="button" className="btn btn-primary btn-sm" onClick={handleTriage} disabled={triaging}>
              {triaging ? 'Running Triage...' : 'Run AI Triage'}
            </button>

            {triages.length === 0 ? (
              <EmptyState message="No triage runs yet." />
            ) : (
              <ul className="simple-list" style={{ flexDirection: 'column' }}>
                {triages.map((tr) => (
                  <li key={tr.id} style={{ border: '1px solid var(--color-border)', borderRadius: 8, padding: '0.75rem', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                    <div className="field-hint">{formatDate(tr.created_at)}</div>
                    <div>
                      <Badge
                        text={titleCase(tr.suggested_severity)}
                        tone={
                          tr.suggested_severity === 'critical'
                            ? 'danger'
                            : tr.suggested_severity === 'high'
                              ? 'warning'
                              : 'default'
                        }
                      />{' '}
                      {titleCase(tr.suggested_category)}
                      {tr.sentiment ? ` · ${titleCase(tr.sentiment)}` : ''}
                    </div>
                    {tr.suggested_response ? (
                      <div>
                        <strong>Draft reply:</strong> {tr.suggested_response}
                      </div>
                    ) : null}
                    {tr.suggested_resolution ? (
                      <div>
                        <strong>Suggested resolution:</strong> {tr.suggested_resolution}
                      </div>
                    ) : null}
                    {tr.applied_at ? (
                      <Badge text="Applied" tone="success" />
                    ) : (
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={applyingId === tr.id}
                        onClick={() => handleApply(tr.id)}
                      >
                        {applyingId === tr.id ? 'Applying...' : 'Apply to Ticket'}
                      </button>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
