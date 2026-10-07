import { useState, FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card } from '../../components/ui'

export default function VendorForm() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    name: '',
    category: 'other',
    email: '',
    phone: '',
    contact_person: '',
    payment_terms: '',
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
      await api.post('/vendors', form)
      navigate('/vendors')
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
      <h1 className="text-xl font-semibold text-ink mb-6">New vendor</h1>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="Name" error={errors.name}>
            <Input required value={form.name} onChange={(e) => update('name', e.target.value)} />
          </Field>
          <Field label="Category" error={errors.category}>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.category}
              onChange={(e) => update('category', e.target.value)}
            >
              <option value="printing">Printing</option>
              <option value="marketing">Marketing</option>
              <option value="software">Software</option>
              <option value="office_supplies">Office supplies</option>
              <option value="utilities">Utilities</option>
              <option value="other">Other</option>
            </select>
          </Field>
          <Field label="Email" error={errors.email}>
            <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Field label="Phone" error={errors.phone}>
            <Input value={form.phone} onChange={(e) => update('phone', e.target.value)} />
          </Field>
          <Field label="Contact person" error={errors.contact_person}>
            <Input value={form.contact_person} onChange={(e) => update('contact_person', e.target.value)} />
          </Field>
          <Field label="Payment terms" error={errors.payment_terms}>
            <Input value={form.payment_terms} onChange={(e) => update('payment_terms', e.target.value)} />
          </Field>

          <div className="flex gap-2 pt-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save vendor'}
            </Button>
            <Button type="button" variant="secondary" onClick={() => navigate('/vendors')}>
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
