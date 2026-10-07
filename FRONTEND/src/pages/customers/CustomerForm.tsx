import { useState, FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card } from '../../components/ui'

export default function CustomerForm() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    customer_type: 'individual',
    country: '',
  })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [submitting, setSubmitting] = useState(false)

  function update(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/customers', form)
      navigate('/customers')
    } catch (err: any) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors || {})
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold text-ink mb-6">New customer</h1>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="Name" error={errors.name}>
            <Input required value={form.name} onChange={(e) => update('name', e.target.value)} />
          </Field>
          <Field label="Email" error={errors.email}>
            <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Field label="Phone" error={errors.phone}>
            <Input value={form.phone} onChange={(e) => update('phone', e.target.value)} />
          </Field>
          <Field label="Type" error={errors.customer_type}>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.customer_type}
              onChange={(e) => update('customer_type', e.target.value)}
            >
              <option value="individual">Individual</option>
              <option value="corporate">Corporate</option>
              <option value="group">Group</option>
            </select>
          </Field>
          <Field label="Country" error={errors.country}>
            <Input value={form.country} onChange={(e) => update('country', e.target.value)} />
          </Field>

          <div className="flex gap-2 pt-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save customer'}
            </Button>
            <Button type="button" variant="secondary" onClick={() => navigate('/customers')}>
              Cancel
            </Button>
          </div>
        </form>
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
