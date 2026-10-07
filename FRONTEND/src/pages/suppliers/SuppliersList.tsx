import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Supplier {
  id: number
  name: string
  type: string
  email: string
  phone: string
  commission_rate: number | null
  status: string
}

export default function SuppliersList() {
  const [suppliers, setSuppliers] = useState<Supplier[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState({
    name: '', type: 'hotel', email: '', phone: '', contact_person: '', commission_rate: '',
  })

  function load() {
    setLoading(true)
    api.get('/suppliers').then((res) => setSuppliers(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/suppliers', form)
      setShowForm(false)
      setForm({ name: '', type: 'hotel', email: '', phone: '', contact_person: '', commission_rate: '' })
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
        <h1 className="text-xl font-semibold text-ink">Suppliers</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New supplier
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Name" error={errors.name}>
              <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </Field>
            <Field label="Type" error={errors.type}>
              <select
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.type}
                onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
              >
                <option value="airline">Airline</option>
                <option value="hotel">Hotel</option>
                <option value="transport">Transport</option>
                <option value="visa">Visa</option>
                <option value="guide">Guide</option>
                <option value="other">Other</option>
              </select>
            </Field>
            <Field label="Email" error={errors.email}>
              <Input type="email" required value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
            </Field>
            <Field label="Phone" error={errors.phone}>
              <Input required value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
            </Field>
            <Field label="Contact person" error={errors.contact_person}>
              <Input value={form.contact_person} onChange={(e) => setForm((f) => ({ ...f, contact_person: e.target.value }))} />
            </Field>
            <Field label="Commission rate (%)" error={errors.commission_rate}>
              <Input type="number" step="0.01" value={form.commission_rate} onChange={(e) => setForm((f) => ({ ...f, commission_rate: e.target.value }))} />
            </Field>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save supplier'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : suppliers.length === 0 ? (
          <EmptyState title="No suppliers yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">Commission</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {suppliers.map((s) => (
                <tr key={s.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{s.name}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{s.type}</td>
                  <td className="px-4 py-3 text-ink-soft">{s.email || s.phone}</td>
                  <td className="px-4 py-3 text-ink-soft">{s.commission_rate ?? '—'}%</td>
                  <td className="px-4 py-3">
                    <Badge tone={s.status === 'active' ? 'success' : 'default'}>{s.status}</Badge>
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
