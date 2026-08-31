import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  assignConversation,
  createConversation,
  getConversation,
  replyToConversation,
  listConversations,
  updateConversationStatus,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Conversation, ConversationChannel } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const CHANNELS: ConversationChannel[] = ['website_chat', 'email', 'whatsapp', 'telegram', 'sms', 'internal']
const STATUS_FILTERS = ['all', 'open', 'pending', 'closed']

const emptyForm = {
  customer_id: '',
  lead_id: '',
  channel: 'telegram' as ConversationChannel,
  external_thread_id: '',
  subject: '',
}

function deliveryTone(status?: string | null) {
  if (status === 'sent') return 'success'
  if (status === 'failed') return 'danger'
  if (status === 'not_attempted') return 'warning'
  return 'default'
}

export default function ConversationsPage() {
  const [conversations, setConversations] = useState<Conversation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [channelFilter, setChannelFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [openConversation, setOpenConversation] = useState<Conversation | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listConversations({
        channel: channelFilter === 'all' ? undefined : channelFilter,
        status: statusFilter === 'all' ? undefined : statusFilter,
      })
      setConversations(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load conversations.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channelFilter, statusFilter])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createConversation({
        customer_id: form.customer_id ? Number(form.customer_id) : undefined,
        lead_id: form.lead_id ? Number(form.lead_id) : undefined,
        channel: form.channel,
        external_thread_id: form.external_thread_id || undefined,
        subject: form.subject || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this conversation.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Conversations" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Conversations</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Conversation
          </button>
        </div>
      </div>
      <div className="view-toggle">
        {['all', ...CHANNELS].map((c) => (
          <button key={c} type="button" className={channelFilter === c ? 'active' : ''} onClick={() => setChannelFilter(c)}>
            {titleCase(c)}
          </button>
        ))}
      </div>
      <div className="view-toggle">
        {STATUS_FILTERS.map((s) => (
          <button key={s} type="button" className={statusFilter === s ? 'active' : ''} onClick={() => setStatusFilter(s)}>
            {titleCase(s)}
          </button>
        ))}
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : conversations.length === 0 ? (
        <EmptyState message="No conversations found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Contact</th>
                <th>Channel</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Unread</th>
                <th>Last Message</th>
                <th>Assignee</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {conversations.map((c) => (
                <tr key={c.id}>
                  <td>{c.customer?.name ?? (c.customer_id ? `Customer #${c.customer_id}` : c.lead_id ? `Lead #${c.lead_id}` : '—')}</td>
                  <td>{titleCase(c.channel)}</td>
                  <td>{c.subject ?? '—'}</td>
                  <td>
                    <Badge text={titleCase(c.status)} tone={statusTone(c.status)} />
                  </td>
                  <td>{c.unread_count ?? 0}</td>
                  <td>{formatDate(c.last_message_at)}</td>
                  <td>{c.assignee?.name ?? '—'}</td>
                  <td>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setOpenConversation(c)}>
                      Open
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Conversation" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Customer ID</span>
              <input value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })} />
            </label>
            <label className="field">
              <span>Lead ID</span>
              <input value={form.lead_id} onChange={(e) => setForm({ ...form, lead_id: e.target.value })} />
              <p className="field-hint">Provide a customer ID or a lead ID (one is required).</p>
            </label>
            <label className="field">
              <span>Channel</span>
              <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value as ConversationChannel })}>
                {CHANNELS.map((c) => (
                  <option key={c} value={c}>
                    {titleCase(c)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>External Thread ID</span>
              <input
                value={form.external_thread_id}
                onChange={(e) => setForm({ ...form, external_thread_id: e.target.value })}
              />
              <p className="field-hint">
                For Telegram, this is the chat id replies get sent to. Leave blank for a channel with no real
                thread yet (e.g. internal).
              </p>
            </label>
            <label className="field">
              <span>Subject</span>
              <input value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Conversation'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {openConversation ? (
        <ConversationThreadModal
          conversationId={openConversation.id}
          onClose={() => setOpenConversation(null)}
          onChanged={load}
        />
      ) : null}
    </div>
  )
}

