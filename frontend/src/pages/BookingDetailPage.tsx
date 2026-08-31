import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { getBooking } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, NotAvailable } from '../components/ui'
import type { Booking } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function BookingDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [booking, setBooking] = useState<Booking | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!id) return
    let cancelled = false
    setLoading(true)
    setError(null)
    getBooking(id)
      .then((res) => {
        if (!cancelled) setBooking(res.data.data)
      })
      .catch((err) => {
        if (!cancelled) setError(getErrorMessage(err, 'Unable to load this booking.'))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [id])

  if (loading) return <Loading label="Loading booking..." />
  if (error) return <ErrorBanner message={error} />
  if (!booking) return <ErrorBanner message="Booking not found." />

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/bookings" className="back-link">
            ← Back to bookings
          </Link>
          <h1>{booking.booking_number ?? `Booking #${booking.id}`}</h1>
        </div>
        <div className="header-actions">
          <Badge text={titleCase(booking.status)} tone={statusTone(booking.status)} />
        </div>
      </div>

      <section className="panel">
        <h2>Details</h2>
        <div className="detail-grid">
          <div>
            <span className="detail-label">Customer</span>
            <span>
              {booking.customer?.name ?? (booking.customer_id ? `Customer #${booking.customer_id}` : '—')}
            </span>
          </div>
          <div>
            <span className="detail-label">Package</span>
            <span>
              {booking.package?.name ?? (booking.package_id ? `Package #${booking.package_id}` : '—')}
            </span>
          </div>
          <div>
            <span className="detail-label">Type</span>
            <span>{titleCase(booking.booking_type)}</span>
          </div>
          <div>
            <span className="detail-label">Travel Date</span>
            <span>{formatDate(booking.travel_date)}</span>
          </div>
          <div>
            <span className="detail-label">Return Date</span>
            <span>{formatDate(booking.return_date)}</span>
          </div>
          <div>
            <span className="detail-label">Travelers</span>
            <span>{booking.number_of_travelers ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Total Amount</span>
            <span>{formatCurrency(booking.total_amount)}</span>
          </div>
          <div>
            <span className="detail-label">Payment Status</span>
            <Badge text={titleCase(booking.payment_status)} tone={statusTone(booking.payment_status)} />
          </div>
          <div>
            <span className="detail-label">Visa Required</span>
            <span>{booking.visa_required ? 'Yes' : 'No'}</span>
          </div>
          <div>
            <span className="detail-label">Special Requests</span>
            <span>{booking.special_requests ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Notes</span>
            <span>{booking.notes ?? '—'}</span>
          </div>
        </div>
      </section>

      <section className="panel">
        <h2>Travelers ({booking.travelers?.length ?? 0})</h2>
        {!booking.travelers || booking.travelers.length === 0 ? (
          <NotAvailable label="Traveler list" />
        ) : (
          <ul className="simple-list">
            {booking.travelers.map((t) => (
              <li key={t.id}>{t.name ?? `Traveler #${t.id}`}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Flight Bookings ({booking.flight_bookings?.length ?? 0})</h2>
        {!booking.flight_bookings || booking.flight_bookings.length === 0 ? (
          <EmptyState message="No flight bookings for this booking." />
        ) : (
          <ul className="simple-list">
            {booking.flight_bookings.map((f) => (
              <li key={f.id}>
                Flight #{f.flight_id} — Seat {f.seat_number ?? '—'} ({titleCase(f.status)})
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Hotel Bookings ({booking.hotel_bookings?.length ?? 0})</h2>
        {!booking.hotel_bookings || booking.hotel_bookings.length === 0 ? (
          <EmptyState message="No hotel bookings for this booking." />
        ) : (
          <ul className="simple-list">
            {booking.hotel_bookings.map((h, i) => (
              <li key={i}>{JSON.stringify(h)}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Visa Applications ({booking.visa_applications?.length ?? 0})</h2>
        {!booking.visa_applications || booking.visa_applications.length === 0 ? (
          <EmptyState message="No visa applications for this booking." />
        ) : (
          <ul className="simple-list">
            {booking.visa_applications.map((v) => (
              <li key={v.id}>
                {v.destination_country ?? 'Visa'} — {titleCase(v.status)}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
