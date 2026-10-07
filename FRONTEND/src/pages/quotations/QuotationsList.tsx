import { useEffect, useState } from 'react'
import { api } from '../../lib/api'
import { Card, Badge, EmptyState, Spinner } from '../../components/ui'

interface Quotation {
  id: number
  quotation_number: string
  subject: string | null
  status: string
  total_amount: number
  currency: string
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  draft: 'default',
  sent: 'warn',
  accepted: 'success',
  rejected: 'danger',
}

export default function QuotationsList() {
  const [quotations, setQuotations] = useState<Quotation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get('/quotations')
      .then((res) => setQuotations(res.data.data))
      .catch(() => setError('Could not load quotations.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Quotations</h1>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : quotations.length === 0 ? (
          <EmptyState title="No quotations yet" description="Quotations sent to leads and customers will appear here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Number</th>
                <th className="px-4 py-3 font-medium">Subject</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {quotations.map((q) => (
                <tr key={q.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink font-mono text-xs">{q.quotation_number}</td>
                  <td className="px-4 py-3 text-ink-soft">{q.subject || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: q.currency || 'USD' }).format(q.total_amount)}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[q.status] || 'default'}>{q.status}</Badge>
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
