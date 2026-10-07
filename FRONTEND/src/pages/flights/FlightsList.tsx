import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Flight {
  id: number
  airline_code: string
  flight_number: string
  departure_airport: string
  arrival_airport: string
  departure_date: string
  available_seats: number
  total_seats: number
  price_per_seat: number
  currency: string
  status: string
}

const emptyForm = {
  airline_code: '', flight_number: '', departure_airport: '', arrival_airport: '',
  departure_date: '', arrival_date: '', departure_time: '', arrival_time: '',
  aircraft_type: '', total_seats: '', price_per_seat: '', currency: 'USD',
}

export default function FlightsList() {
  const [flights, setFlights] = useState<Flight[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState(emptyForm)

  function load() {
    setLoading(true)
    api.get('/flights').then((res) => setFlights(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  function update(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/flights', form)
      setShowForm(false)
      setForm(emptyForm)
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Flights</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New flight
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-xl">
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
            <Field label="Airline code" error={errors.airline_code}>
              <Input required value={form.airline_code} onChange={(e) => update('airline_code', e.target.value)} />
            </Field>
            <Field label="Flight number" error={errors.flight_number}>
              <Input required value={form.flight_number} onChange={(e) => update('flight_number', e.target.value)} />
            </Field>
            <Field label="Departure airport (IATA)" error={errors.departure_airport}>
              <Input required maxLength={3} value={form.departure_airport} onChange={(e) => update('departure_airport', e.target.value.toUpperCase())} />
            </Field>
            <Field label="Arrival airport (IATA)" error={errors.arrival_airport}>
              <Input required maxLength={3} value={form.arrival_airport} onChange={(e) => update('arrival_airport', e.target.value.toUpperCase())} />
            </Field>
            <Field label="Departure date" error={errors.departure_date}>
              <Input type="date" required value={form.departure_date} onChange={(e) => update('departure_date', e.target.value)} />
            </Field>
            <Field label="Arrival date" error={errors.arrival_date}>
              <Input type="date" required value={form.arrival_date} onChange={(e) => update('arrival_date', e.target.value)} />
            </Field>
            <Field label="Departure time" error={errors.departure_time}>
              <Input type="time" step={1} required value={form.departure_time} onChange={(e) => update('departure_time', e.target.value)} />
            </Field>
            <Field label="Arrival time" error={errors.arrival_time}>
              <Input type="time" step={1} required value={form.arrival_time} onChange={(e) => update('arrival_time', e.target.value)} />
            </Field>
            <Field label="Aircraft type" error={errors.aircraft_type}>
              <Input required value={form.aircraft_type} onChange={(e) => update('aircraft_type', e.target.value)} />
            </Field>
            <Field label="Total seats" error={errors.total_seats}>
              <Input type="number" min={1} required value={form.total_seats} onChange={(e) => update('total_seats', e.target.value)} />
            </Field>
            <Field label="Price per seat" error={errors.price_per_seat}>
              <Input type="number" step="0.01" required value={form.price_per_seat} onChange={(e) => update('price_per_seat', e.target.value)} />
            </Field>
            <Field label="Currency" error={errors.currency}>
              <Input required maxLength={3} value={form.currency} onChange={(e) => update('currency', e.target.value.toUpperCase())} />
            </Field>
            <Button type="submit" disabled={submitting} className="col-span-2">
              {submitting ? 'Saving…' : 'Save flight'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : flights.length === 0 ? (
          <EmptyState title="No flights yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Flight</th>
                <th className="px-4 py-3 font-medium">Route</th>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Seats</th>
                <th className="px-4 py-3 font-medium">Price</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {flights.map((f) => (
                <tr key={f.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{f.airline_code}{f.flight_number}</td>
                  <td className="px-4 py-3 text-ink-soft">{f.departure_airport} → {f.arrival_airport}</td>
                  <td className="px-4 py-3 text-ink-soft">{new Date(f.departure_date).toLocaleDateString()}</td>
                  <td className="px-4 py-3 text-ink-soft">{f.available_seats} / {f.total_seats}</td>
                  <td className="px-4 py-3 text-ink-soft">{f.price_per_seat} {f.currency}</td>
                  <td className="px-4 py-3">
                    <Badge tone={f.status === 'active' ? 'success' : 'default'}>{f.status}</Badge>
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

function Field({ label, error, children }: { label: string; error?: string[]; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-ink mb-1">{label}</label>
      {children}
      {error && <p className="mt-1 text-xs text-danger">{error[0]}</p>}
    </div>
  )
}
