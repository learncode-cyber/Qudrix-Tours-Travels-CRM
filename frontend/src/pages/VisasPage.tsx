import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import {
  approveVisa,
  checkVisaExpiryReminders,
  createEmbassy,
  createVisa,
  deleteEmbassy,
  getVisaChecklist,
  listEmbassies,
  listVisas,
  submitVisa,
  updateVisaChecklistItem,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Embassy, VisaApplication, VisaChecklistItem } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const STATUS_FILTERS = ['all', 'pending', 'submitted', 'approved', 'rejected']

const emptyVisaForm = {
  booking_id: '',
  booking_traveler_id: '',
  destination_country: '',
  visa_type: '',
  embassy_id: '',
  application_date: '',
  notes: '',
}

const emptyEmbassyForm = {
  name: '',
  country: '',
  city: '',
  address: '',
  contact_email: '',
  contact_phone: '',
  website: '',
  average_processing_days: '',
  notes: '',
}

export default function VisasPage() {
  const [tab, setTab] = useState<'applications' | 'embassies'>('applications')

  return (
    <div className="page">
      <div className="page-header">
        <h1>Visas</h1>
      </div>
      <div className="view-toggle">
        <button type="button" className={tab === 'applications' ? 'active' : ''} onClick={() => setTab('applications')}>
          Applications
        </button>
        <button type="button" className={tab === 'embassies' ? 'active' : ''} onClick={() => setTab('embassies')}>
          Embassies
        </button>
      </div>
      {tab === 'applications' ? <VisaApplicationsTab /> : <EmbassiesTab />}
    </div>
  )
}

