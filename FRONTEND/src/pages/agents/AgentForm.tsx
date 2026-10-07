import { useState, FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card } from '../../components/ui'

export default function AgentForm() {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    agent_code: '',
    name: '',
    email: '',
    phone: '',
    company_name: '',
    commission_type: 'percentage',
    commission_rate: '',
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
      await api.post('/agents', form)
      navigate('/agents')
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
      <h1 className="text-xl font-semibold text-ink mb-6">New agent</h1>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="Agent code" error={errors.agent_code}>
            <Input required value={form.agent_code} onChange={(e) => update('agent_code', e.target.value)} />
          </Field>
          <Field label="Name" error={errors.name}>
            <Input required value={form.name} onChange={(e) => update('name', e.target.value)} />
          </Field>
          <Field label="Email" error={errors.email}>
            <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
          </Field>
          <Field label="Phone" error={errors.phone}>
            <Input required value={form.phone} onChange={(e) => update('phone', e.target.value)} />
          </Field>
          <Field label="Company" error={errors.company_name}>
            <Input value={form.company_name} onChange={(e) => update('company_name', e.target.value)} />
          </Field>
          <Field label="Commission type" error={errors.commission_type}>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.commission_type}
              onChange={(e) => update('commission_type', e.target.value)}
            >
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed amount</option>
            </select>
          </Field>
          <Field label="Commission rate" error={errors.commission_rate}>
            <Input
              type="number"
              step="0.01"
              required
              value={form.commission_rate}
              onChange={(e) => update('commission_rate', e.target.value)}
            />
          </Field>

          <div className="flex gap-2 pt-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save agent'}
            </Button>
            <Button type="button" variant="secondary" onClick={() => navigate('/agents')}>
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
