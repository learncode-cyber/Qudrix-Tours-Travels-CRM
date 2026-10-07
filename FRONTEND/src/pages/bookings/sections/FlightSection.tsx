import { useState, FormEvent } from 'react'
import { api } from '../../../lib/api'
import { Card, Badge, EmptyState, Button } from '../../../components/ui'
import type { Booking, Flight } from '../types'

interface Props {
  bookingId: string | undefined
  booking: Booking
  flights: Flight[]
  onChanged: () => void
}

export default function FlightSection({ bookingId, booking, flights, onChanged }: Props) {
  const [flightForm, setFlightForm] = useState({ flightId: '', travelerIds: [] as number[] })
  const [bookingFlight, setBookingFlight] = useState(false)
  const [flightError, setFlightError] = useState<string | null>(null)

  function toggleTravelerForFlight(travelerId: number) {
    setFlightForm((f) => ({
      ...f,
      travelerIds: f.travelerIds.includes(travelerId) ? f.travelerIds.filter((t) => t !== travelerId) : [...f.travelerIds, travelerId],
    }))
  }

  async function handleBookFlight(e: FormEvent) {
    e.preventDefault()
    setBookingFlight(true)
    setFlightError(null)
    try {
      await api.post('/flights/book', { flight_id: flightForm.flightId, booking_id: bookingId, travelers: flightForm.travelerIds })
      setFlightForm({ flightId: '', travelerIds: [] })
      onChanged()
    } catch (err: any) {
      setFlightError(err.response?.data?.error || 'Could not book flight.')
    } finally {
      setBookingFlight(false)
    }
  }

  return (
      <Card className="p-5 mt-6">
        <h2 className="text-sm font-medium text-ink mb-4">Flights</h2>
        {(!booking.flightBookings || booking.flightBookings.length === 0) ? (
          <EmptyState title="No flight booked for this trip yet" />
        ) : (
          <ul className="space-y-2 mb-5">
            {booking.flightBookings.map((fb) => (
              <li key={fb.id} className="text-sm flex items-center justify-between border-b border-line last:border-0 pb-2 last:pb-0">
                <span className="text-ink">
                  {fb.flight.airline_code}{fb.flight.flight_number} — {fb.flight.departure_airport} → {fb.flight.arrival_airport} —{' '}
                  {fb.traveler.first_name} {fb.traveler.last_name} (seat {fb.seat_number})
                </span>
                <Badge tone={fb.status === 'booked' ? 'success' : 'default'}>{fb.status}</Badge>
              </li>
            ))}
          </ul>
        )}

        <form onSubmit={handleBookFlight} className="border-t border-line pt-4">
          <select
            required
            className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal mb-3"
            value={flightForm.flightId}
            onChange={(e) => setFlightForm((f) => ({ ...f, flightId: e.target.value }))}
          >
            <option value="">Select flight…</option>
            {flights.map((fl) => (
              <option key={fl.id} value={fl.id}>
                {fl.airline_code}{fl.flight_number} — {fl.departure_airport} → {fl.arrival_airport} ({new Date(fl.departure_date).toLocaleDateString()}) — {fl.available_seats} seats left
              </option>
            ))}
          </select>
          <div className="flex flex-wrap gap-3 mb-3 text-sm">
            {booking.travelers.map((t) => (
              <label key={t.id} className="flex items-center gap-1 text-ink-soft">
                <input type="checkbox" checked={flightForm.travelerIds.includes(t.id)} onChange={() => toggleTravelerForFlight(t.id)} />
                {t.first_name}
              </label>
            ))}
          </div>
          <Button type="submit" disabled={bookingFlight || flightForm.travelerIds.length === 0}>
            {bookingFlight ? 'Booking…' : 'Book flight'}
          </Button>
          {flightError && <p className="mt-2 text-xs text-danger">{flightError}</p>}
        </form>
      </Card>
  )
}
