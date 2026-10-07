import { useState, FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card } from '../../components/ui'

export default function PaymentForm() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [form, setForm] = useState({
    invoice_id: searchParams.get('invoice_id') || '',
    amount: '',
    payment_method: 'bank_transfer',
    reference_number: '',
    status: 'completed',
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
      await api.post('/payments', {
        ...form,
        invoice_id: form.invoice_id ? Number(form.invoice_id) : undefined,
        amount: Number(form.amount),
      })
      navigate('/payments')
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
      <h1 className="text-xl font-semibold text-ink mb-6">Record payment</h1>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="Invoice ID" error={errors.invoice_id}>
            <Input
              type="number"
              required
              value={form.invoice_id}
              onChange={(e) => update('invoice_id', e.target.value)}
            />
          </Field>
          <Field label="Amount" error={errors.amount}>
            <Input type="number" step="0.01" min="0.01" required value={form.amount} onChange={(e) => update('amount', e.target.value)} />
          </Field>
          <Field label="Payment method" error={errors.payment_method}>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.payment_method}
              onChange={(e) => update('payment_method', e.target.value)}
            >
              <option value="bank_transfer">Bank transfer</option>
              <option value="credit_card">Credit card</option>
              <option value="cash">Cash</option>
              <option value="refund">Refund</option>
            </select>
          </Field>
          <Field label="Reference number" error={errors.reference_number}>
            <Input value={form.reference_number} onChange={(e) => update('reference_number', e.target.value)} />
          </Field>
          <Field label="Status" error={errors.status}>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.status}
              onChange={(e) => update('status', e.target.value)}
            >
              <option value="pending">Pending</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </Field>

          <div className="flex gap-2 pt-2">
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save payment'}
            </Button>
            <Button type="button" variant="secondary" onClick={() => navigate('/payments')}>
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
