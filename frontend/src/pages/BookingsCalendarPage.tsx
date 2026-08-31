import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { getBookingsCalendar } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, NotAvailable } from '../components/ui'
import type { Booking } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

function monthBounds(anchor: Date): { from: string; to: string; label: string } {
  const year = anchor.getFullYear()
  const month = anchor.getMonth()
  const from = new Date(year, month, 1)
  const to = new Date(year, month + 1, 0)
  const iso = (d: Date) => d.toISOString().slice(0, 10)
  const label = anchor.toLocaleDateString(undefined, { year: 'numeric', month: 'long' })
  return { from: iso(from), to: iso(to), label }
}

export default function BookingsCalendarPage() {
  const [anchor, setAnchor] = useState(() => new Date())
  const [bookings, setBookings] = useState<Booking[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)

  const { from, to, label } = useMemo(() => monthBounds(anchor), [anchor])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    getBookingsCalendar(from, to)
      .then((res) => {
        if (!cancelled) setBookings(res.data.data ?? [])
      })
      .catch((err) => {
        if (cancelled) return
        const anyErr = err as { response?: { status?: number } }
        if (anyErr.response?.status === 404) {
          setNotAvailable(true)
        } else {
          setError(getErrorMessage(err, 'Unable to load the booking calendar.'))
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [from, to])

  const grouped = useMemo(() => {
    const map = new Map<string, Booking[]>()
    for (const b of bookings) {
      const key = b.travel_date ?? 'Unknown date'
      const list = map.get(key) ?? []
      list.push(b)
      map.set(key, list)
    }
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b))
  }, [bookings])

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Bookings Calendar</h1>
        </div>
        <NotAvailable label="Bookings calendar" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/bookings" className="back-link">
            ← Back to bookings
          </Link>
          <h1>Bookings Calendar</h1>
        </div>
      </div>

      <div className="view-toggle">
        <button
          type="button"
          onClick={() => setAnchor((a) => new Date(a.getFullYear(), a.getMonth() - 1, 1))}
        >
          ← Prev
        </button>
        <span style={{ padding: '0 12px', display: 'inline-flex', alignItems: 'center' }}>{label}</span>
        <button
          type="button"
          onClick={() => setAnchor((a) => new Date(a.getFullYear(), a.getMonth() + 1, 1))}
        >
          Next →
        </button>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : grouped.length === 0 ? (
        <EmptyState message="No bookings in this month." />
      ) : (
        grouped.map(([date, list]) => (
          <section className="panel" key={date}>
            <h2>{formatDate(date)}</h2>
            <ul className="simple-list">
              {list.map((b) => (
                <li key={b.id}>
                  <Link to={`/bookings/${b.id}`}>
                    <strong>{b.customer?.name ?? `Customer #${b.customer_id ?? '—'}`}</strong>
                  </Link>{' '}
                  — {b.package?.name ?? (b.package_id ? `Package #${b.package_id}` : 'No package')}
                  {b.return_date ? ` · returns ${formatDate(b.return_date)}` : ''}{' '}
                  <Badge text={titleCase(b.status)} tone={statusTone(b.status)} />
                </li>
              ))}
            </ul>
          </section>
        ))
      )}
    </div>
  )
}
