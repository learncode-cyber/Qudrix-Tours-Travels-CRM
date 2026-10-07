import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, Spinner, EmptyState } from '../../components/ui'
import { ArrowLeft } from 'lucide-react'

interface Customer {
  id: number
  name: string
  email: string | null
  phone: string | null
  customer_type: string
  status: string
  country: string | null
}

interface TimelineEvent {
  type: string
  subtype: string | null
  summary: string
  occurred_at: string
  ref_id: number
}

export default function CustomerDetail() {
  const { id } = useParams()
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [timeline, setTimeline] = useState<TimelineEvent[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([
      api.get(`/customers/${id}`),
      api.get(`/customers/${id}/timeline`),
    ])
      .then(([customerRes, timelineRes]) => {
        setCustomer(customerRes.data.data ?? customerRes.data)
        setTimeline(timelineRes.data.data)
      })
      .finally(() => setLoading(false))
  }, [id])

  if (loading) return <Spinner />
  if (!customer) return <EmptyState title="Customer not found" />

  return (
    <div className="max-w-2xl">
      <Link to="/customers" className="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink mb-4">
        <ArrowLeft size={15} /> Back to customers
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold text-ink">{customer.name}</h1>
          <p className="text-sm text-ink-soft mt-0.5">{customer.email || customer.phone || 'No contact info'}</p>
        </div>
        <Badge tone={customer.status === 'active' ? 'success' : 'default'}>{customer.status}</Badge>
      </div>

      <Card className="p-5">
        <h2 className="text-sm font-medium text-ink mb-4">Timeline</h2>
        {timeline.length === 0 ? (
          <EmptyState title="No activity yet" description="Quotations, bookings, and payments will appear here." />
        ) : (
          <ol className="space-y-4">
            {timeline.map((event, i) => (
              <li key={i} className="flex gap-3 text-sm">
                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-teal" />
                <div>
                  <p className="text-ink">
                    <span className="font-medium capitalize">{event.type}</span>
                    {event.subtype && <span className="text-ink-soft"> · {event.subtype}</span>}
                  </p>
                  <p className="text-ink-soft">{event.summary}</p>
                  <p className="text-xs text-ink-soft/70 mt-0.5">{new Date(event.occurred_at).toLocaleString()}</p>
                </div>
              </li>
            ))}
          </ol>
        )}
      </Card>
    </div>
  )
}
