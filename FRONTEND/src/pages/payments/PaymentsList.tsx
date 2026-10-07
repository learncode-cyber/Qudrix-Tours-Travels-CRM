import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, Button, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Payment {
  id: number
  amount: number
  payment_method: string
  status: string
  paid_at: string | null
  invoice: { invoice_number: string } | null
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  pending: 'warn',
  completed: 'success',
  failed: 'danger',
  refunded: 'default',
}

export default function PaymentsList() {
  const [payments, setPayments] = useState<Payment[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get('/payments')
      .then((res) => setPayments(res.data.data))
      .catch(() => setError('Could not load payments.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Payments</h1>
        <Link to="/payments/new">
          <Button>
            <Plus size={16} /> Record payment
          </Button>
        </Link>
      </div>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : payments.length === 0 ? (
          <EmptyState title="No payments recorded yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Invoice</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-4 py-3 font-medium">Method</th>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-mono text-xs text-ink">{p.invoice?.invoice_number || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.amount}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{p.payment_method}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {p.paid_at ? new Date(p.paid_at).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[p.status] || 'default'}>{p.status}</Badge>
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
