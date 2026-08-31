import { useEffect, useState } from 'react'
import { getSalesDashboard } from '../api/endpoints'
import { ErrorBanner, Loading, NotAvailable, StatCard } from '../components/ui'
import type { SalesDashboardResponse } from '../types'
import { formatCurrency, formatPercent, getErrorMessage } from '../utils/format'

export default function SalesDashboardPage() {
  const [data, setData] = useState<SalesDashboardResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError(null)
      setNotAvailable(false)
      try {
        const res = await getSalesDashboard()
        if (!cancelled) setData(res.data.data)
      } catch (err) {
        if (cancelled) return
        const anyErr = err as { response?: { status?: number } }
        if (anyErr.response?.status === 404) {
          setNotAvailable(true)
        } else {
          setError(getErrorMessage(err, 'Unable to load sales dashboard.'))
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => {
      cancelled = true
    }
  }, [])

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Sales</h1>
        </div>
        <NotAvailable label="Sales dashboard" />
      </div>
    )
  }

  if (loading) return <Loading label="Loading sales dashboard..." />
  if (error) return <ErrorBanner message={error} />
  if (!data) return <ErrorBanner message="No sales data." />

  const topPackages = data.top_packages ?? []
  const maxRevenue = Math.max(1, ...topPackages.map((p) => Number(p.revenue) || 0))

  return (
    <div className="page">
      <div className="page-header">
        <h1>Sales</h1>
      </div>

      <div className="stat-grid">
        <StatCard label="Revenue This Month" value={formatCurrency(data.revenue_this_month)} />
        <StatCard
          label="Quotation Conversion Rate"
          value={
            data.quotation_conversion_rate !== undefined
              ? formatPercent(data.quotation_conversion_rate)
              : '—'
          }
        />
        <StatCard
          label="Invoice Collection Rate"
          value={
            data.invoice_collection_rate !== undefined
              ? formatPercent(data.invoice_collection_rate)
              : '—'
          }
        />
        <StatCard label="Outstanding Amount" value={formatCurrency(data.outstanding_amount)} />
      </div>

      <section className="panel">
        <h2>Top Packages</h2>
        {topPackages.length === 0 ? (
          <p className="muted">No package sales data available.</p>
        ) : (
          <div className="stage-bars">
            {topPackages.map((p, i) => (
              <div className="stage-bar-row" key={p.package_id ?? i}>
                <div className="stage-bar-label">
                  {p.name ?? `Package #${p.package_id}`}
                  {p.count ? ` (${p.count})` : ''}
                </div>
                <div className="stage-bar-track">
                  <div
                    className="stage-bar-fill"
                    style={{ width: `${((Number(p.revenue) || 0) / maxRevenue) * 100}%` }}
                  />
                </div>
                <div className="stage-bar-value">{formatCurrency(p.revenue)}</div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
