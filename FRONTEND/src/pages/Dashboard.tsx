import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { Card, Spinner } from '../components/ui'

interface Kpi {
  total_bookings: number
  total_revenue: number
  total_customers: number
  total_leads: number
  avg_booking_value: number
  customer_satisfaction: number | null
  occupancy_rate: number | null
  unavailable_metrics?: Record<string, string>
}

export default function Dashboard() {
  const [kpi, setKpi] = useState<Kpi | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api
      .get('/dashboard/kpi')
      .then((res) => setKpi(res.data.data))
      .catch(() => setError('Could not load dashboard data.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Spinner />
  if (error) return <p className="text-sm text-danger">{error}</p>
  if (!kpi) return null

  const stats = [
    { label: 'Total bookings', value: kpi.total_bookings },
    { label: 'Total revenue', value: formatCurrency(kpi.total_revenue) },
    { label: 'Customers', value: kpi.total_customers },
    { label: 'Leads', value: kpi.total_leads },
    { label: 'Avg. booking value', value: formatCurrency(kpi.avg_booking_value) },
  ]

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Dashboard</h1>

      <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        {stats.map((s) => (
          <Card key={s.label} className="p-4 border-t-2 border-t-teal">
            <p className="text-xs text-ink-soft">{s.label}</p>
            <p className="text-2xl font-semibold text-ink mt-1">{s.value}</p>
          </Card>
        ))}
      </div>

      {kpi.unavailable_metrics && Object.keys(kpi.unavailable_metrics).length > 0 && (
        <Card className="p-4 bg-canvas/50">
          <p className="text-xs font-medium text-ink-soft mb-2">Not yet available</p>
          <ul className="text-sm text-ink-soft space-y-1">
            {Object.entries(kpi.unavailable_metrics).map(([key, reason]) => (
              <li key={key}>
                <span className="font-medium">{key.replace(/_/g, ' ')}:</span> {reason}
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  )
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}
