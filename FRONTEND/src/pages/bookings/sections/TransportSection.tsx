import { useState, FormEvent } from 'react'
import { api } from '../../../lib/api'
import { Card, Badge, EmptyState, Button } from '../../../components/ui'
import type { Booking, TransportOption } from '../types'

interface Props {
  bookingId: string | undefined
  booking: Booking
  transportOptions: TransportOption[]
  onChanged: () => void
}

export default function TransportSection({ bookingId, booking, transportOptions, onChanged }: Props) {
  const [transportForm, setTransportForm] = useState({ transportId: '', travelerIds: [] as number[] })
  const [bookingTransport, setBookingTransport] = useState(false)
  const [transportError, setTransportError] = useState<string | null>(null)

  function toggleTravelerForTransport(travelerId: number) {
    setTransportForm((f) => ({
      ...f,
      travelerIds: f.travelerIds.includes(travelerId) ? f.travelerIds.filter((t) => t !== travelerId) : [...f.travelerIds, travelerId],
    }))
  }

  async function handleBookTransport(e: FormEvent) {
    e.preventDefault()
    setBookingTransport(true)
    setTransportError(null)
    try {
      await api.post('/transports/book', { transport_id: transportForm.transportId, booking_id: bookingId, travelers: transportForm.travelerIds })
      setTransportForm({ transportId: '', travelerIds: [] })
      onChanged()
    } catch (err: any) {
      setTransportError(err.response?.data?.error || 'Could not book transport.')
    } finally {
      setBookingTransport(false)
    }
  }

  return (
      <Card className="p-5 mt-6">
        <h2 className="text-sm font-medium text-ink mb-4">Transport</h2>
        {(!booking.transportBookings || booking.transportBookings.length === 0) ? (
          <EmptyState title="No transport booked for this trip yet" />
        ) : (
          <ul className="space-y-2 mb-5">
            {booking.transportBookings.map((tb) => (
              <li key={tb.id} className="text-sm flex items-center justify-between border-b border-line last:border-0 pb-2 last:pb-0">
                <span className="text-ink capitalize">
                  {tb.transport.vehicle_name} ({tb.transport.transport_type}) — {tb.traveler.first_name} {tb.traveler.last_name}
                </span>
                <Badge tone={tb.status === 'booked' ? 'success' : 'default'}>{tb.status}</Badge>
              </li>
            ))}
          </ul>
        )}

        <form onSubmit={handleBookTransport} className="border-t border-line pt-4">
          <select
            required
            className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal mb-3"
            value={transportForm.transportId}
            onChange={(e) => setTransportForm((f) => ({ ...f, transportId: e.target.value }))}
          >
            <option value="">Select vehicle…</option>
            {transportOptions.map((t) => (
              <option key={t.id} value={t.id}>
                {t.vehicle_name} ({t.transport_type}) — {t.pickup_location} → {t.dropoff_location}
              </option>
            ))}
          </select>
          <div className="flex flex-wrap gap-3 mb-3 text-sm">
            {booking.travelers.map((t) => (
              <label key={t.id} className="flex items-center gap-1 text-ink-soft">
                <input type="checkbox" checked={transportForm.travelerIds.includes(t.id)} onChange={() => toggleTravelerForTransport(t.id)} />
                {t.first_name}
              </label>
            ))}
          </div>
          <Button type="submit" disabled={bookingTransport || transportForm.travelerIds.length === 0}>
            {bookingTransport ? 'Booking…' : 'Book transport'}
          </Button>
          {transportError && <p className="mt-2 text-xs text-danger">{transportError}</p>}
        </form>
      </Card>
  )
}
