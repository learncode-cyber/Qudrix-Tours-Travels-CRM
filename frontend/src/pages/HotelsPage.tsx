import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { createHotel, listHotels } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Hotel } from '../types'
import { formatCurrency, getErrorMessage } from '../utils/format'

const emptyForm = {
  name: '',
  city: '',
  country: '',
  address: '',
  phone: '',
  email: '',
  star_rating: '',
  total_rooms: '',
  price_per_night: '',
  currency: 'USD',
}

export default function HotelsPage() {
  const navigate = useNavigate()
  const [hotels, setHotels] = useState<Hotel[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listHotels()
      setHotels(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load hotels.'))
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
      await createHotel({
        name: form.name,
        city: form.city || undefined,
        country: form.country || undefined,
        address: form.address || undefined,
        phone: form.phone || undefined,
        email: form.email || undefined,
        star_rating: form.star_rating ? Number(form.star_rating) : undefined,
        total_rooms: form.total_rooms ? Number(form.total_rooms) : undefined,
        price_per_night: form.price_per_night ? Number(form.price_per_night) : undefined,
        currency: form.currency,
      } as Partial<Hotel>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create hotel.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Hotels</h1>
        </div>
        <NotAvailable label="Hotels" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Hotels</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Hotel
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : hotels.length === 0 ? (
        <EmptyState message="No hotels found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Location</th>
                <th>Stars</th>
                <th>Rooms</th>
                <th>Price / Night</th>
              </tr>
            </thead>
            <tbody>
              {hotels.map((h) => (
                <tr key={h.id} className="clickable-row" onClick={() => navigate(`/hotels/${h.id}`)}>
                  <td>{h.name}</td>
                  <td>
                    {h.city ?? '—'}
                    {h.country ? `, ${h.country}` : ''}
                  </td>
                  <td>{h.star_rating ?? '—'}</td>
                  <td>
                    {h.available_rooms ?? '—'} / {h.total_rooms ?? '—'}
                  </td>
                  <td>{formatCurrency(h.price_per_night)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Hotel" onClose={() => setShowForm(false)}>
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
              <span>City</span>
              <input
                value={form.city}
                onChange={(e) => setForm({ ...form, city: e.target.value })}
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
              <span>Address</span>
              <input
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Phone</span>
              <input
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Star Rating</span>
              <input
                type="number"
                min={1}
                max={5}
                value={form.star_rating}
                onChange={(e) => setForm({ ...form, star_rating: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Total Rooms</span>
              <input
                type="number"
                min={0}
                value={form.total_rooms}
                onChange={(e) => setForm({ ...form, total_rooms: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price / Night</span>
              <input
                type="number"
                min={0}
                value={form.price_per_night}
                onChange={(e) => setForm({ ...form, price_per_night: e.target.value })}
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
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Hotel'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
