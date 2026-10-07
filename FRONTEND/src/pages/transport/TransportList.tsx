import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Transport {
  id: number
  transport_type: string
  vehicle_name: string
  vehicle_number: string
  pickup_location: string
  dropoff_location: string
  pickup_date: string
  capacity: number
  price_per_seat: number
  currency: string
  status: string
}

const emptyForm = {
  transport_type: 'bus', vehicle_name: '', vehicle_number: '', pickup_location: '', dropoff_location: '',
  pickup_date: '', pickup_time: '', capacity: '', price_per_seat: '', currency: 'USD',
  driver_name: '', driver_phone: '',
}

export default function TransportList() {
  const [transports, setTransports] = useState<Transport[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState(emptyForm)

  function load() {
    setLoading(true)
    api.get('/transports').then((res) => setTransports(res.data.data)).finally(() => setLoading(false))
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
      await api.post('/transports', form)
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
        <h1 className="text-xl font-semibold text-ink">Transport</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New vehicle
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-xl">
          <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-4">
            <Field label="Type" error={errors.transport_type}>
              <select
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.transport_type}
                onChange={(e) => update('transport_type', e.target.value)}
              >
                <option value="bus">Bus</option>
                <option value="car">Car</option>
                <option value="van">Van</option>
                <option value="coach">Coach</option>
              </select>
            </Field>
            <Field label="Vehicle name" error={errors.vehicle_name}>
              <Input required value={form.vehicle_name} onChange={(e) => update('vehicle_name', e.target.value)} />
            </Field>
            <Field label="Vehicle number" error={errors.vehicle_number}>
              <Input required value={form.vehicle_number} onChange={(e) => update('vehicle_number', e.target.value)} />
            </Field>
            <Field label="Capacity" error={errors.capacity}>
              <Input type="number" min={1} required value={form.capacity} onChange={(e) => update('capacity', e.target.value)} />
            </Field>
            <Field label="Pickup location" error={errors.pickup_location}>
              <Input required value={form.pickup_location} onChange={(e) => update('pickup_location', e.target.value)} />
            </Field>
            <Field label="Dropoff location" error={errors.dropoff_location}>
              <Input required value={form.dropoff_location} onChange={(e) => update('dropoff_location', e.target.value)} />
            </Field>
            <Field label="Pickup date" error={errors.pickup_date}>
              <Input type="date" required value={form.pickup_date} onChange={(e) => update('pickup_date', e.target.value)} />
            </Field>
            <Field label="Pickup time" error={errors.pickup_time}>
              <Input type="time" step={1} required value={form.pickup_time} onChange={(e) => update('pickup_time', e.target.value)} />
            </Field>
            <Field label="Price per seat" error={errors.price_per_seat}>
              <Input type="number" step="0.01" required value={form.price_per_seat} onChange={(e) => update('price_per_seat', e.target.value)} />
            </Field>
            <Field label="Currency" error={errors.currency}>
              <Input required maxLength={3} value={form.currency} onChange={(e) => update('currency', e.target.value.toUpperCase())} />
            </Field>
            <Field label="Driver name" error={errors.driver_name}>
              <Input required value={form.driver_name} onChange={(e) => update('driver_name', e.target.value)} />
            </Field>
            <Field label="Driver phone" error={errors.driver_phone}>
              <Input required value={form.driver_phone} onChange={(e) => update('driver_phone', e.target.value)} />
            </Field>
            <Button type="submit" disabled={submitting} className="col-span-2">
              {submitting ? 'Saving…' : 'Save vehicle'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : transports.length === 0 ? (
          <EmptyState title="No transport vehicles yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Vehicle</th>
                <th className="px-4 py-3 font-medium">Route</th>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Capacity</th>
                <th className="px-4 py-3 font-medium">Price</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {transports.map((t) => (
                <tr key={t.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink capitalize">{t.vehicle_name} <span className="text-ink-soft">({t.transport_type})</span></td>
                  <td className="px-4 py-3 text-ink-soft">{t.pickup_location} → {t.dropoff_location}</td>
                  <td className="px-4 py-3 text-ink-soft">{new Date(t.pickup_date).toLocaleDateString()}</td>
                  <td className="px-4 py-3 text-ink-soft">{t.capacity}</td>
                  <td className="px-4 py-3 text-ink-soft">{t.price_per_seat} {t.currency}</td>
                  <td className="px-4 py-3">
                    <Badge tone={t.status === 'active' ? 'success' : 'default'}>{t.status}</Badge>
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
