import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { bookFlightSeat, createFlight, listFlights } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { Flight } from '../types'
import { formatCurrency, formatDate, getErrorMessage } from '../utils/format'

const emptyFlightForm = {
  airline_code: '',
  flight_number: '',
  departure_airport: '',
  arrival_airport: '',
  departure_date: '',
  arrival_date: '',
  departure_time: '',
  arrival_time: '',
  aircraft_type: '',
  total_seats: '',
  price_per_seat: '',
  currency: 'USD',
}

const emptyBookForm = {
  booking_id: '',
  // Comma-separated traveler IDs — the API books one seat per traveler
  // in this list and auto-assigns seat numbers; it has no concept of a
  // single explicit seat number in the request.
  traveler_ids: '',
  cabin_class: '',
  fare_type: '',
}

export default function FlightsPage() {
  const [flights, setFlights] = useState<Flight[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyFlightForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const [bookingFlight, setBookingFlight] = useState<Flight | null>(null)
  const [bookForm, setBookForm] = useState(emptyBookForm)
  const [bookSaving, setBookSaving] = useState(false)
  const [bookError, setBookError] = useState<string | null>(null)
  const [bookSuccess, setBookSuccess] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listFlights()
      setFlights(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load flights.'))
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
      await createFlight({
        airline_code: form.airline_code,
        flight_number: form.flight_number,
        departure_airport: form.departure_airport,
        arrival_airport: form.arrival_airport,
        departure_date: form.departure_date || undefined,
        arrival_date: form.arrival_date || undefined,
        // The backend requires H:i:s (with seconds); the native <input
        // type="time"> only gives "HH:MM", so append seconds here.
        departure_time: form.departure_time ? `${form.departure_time}:00` : undefined,
        arrival_time: form.arrival_time ? `${form.arrival_time}:00` : undefined,
        aircraft_type: form.aircraft_type || undefined,
        total_seats: form.total_seats ? Number(form.total_seats) : undefined,
        price_per_seat: form.price_per_seat ? Number(form.price_per_seat) : undefined,
        currency: form.currency || undefined,
      } as Partial<Flight>)
      setForm(emptyFlightForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create flight.'))
    } finally {
      setSaving(false)
    }
  }

  function openBook(flight: Flight) {
    setBookingFlight(flight)
    setBookForm(emptyBookForm)
    setBookError(null)
    setBookSuccess(null)
  }

  async function handleBook(e: FormEvent) {
    e.preventDefault()
    if (!bookingFlight) return
    setBookSaving(true)
    setBookError(null)
    setBookSuccess(null)
    const travelerIds = bookForm.traveler_ids
      .split(',')
      .map((id) => id.trim())
      .filter(Boolean)
    if (travelerIds.length === 0) {
      setBookError('Enter at least one traveler ID.')
      return
    }
    try {
      const res = await bookFlightSeat({
        flight_id: bookingFlight.id,
        booking_id: bookForm.booking_id,
        travelers: travelerIds,
        cabin_class: bookForm.cabin_class || undefined,
        fare_type: bookForm.fare_type || undefined,
      })
      setBookSuccess(`Seat(s) booked successfully. PNR: ${res.data.data.pnr}`)
      await load()
    } catch (err) {
      setBookError(getErrorMessage(err, 'Unable to book seat.'))
    } finally {
      setBookSaving(false)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Flights</h1>
        </div>
        <NotAvailable label="Flights" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Flights</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Flight
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : flights.length === 0 ? (
        <EmptyState message="No flights found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Flight</th>
                <th>Route</th>
                <th>Departure</th>
                <th>Arrival</th>
                <th>Aircraft</th>
                <th>Seats</th>
                <th>Price / Seat</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {flights.map((f) => (
                <tr key={f.id}>
                  <td>
                    {f.airline_code ?? ''} {f.flight_number ?? ''}
                  </td>
                  <td>
                    {f.departure_airport ?? '—'} → {f.arrival_airport ?? '—'}
                  </td>
                  <td>
                    {formatDate(f.departure_date)} {f.departure_time ?? ''}
                  </td>
                  <td>
                    {formatDate(f.arrival_date)} {f.arrival_time ?? ''}
                  </td>
                  <td>{f.aircraft_type ?? '—'}</td>
                  <td>
                    {f.available_seats ?? '—'} / {f.total_seats ?? '—'}
                  </td>
                  <td>{formatCurrency(f.price_per_seat)}</td>
                  <td>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => openBook(f)}>
                      Book Seat
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Flight" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Airline Code</span>
              <input
                required
                value={form.airline_code}
                onChange={(e) => setForm({ ...form, airline_code: e.target.value })}
                placeholder="EK"
              />
            </label>
            <label className="field">
              <span>Flight Number</span>
              <input
                required
                value={form.flight_number}
                onChange={(e) => setForm({ ...form, flight_number: e.target.value })}
                placeholder="202"
              />
            </label>
            <label className="field">
              <span>Departure Airport</span>
              <input
                required
                value={form.departure_airport}
                onChange={(e) => setForm({ ...form, departure_airport: e.target.value })}
                placeholder="DXB"
              />
            </label>
            <label className="field">
              <span>Arrival Airport</span>
              <input
                required
                value={form.arrival_airport}
                onChange={(e) => setForm({ ...form, arrival_airport: e.target.value })}
                placeholder="JFK"
              />
            </label>
            <label className="field">
              <span>Departure Date</span>
              <input
                type="date"
                required
                value={form.departure_date}
                onChange={(e) => setForm({ ...form, departure_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Departure Time</span>
              <input
                type="time"
                value={form.departure_time}
                onChange={(e) => setForm({ ...form, departure_time: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Arrival Date</span>
              <input
                type="date"
                required
                value={form.arrival_date}
                onChange={(e) => setForm({ ...form, arrival_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Arrival Time</span>
              <input
                type="time"
                value={form.arrival_time}
                onChange={(e) => setForm({ ...form, arrival_time: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Aircraft Type</span>
              <input
                value={form.aircraft_type}
                onChange={(e) => setForm({ ...form, aircraft_type: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Total Seats</span>
              <input
                type="number"
                min={1}
                required
                value={form.total_seats}
                onChange={(e) => setForm({ ...form, total_seats: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price per Seat</span>
              <input
                type="number"
                min={0}
                required
                value={form.price_per_seat}
                onChange={(e) => setForm({ ...form, price_per_seat: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                required
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
                {saving ? 'Saving...' : 'Create Flight'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {bookingFlight ? (
        <Modal
          title={`Book Seat — ${bookingFlight.airline_code ?? ''} ${bookingFlight.flight_number ?? ''}`}
          onClose={() => setBookingFlight(null)}
        >
          <form onSubmit={handleBook} className="stacked-form">
            <p className="field-hint">
              There is no cross-entity picker for bookings/travelers yet — enter the booking ID and
              the traveler ID(s) to seat (find these on the booking's detail page). Seat numbers are
              assigned automatically by the API, one per traveler.
            </p>
            {bookError ? <div className="state-block error">{bookError}</div> : null}
            {bookSuccess ? <div className="state-block success">{bookSuccess}</div> : null}
            <label className="field">
              <span>Booking ID</span>
              <input
                required
                value={bookForm.booking_id}
                onChange={(e) => setBookForm({ ...bookForm, booking_id: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Traveler ID(s)</span>
              <input
                required
                value={bookForm.traveler_ids}
                onChange={(e) => setBookForm({ ...bookForm, traveler_ids: e.target.value })}
                placeholder="1, 2, 3"
              />
            </label>
            <label className="field">
              <span>Cabin Class</span>
              <input
                value={bookForm.cabin_class}
                onChange={(e) => setBookForm({ ...bookForm, cabin_class: e.target.value })}
                placeholder="economy"
              />
            </label>
            <label className="field">
              <span>Fare Type</span>
              <input
                value={bookForm.fare_type}
                onChange={(e) => setBookForm({ ...bookForm, fare_type: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setBookingFlight(null)}>
                Close
              </button>
              <button type="submit" className="btn btn-primary" disabled={bookSaving}>
                {bookSaving ? 'Booking...' : 'Book Seat'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
