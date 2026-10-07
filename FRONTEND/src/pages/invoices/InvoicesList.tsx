import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, Button, EmptyState, Spinner } from '../../components/ui'

interface Invoice {
  id: number
  invoice_number: string
  status: string
  total_amount: number
  amount_paid: number
  currency: string
  due_date: string | null
  customer: { name: string } | null
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  draft: 'default',
  sent: 'warn',
  partially_paid: 'warn',
  paid: 'success',
  overdue: 'danger',
  void: 'default',
}

export default function InvoicesList() {
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  function load() {
    setLoading(true)
    api
      .get('/invoices')
      .then((res) => setInvoices(res.data.data))
      .catch(() => setError('Could not load invoices.'))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleVoid(id: number) {
    setActionError(null)
    try {
      await api.post(`/invoices/${id}/void`)
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || 'Could not void this invoice.')
    }
  }

  function fmt(amount: number, currency: string) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD' }).format(amount)
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Invoices</h1>

      {actionError && (
        <p className="mb-4 text-sm text-danger bg-danger/10 border border-danger/30 px-3 py-2">{actionError}</p>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : invoices.length === 0 ? (
          <EmptyState title="No invoices yet" description="Invoices generated from signed proposals will appear here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Number</th>
                <th className="px-4 py-3 font-medium">Customer</th>
                <th className="px-4 py-3 font-medium">Total</th>
                <th className="px-4 py-3 font-medium">Balance due</th>
                <th className="px-4 py-3 font-medium">Due date</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {invoices.map((inv) => (
                <tr key={inv.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-mono text-xs text-ink">{inv.invoice_number}</td>
                  <td className="px-4 py-3 text-ink-soft">{inv.customer?.name || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">{fmt(inv.total_amount, inv.currency)}</td>
                  <td className="px-4 py-3 text-ink-soft">{fmt(inv.total_amount - inv.amount_paid, inv.currency)}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {inv.due_date ? new Date(inv.due_date).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[inv.status] || 'default'}>{inv.status}</Badge>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Link to={`/payments/new?invoice_id=${inv.id}`}>
                        <Button variant="secondary" className="text-xs px-2 py-1">
                          Record payment
                        </Button>
                      </Link>
                      {inv.amount_paid === 0 && inv.status !== 'void' && (
                        <Button variant="ghost" className="text-xs px-2 py-1" onClick={() => handleVoid(inv.id)}>
                          Void
                        </Button>
                      )}
                    </div>
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
