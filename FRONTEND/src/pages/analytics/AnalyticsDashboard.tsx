import { useEffect, useState } from 'react'
import { api } from '../../lib/api'
import { Card, Spinner } from '../../components/ui'

interface Kpis {
  total_bookings: number
  total_revenue: number
  total_customers: number
  total_leads: number
  avg_booking_value: number
  customer_satisfaction: number | null
  occupancy_rate: number | null
  unavailable_metrics: Record<string, string>
}

interface FunnelData {
  counts: Record<string, number>
  dropoff: { from: string; to: string; rate: number | null }[]
}

interface ConversionData {
  won: number
  lost: number
  overall_conversion_rate: number | null
}

interface RevenueData {
  daily_revenue: { day: string; total: string }[]
  total_in_period: number
}

export default function AnalyticsDashboard() {
  const [kpis, setKpis] = useState<Kpis | null>(null)
  const [funnel, setFunnel] = useState<FunnelData | null>(null)
  const [conversion, setConversion] = useState<ConversionData | null>(null)
  const [revenue, setRevenue] = useState<RevenueData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    Promise.all([
      api.get('/dashboard/kpi'),
      api.get('/analytics/lead-funnel'),
      api.get('/analytics/conversion-rate'),
      api.get('/analytics/revenue', { params: { days: 30 } }),
    ])
      .then(([kpiRes, funnelRes, convRes, revRes]) => {
        setKpis(kpiRes.data.data)
        setFunnel(funnelRes.data.data)
        setConversion(convRes.data.data)
        setRevenue(revRes.data.data)
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Spinner />
  if (!kpis) return null

  const funnelMax = funnel ? Math.max(...Object.values(funnel.counts), 1) : 1
  const revenueMax = revenue ? Math.max(...revenue.daily_revenue.map((d) => Number(d.total)), 1) : 1

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Analytics</h1>

      <div className="grid grid-cols-5 gap-4 mb-6">
        <KpiCard label="Bookings" value={kpis.total_bookings} />
        <KpiCard label="Revenue" value={kpis.total_revenue.toLocaleString()} />
        <KpiCard label="Customers" value={kpis.total_customers} />
        <KpiCard label="Leads" value={kpis.total_leads} />
        <KpiCard label="Avg booking value" value={kpis.avg_booking_value.toLocaleString()} />
      </div>

      {Object.keys(kpis.unavailable_metrics || {}).length > 0 && (
        <p className="text-xs text-ink-soft mb-6">
          Not available yet: {Object.entries(kpis.unavailable_metrics).map(([k, v]) => `${k.replace(/_/g, ' ')} (${v})`).join('; ')}
        </p>
      )}

      <div className="grid grid-cols-2 gap-6">
        <Card className="p-5">
          <h2 className="text-sm font-medium text-ink mb-4">Lead funnel</h2>
          {funnel && (
            <div className="space-y-2">
              {Object.entries(funnel.counts).map(([stage, count]) => (
                <div key={stage}>
                  <div className="flex justify-between text-xs text-ink-soft mb-0.5 capitalize">
                    <span>{stage}</span>
                    <span>{count}</span>
                  </div>
                  <div className="h-2 bg-canvas">
                    <div className="h-2 bg-teal" style={{ width: `${(count / funnelMax) * 100}%` }} />
                  </div>
                </div>
              ))}
            </div>
          )}
          {conversion && (
            <p className="text-xs text-ink-soft mt-4 pt-4 border-t border-line">
              Overall conversion: {conversion.overall_conversion_rate ?? 'n/a'}% ({conversion.won} won / {conversion.lost} lost)
            </p>
          )}
        </Card>

        <Card className="p-5">
          <h2 className="text-sm font-medium text-ink mb-4">Revenue — last 30 days</h2>
          {revenue && revenue.daily_revenue.length === 0 ? (
            <p className="text-sm text-ink-soft">No completed payments in this period.</p>
          ) : (
            <>
              <div className="flex items-end gap-1 h-32 mb-3">
                {revenue?.daily_revenue.map((d) => (
                  <div
                    key={d.day}
                    title={`${d.day}: ${d.total}`}
                    className="flex-1 bg-teal/70 hover:bg-teal"
                    style={{ height: `${(Number(d.total) / revenueMax) * 100}%` }}
                  />
                ))}
              </div>
              <p className="text-xs text-ink-soft">Total in period: {revenue?.total_in_period.toLocaleString()}</p>
            </>
          )}
        </Card>
      </div>
    </div>
  )
}

function KpiCard({ label, value }: { label: string; value: string | number }) {
  return (
    <Card className="p-4">
      <p className="text-xs text-ink-soft">{label}</p>
      <p className="text-lg font-semibold text-ink">{value}</p>
    </Card>
  )
}
