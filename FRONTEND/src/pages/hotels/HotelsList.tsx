import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Hotel {
  id: number
  name: string
  city: string
  country: string
  star_rating: number
  available_rooms: number
  total_rooms: number
  price_per_night: number
  currency: string
  status: string
}

export default function HotelsList() {
  const [hotels, setHotels] = useState<Hotel[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState({
    name: '', city: '', country: '', address: '', phone: '', email: '',
    star_rating: '4', total_rooms: '', price_per_night: '', currency: 'USD',
  })

  function load() {
    setLoading(true)
    api.get('/hotels').then((res) => setHotels(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/hotels', form)
      setShowForm(false)
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  function update(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Hotels</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New hotel
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Name" error={errors.name}>
              <Input required value={form.name} onChange={(e) => update('name', e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="City" error={errors.city}>
                <Input required value={form.city} onChange={(e) => update('city', e.target.value)} />
              </Field>
              <Field label="Country" error={errors.country}>
                <Input required value={form.country} onChange={(e) => update('country', e.target.value)} />
              </Field>
            </div>
            <Field label="Address" error={errors.address}>
              <Input required value={form.address} onChange={(e) => update('address', e.target.value)} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Phone" error={errors.phone}>
                <Input required value={form.phone} onChange={(e) => update('phone', e.target.value)} />
              </Field>
              <Field label="Email" error={errors.email}>
                <Input type="email" required value={form.email} onChange={(e) => update('email', e.target.value)} />
              </Field>
            </div>
            <div className="grid grid-cols-3 gap-3">
              <Field label="Star rating" error={errors.star_rating}>
                <Input type="number" min={1} max={5} required value={form.star_rating} onChange={(e) => update('star_rating', e.target.value)} />
              </Field>
              <Field label="Total rooms" error={errors.total_rooms}>
                <Input type="number" min={1} required value={form.total_rooms} onChange={(e) => update('total_rooms', e.target.value)} />
              </Field>
              <Field label="Price/night" error={errors.price_per_night}>
                <Input type="number" step="0.01" required value={form.price_per_night} onChange={(e) => update('price_per_night', e.target.value)} />
              </Field>
            </div>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save hotel'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : hotels.length === 0 ? (
          <EmptyState title="No hotels yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Location</th>
                <th className="px-4 py-3 font-medium">Rating</th>
                <th className="px-4 py-3 font-medium">Rooms available</th>
                <th className="px-4 py-3 font-medium">Price/night</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {hotels.map((h) => (
                <tr key={h.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{h.name}</td>
                  <td className="px-4 py-3 text-ink-soft">{h.city}, {h.country}</td>
                  <td className="px-4 py-3 text-ink-soft">{'★'.repeat(h.star_rating)}</td>
                  <td className="px-4 py-3 text-ink-soft">{h.available_rooms} / {h.total_rooms}</td>
                  <td className="px-4 py-3 text-ink-soft">{h.price_per_night} {h.currency}</td>
                  <td className="px-4 py-3">
                    <Badge tone={h.status === 'active' ? 'success' : 'default'}>{h.status}</Badge>
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
