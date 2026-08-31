import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  cancelBooking,
  confirmBooking,
  createBooking,
  listBookings,
  listCustomers,
  listPackages,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Booking, Customer, Package } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const STATUS_FILTERS = ['all', 'pending', 'confirmed', 'cancelled', 'completed']

const emptyForm = {
  customer_id: '',
  package_id: '',
  booking_type: 'individual',
  travel_date: '',
  return_date: '',
  number_of_travelers: 1,
  total_amount: '',
  currency: 'USD',
  visa_required: false,
  special_requests: '',
  notes: '',
}

export default function BookingsPage() {
  const navigate = useNavigate()
  const [bookings, setBookings] = useState<Booking[]>([])
  const [customers, setCustomers] = useState<Customer[]>([])
  const [packages, setPackages] = useState<Package[]>([])
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
      const res = await listBookings()
      setBookings(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load bookings.'))
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
    listPackages()
      .then((res) => setPackages(res.data.data ?? []))
      .catch(() => setPackages([]))
  }, [])

  const filtered = useMemo(() => {
    if (statusFilter === 'all') return bookings
    return bookings.filter((b) => b.status === statusFilter)
  }, [bookings, statusFilter])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createBooking({
        customer_id: form.customer_id ? Number(form.customer_id) : undefined,
        package_id: form.package_id ? Number(form.package_id) : undefined,
        booking_type: form.booking_type,
        travel_date: form.travel_date || undefined,
        return_date: form.return_date || undefined,
        number_of_travelers: Number(form.number_of_travelers) || 1,
        total_amount: form.total_amount ? Number(form.total_amount) : undefined,
        currency: form.currency,
        visa_required: form.visa_required,
        special_requests: form.special_requests || undefined,
        notes: form.notes || undefined,
      } as Partial<Booking>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create booking.'))
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

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Bookings</h1>
        </div>
        <NotAvailable label="Bookings" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Bookings</h1>
        <div className="header-actions">
          <Link to="/bookings/calendar" className="btn btn-ghost">
            Calendar
          </Link>
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Booking
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
        <EmptyState message="No bookings found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Customer</th>
                <th>Package</th>
                <th>Status</th>
                <th>Travel Dates</th>
                <th>Travelers</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((b) => (
                <tr key={b.id} className="clickable-row" onClick={() => navigate(`/bookings/${b.id}`)}>
                  <td>{b.booking_number ?? b.id}</td>
                  <td>{b.customer?.name ?? (b.customer_id ? `Customer #${b.customer_id}` : '—')}</td>
                  <td>{b.package?.name ?? (b.package_id ? `Package #${b.package_id}` : '—')}</td>
                  <td>
                    <Badge text={titleCase(b.status)} tone={statusTone(b.status)} />
                  </td>
                  <td>
                    {formatDate(b.travel_date)}
                    {b.return_date ? ` – ${formatDate(b.return_date)}` : ''}
                  </td>
                  <td>{b.number_of_travelers ?? '—'}</td>
                  <td>{formatCurrency(b.total_amount)}</td>
                  <td>
                    <Badge text={titleCase(b.payment_status)} tone={statusTone(b.payment_status)} />
                  </td>
                  <td onClick={(e) => e.stopPropagation()}>
                    <div className="row-actions">
                      {b.status === 'pending' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === b.id}
                          onClick={() => runAction(b.id, () => confirmBooking(b.id))}
                        >
                          Confirm
                        </button>
                      ) : null}
                      {b.status !== 'cancelled' && b.status !== 'completed' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === b.id}
                          onClick={() => runAction(b.id, () => cancelBooking(b.id))}
                        >
                          Cancel
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
        <Modal title="New Booking" onClose={() => setShowForm(false)}>
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
              <span>Package</span>
              <select
                value={form.package_id}
                onChange={(e) => setForm({ ...form, package_id: e.target.value })}
              >
                <option value="">Select a package...</option>
                {packages.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Booking Type</span>
              <select
                value={form.booking_type}
                onChange={(e) => setForm({ ...form, booking_type: e.target.value })}
              >
                <option value="individual">Individual</option>
                <option value="group">Group</option>
                <option value="corporate">Corporate</option>
              </select>
            </label>
            <label className="field">
              <span>Travel Date</span>
              <input
                type="date"
                required
                value={form.travel_date}
                onChange={(e) => setForm({ ...form, travel_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Return Date</span>
              <input
                type="date"
                value={form.return_date}
                onChange={(e) => setForm({ ...form, return_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Number of Travelers</span>
              <input
                type="number"
                min={1}
                value={form.number_of_travelers}
                onChange={(e) => setForm({ ...form, number_of_travelers: Number(e.target.value) })}
              />
            </label>
            <label className="field">
              <span>Total Amount</span>
              <input
                type="number"
                min={0}
                required
                value={form.total_amount}
                onChange={(e) => setForm({ ...form, total_amount: e.target.value })}
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
            <label className="field field-inline">
              <input
                type="checkbox"
                checked={form.visa_required}
                onChange={(e) => setForm({ ...form, visa_required: e.target.checked })}
              />
              <span>Visa Required</span>
            </label>
            <label className="field">
              <span>Special Requests</span>
              <textarea
                value={form.special_requests}
                onChange={(e) => setForm({ ...form, special_requests: e.target.value })}
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
                {saving ? 'Saving...' : 'Create Booking'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
