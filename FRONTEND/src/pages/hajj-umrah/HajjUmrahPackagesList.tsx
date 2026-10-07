import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Package {
  id: number
  name: string
  duration_days: number
  price: number
  max_capacity: number
  status: string
}

type PackageType = 'hajj' | 'umrah'

export default function HajjUmrahPackagesList() {
  const [type, setType] = useState<PackageType>('hajj')
  const [packages, setPackages] = useState<Package[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState({ name: '', duration_days: '', price: '', max_capacity: '' })
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  function load(t: PackageType) {
    setLoading(true)
    api
      .get(`/${t}`)
      .then((res) => setPackages(res.data.data))
      .finally(() => setLoading(false))
  }

  useEffect(() => load(type), [type])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post(`/${type}`, form)
      setForm({ name: '', duration_days: '', price: '', max_capacity: '' })
      setShowForm(false)
      load(type)
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex gap-1">
          {(['hajj', 'umrah'] as PackageType[]).map((t) => (
            <button
              key={t}
              onClick={() => {
                setType(t)
                setShowForm(false)
              }}
              className={`px-4 py-2 text-sm font-medium capitalize border-b-2 ${
                type === t ? 'border-teal text-ink' : 'border-transparent text-ink-soft hover:text-ink'
              }`}
            >
              {t} packages
            </button>
          ))}
        </div>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New {type} package
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Name" error={errors.name}>
              <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </Field>
            <Field label="Duration (days)" error={errors.duration_days}>
              <Input
                type="number"
                required
                value={form.duration_days}
                onChange={(e) => setForm((f) => ({ ...f, duration_days: e.target.value }))}
              />
            </Field>
            <Field label="Price" error={errors.price}>
              <Input
                type="number"
                step="0.01"
                required
                value={form.price}
                onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
              />
            </Field>
            <Field label="Max capacity" error={errors.max_capacity}>
              <Input
                type="number"
                required
                value={form.max_capacity}
                onChange={(e) => setForm((f) => ({ ...f, max_capacity: e.target.value }))}
              />
            </Field>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : `Save ${type} package`}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : packages.length === 0 ? (
          <EmptyState title={`No ${type} packages yet`} />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Duration</th>
                <th className="px-4 py-3 font-medium">Price</th>
                <th className="px-4 py-3 font-medium">Capacity</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{p.name}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.duration_days} days</td>
                  <td className="px-4 py-3 text-ink-soft">{p.price}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.max_capacity}</td>
                  <td className="px-4 py-3">
                    <Badge tone={p.status === 'active' ? 'success' : 'default'}>{p.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      {/* NOTE: HajjController/UmrahController@index return no pagination
          metadata at all (pre-existing, not introduced here), so there is
          no "next page" control here — this page only ever shows the
          first 20 records. Flagged in FRONTEND_INTEGRATION_BATCH_REPORT.md
          rather than silently worked around. */}
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