function VisaApplicationsTab() {
  const [visas, setVisas] = useState<VisaApplication[]>([])
  const [embassies, setEmbassies] = useState<Embassy[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [statusFilter, setStatusFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyVisaForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [checklistFor, setChecklistFor] = useState<VisaApplication | null>(null)

  const [reminderBusy, setReminderBusy] = useState(false)
  const [reminderResult, setReminderResult] = useState<string | null>(null)
  const [reminderError, setReminderError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listVisas()
      setVisas(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load visa applications.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    listEmbassies()
      .then((res) => setEmbassies(res.data.data ?? []))
      .catch(() => setEmbassies([]))
  }, [])

  const filtered = useMemo(() => {
    if (statusFilter === 'all') return visas
    return visas.filter((v) => v.status === statusFilter)
  }, [visas, statusFilter])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createVisa({
        booking_id: form.booking_id ? Number(form.booking_id) : undefined,
        booking_traveler_id: form.booking_traveler_id ? Number(form.booking_traveler_id) : undefined,
        destination_country: form.destination_country,
        visa_type: form.visa_type || undefined,
        embassy_id: form.embassy_id ? Number(form.embassy_id) : undefined,
        application_date: form.application_date || undefined,
        notes: form.notes || undefined,
      } as Partial<VisaApplication>)
      setForm(emptyVisaForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create visa application.'))
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

  async function handleCheckReminders() {
    setReminderBusy(true)
    setReminderError(null)
    setReminderResult(null)
    try {
      const res = await checkVisaExpiryReminders()
      const { visa_reminders_created, passport_reminders_created } = res.data.data
      setReminderResult(
        `Created ${visa_reminders_created} visa reminder(s) and ${passport_reminders_created} passport reminder(s).`,
      )
    } catch (err) {
      setReminderError(getErrorMessage(err, 'Unable to run the expiry reminder sweep.'))
    } finally {
      setReminderBusy(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Visa applications" />

  return (
    <>
      <div className="page-header">
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
        <div className="header-actions">
          <button type="button" className="btn btn-ghost" onClick={handleCheckReminders} disabled={reminderBusy}>
            {reminderBusy ? 'Checking...' : 'Check Expiry Reminders'}
          </button>
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Visa Application
          </button>
        </div>
      </div>

      {reminderResult ? <div className="state-block success">{reminderResult}</div> : null}
      {reminderError ? <ErrorBanner message={reminderError} /> : null}
      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState message="No visa applications found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Booking</th>
                <th>Destination</th>
                <th>Type</th>
                <th>Status</th>
                <th>Application Date</th>
                <th>Expiry</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((v) => (
                <tr key={v.id}>
                  <td>{v.booking_id ? `Booking #${v.booking_id}` : '—'}</td>
                  <td>{v.destination_country ?? '—'}</td>
                  <td>{v.visa_type ?? '—'}</td>
                  <td>
                    <Badge text={titleCase(v.status)} tone={statusTone(v.status)} />
                  </td>
                  <td>{formatDate(v.application_date)}</td>
                  <td>{formatDate(v.expiry_date)}</td>
                  <td>
                    <div className="row-actions">
                      {v.status === 'pending' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === v.id}
                          onClick={() => runAction(v.id, () => submitVisa(v.id))}
                        >
                          Submit
                        </button>
                      ) : null}
                      {v.status === 'submitted' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === v.id}
                          onClick={() => runAction(v.id, () => approveVisa(v.id))}
                        >
                          Approve
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => setChecklistFor(v)}
                      >
                        Checklist
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
        <Modal title="New Visa Application" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Booking ID</span>
              <input
                required
                value={form.booking_id}
                onChange={(e) => setForm({ ...form, booking_id: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Booking Traveler ID</span>
              <input
                value={form.booking_traveler_id}
                onChange={(e) => setForm({ ...form, booking_traveler_id: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Destination Country</span>
              <input
                required
                value={form.destination_country}
                onChange={(e) => setForm({ ...form, destination_country: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Visa Type</span>
              <input
                value={form.visa_type}
                onChange={(e) => setForm({ ...form, visa_type: e.target.value })}
                placeholder="tourist / business"
              />
            </label>
            <label className="field">
              <span>Embassy</span>
              <select
                value={form.embassy_id}
                onChange={(e) => setForm({ ...form, embassy_id: e.target.value })}
              >
                <option value="">Select an embassy...</option>
                {embassies.map((emb) => (
                  <option key={emb.id} value={emb.id}>
                    {emb.name} ({emb.country})
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Application Date</span>
              <input
                type="date"
                value={form.application_date}
                onChange={(e) => setForm({ ...form, application_date: e.target.value })}
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
                {saving ? 'Saving...' : 'Create Application'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {checklistFor ? (
        <VisaChecklistModal visa={checklistFor} onClose={() => setChecklistFor(null)} />
      ) : null}
    </>
  )
}

function VisaChecklistModal({ visa, onClose }: { visa: VisaApplication; onClose: () => void }) {
  const [items, setItems] = useState<VisaChecklistItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await getVisaChecklist(visa.id)
      setItems(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load the checklist.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visa.id])

  async function updateStatus(item: VisaChecklistItem, status: string) {
    setBusyId(item.id)
    try {
      await updateVisaChecklistItem(visa.id, item.id, status)
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to update this document.'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <Modal title={`Checklist — ${visa.destination_country ?? `Visa #${visa.id}`}`} onClose={onClose}>
      {notAvailable ? (
        <NotAvailable label="Document checklist" />
      ) : loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : items.length === 0 ? (
        <EmptyState message="No checklist items for this application." />
      ) : (
        <ul className="simple-list">
          {items.map((item) => (
            <li key={item.id}>
              <span>{item.document_name}</span>{' '}
              <Badge text={titleCase(item.status)} tone={statusTone(item.status)} />
              <div className="row-actions">
                {(['missing', 'submitted', 'verified', 'rejected'] as const)
                  .filter((s) => s !== item.status)
                  .map((s) => (
                    <button
                      key={s}
                      type="button"
                      className="btn btn-ghost btn-sm"
                      disabled={busyId === item.id}
                      onClick={() => updateStatus(item, s)}
                    >
                      {titleCase(s)}
                    </button>
                  ))}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  )
}

function EmbassiesTab() {
  const [embassies, setEmbassies] = useState<Embassy[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyEmbassyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listEmbassies()
      setEmbassies(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load embassies.'))
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
      await createEmbassy({
        name: form.name,
        country: form.country || undefined,
        city: form.city || undefined,
        address: form.address || undefined,
        contact_email: form.contact_email || undefined,
        contact_phone: form.contact_phone || undefined,
        website: form.website || undefined,
        average_processing_days: form.average_processing_days
          ? Number(form.average_processing_days)
          : undefined,
        notes: form.notes || undefined,
      } as Partial<Embassy>)
      setForm(emptyEmbassyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create embassy.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(emb: Embassy) {
    if (!window.confirm(`Delete embassy "${emb.name}"?`)) return
    setBusyId(emb.id)
    setActionError(null)
    try {
      await deleteEmbassy(emb.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this embassy.'))
    } finally {
      setBusyId(null)
    }
  }

  if (notAvailable) return <NotAvailable label="Embassies" />

  return (
    <>
      <div className="page-header">
        <h2>Embassies</h2>
        <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
          + New Embassy
        </button>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : embassies.length === 0 ? (
        <EmptyState message="No embassies configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Country</th>
                <th>City</th>
                <th>Contact</th>
                <th>Avg. Processing Days</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {embassies.map((emb) => (
                <tr key={emb.id}>
                  <td>{emb.name}</td>
                  <td>{emb.country ?? '—'}</td>
                  <td>{emb.city ?? '—'}</td>
                  <td>{emb.contact_email ?? emb.contact_phone ?? '—'}</td>
                  <td>{emb.average_processing_days ?? '—'}</td>
                  <td>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      disabled={busyId === emb.id}
                      onClick={() => handleDelete(emb)}
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Embassy" onClose={() => setShowForm(false)}>
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
              <span>Country</span>
              <input
                value={form.country}
                onChange={(e) => setForm({ ...form, country: e.target.value })}
              />
            </label>
            <label className="field">
              <span>City</span>
              <input
                value={form.city}
                onChange={(e) => setForm({ ...form, city: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Address</span>
              <input
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Contact Email</span>
              <input
                type="email"
                value={form.contact_email}
                onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Contact Phone</span>
              <input
                value={form.contact_phone}
                onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Website</span>
              <input
                value={form.website}
                onChange={(e) => setForm({ ...form, website: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Avg. Processing Days</span>
              <input
                type="number"
                min={0}
                value={form.average_processing_days}
                onChange={(e) => setForm({ ...form, average_processing_days: e.target.value })}
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
                {saving ? 'Saving...' : 'Create Embassy'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}