function ConversationThreadModal({
  conversationId,
  onClose,
  onChanged,
}: {
  conversationId: number
  onClose: () => void
  onChanged: () => void
}) {
  const [conversation, setConversation] = useState<Conversation | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [replyBody, setReplyBody] = useState('')
  const [isNote, setIsNote] = useState(false)
  const [sending, setSending] = useState(false)
  const [sendError, setSendError] = useState<string | null>(null)
  const [statusBusy, setStatusBusy] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const res = await getConversation(conversationId)
      setConversation(res.data.data)
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this conversation.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversationId])

  async function handleReply(e: FormEvent) {
    e.preventDefault()
    setSending(true)
    setSendError(null)
    try {
      await replyToConversation(conversationId, { body: replyBody, is_internal_note: isNote })
      setReplyBody('')
      setIsNote(false)
      await load()
      onChanged()
    } catch (err) {
      setSendError(getErrorMessage(err, 'Unable to send this reply.'))
    } finally {
      setSending(false)
    }
  }

  async function handleStatusChange(status: string) {
    setStatusBusy(true)
    try {
      await updateConversationStatus(conversationId, status)
      await load()
      onChanged()
    } catch (err) {
      setSendError(getErrorMessage(err, 'Unable to update the status.'))
    } finally {
      setStatusBusy(false)
    }
  }

  async function handleAssignToMe() {
    if (!conversation) return
    setStatusBusy(true)
    try {
      const meId = window.prompt('Assign to user ID:')
      if (!meId) return
      await assignConversation(conversationId, meId)
      await load()
      onChanged()
    } catch (err) {
      setSendError(getErrorMessage(err, 'Unable to assign this conversation.'))
    } finally {
      setStatusBusy(false)
    }
  }

  return (
    <Modal title={conversation?.subject ?? `Conversation #${conversationId}`} onClose={onClose}>
      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : conversation ? (
        <>
          <div className="row-actions">
            <Badge text={titleCase(conversation.channel)} tone="default" />
            <Badge text={titleCase(conversation.status)} tone={statusTone(conversation.status)} />
            {(['open', 'pending', 'closed'] as const)
              .filter((s) => s !== conversation.status)
              .map((s) => (
                <button key={s} type="button" className="btn btn-ghost btn-sm" disabled={statusBusy} onClick={() => handleStatusChange(s)}>
                  Mark {titleCase(s)}
                </button>
              ))}
            <button type="button" className="btn btn-ghost btn-sm" disabled={statusBusy} onClick={handleAssignToMe}>
              Assign
            </button>
          </div>

          {conversation.messages && conversation.messages.length > 0 ? (
            <ul className="timeline-list">
              {conversation.messages.map((m) => (
                <li key={m.id}>
                  <div>
                    <strong>{m.direction === 'inbound' ? 'Customer' : m.is_internal_note ? 'Internal Note' : 'Staff'}</strong>{' '}
                    <span className="field-hint">{formatDate(m.created_at)}</span>
                    {m.delivery_status ? (
                      <Badge text={titleCase(m.delivery_status)} tone={deliveryTone(m.delivery_status)} />
                    ) : null}
                  </div>
                  <p>{m.body}</p>
                  {m.delivery_error ? <p className="field-hint">{m.delivery_error}</p> : null}
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState message="No messages yet." />
          )}

          <form onSubmit={handleReply} className="stacked-form">
            {sendError ? <div className="state-block error">{sendError}</div> : null}
            <label className="field">
              <span>Reply</span>
              <textarea required value={replyBody} onChange={(e) => setReplyBody(e.target.value)} />
            </label>
            <label className="field field-inline">
              <input type="checkbox" checked={isNote} onChange={(e) => setIsNote(e.target.checked)} />
              <span>Internal note (never sent to the customer)</span>
            </label>
            <div className="modal-actions">
              <button type="submit" className="btn btn-primary" disabled={sending}>
                {sending ? 'Sending...' : isNote ? 'Add Note' : 'Send Reply'}
              </button>
            </div>
          </form>
        </>
      ) : null}
    </Modal>
  )
}
