import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, EmptyState, Spinner } from '../../components/ui'

interface Booking {
  id: number
  booking_number: string
  status: string
  payment_status: string
  travel_date: string | null
  number_of_travelers: number
  total_amount: number
  currency: string
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  pending: 'warn',
  confirmed: 'success',
  cancelled: 'danger',
}

export default function BookingsList() {
  const [bookings, setBookings] = useState<Booking[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get('/bookings')
      .then((res) => setBookings(res.data.data))
      .catch(() => setError('Could not load bookings.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Bookings</h1>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : bookings.length === 0 ? (
          <EmptyState title="No bookings yet" description="Confirmed bookings will appear here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Number</th>
                <th className="px-4 py-3 font-medium">Travel date</th>
                <th className="px-4 py-3 font-medium">Travelers</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-4 py-3 font-medium">Payment</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {bookings.map((b) => (
                <tr key={b.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink font-mono text-xs">
                    <Link to={`/bookings/${b.id}`} className="hover:text-teal">
                      {b.booking_number}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-ink-soft">
                    {b.travel_date ? new Date(b.travel_date).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3 text-ink-soft">{b.number_of_travelers}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: b.currency || 'USD' }).format(b.total_amount)}
                  </td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{b.payment_status}</td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[b.status] || 'default'}>{b.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
