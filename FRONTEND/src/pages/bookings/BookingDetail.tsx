import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Badge, Spinner, EmptyState } from '../../components/ui'
import { ArrowLeft } from 'lucide-react'
import type { Booking, MahramRecord, InstallmentPlan, Hotel, Flight, TransportOption } from './types'
import TravelersMahramSection from './sections/TravelersMahramSection'
import InstallmentSection from './sections/InstallmentSection'
import HotelSection from './sections/HotelSection'
import FlightSection from './sections/FlightSection'
import TransportSection from './sections/TransportSection'

export default function BookingDetail() {
  const { id } = useParams()
  const [booking, setBooking] = useState<Booking | null>(null)
  const [mahrams, setMahrams] = useState<MahramRecord[]>([])
  const [plans, setPlans] = useState<InstallmentPlan[]>([])
  const [hotels, setHotels] = useState<Hotel[]>([])
  const [flights, setFlights] = useState<Flight[]>([])
  const [transportOptions, setTransportOptions] = useState<TransportOption[]>([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    Promise.all([
      api.get(`/bookings/${id}`),
      api.get(`/bookings/${id}/mahrams`),
      api.get(`/bookings/${id}/installment-plans`),
      api.get('/hotels'),
      api.get('/flights'),
      api.get('/transports'),
    ])
      .then(([bookingRes, mahramRes, planRes, hotelsRes, flightsRes, transportRes]) => {
        setBooking(bookingRes.data.data)
        setMahrams(mahramRes.data.data)
        setPlans(planRes.data.data)
        setHotels(hotelsRes.data.data)
        setFlights(flightsRes.data.data)
        setTransportOptions(transportRes.data.data)
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [id])

  if (loading) return <Spinner />
  if (!booking) return <EmptyState title="Booking not found" />

  return (
    <div className="max-w-3xl">
      <Link to="/bookings" className="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink mb-4">
        <ArrowLeft size={15} /> Back to bookings
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold text-ink font-mono">{booking.booking_number}</h1>
          <p className="text-sm text-ink-soft mt-0.5">
            {booking.number_of_travelers} traveler(s) ·{' '}
            {new Intl.NumberFormat('en-US', { style: 'currency', currency: booking.currency || 'USD' }).format(booking.total_amount)}
          </p>
        </div>
        <Badge tone={booking.status === 'confirmed' ? 'success' : 'default'}>{booking.status}</Badge>
      </div>

      <TravelersMahramSection booking={booking} mahrams={mahrams} onChanged={load} />

      <InstallmentSection bookingId={id} plans={plans} onChanged={load} />

      <HotelSection bookingId={id} booking={booking} hotels={hotels} onChanged={load} />

      <FlightSection bookingId={id} booking={booking} flights={flights} onChanged={load} />

      <TransportSection bookingId={id} booking={booking} transportOptions={transportOptions} onChanged={load} />
    </div>
  )
}
