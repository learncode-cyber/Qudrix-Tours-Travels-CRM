import { useEffect, useState } from 'react'
import {
  getBehavioralAnalytics,
  getExecutiveDashboard,
  getQuotationFunnel,
} from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, StatCard } from '../components/ui'
import type { BehavioralAnalyticsData, ExecutiveDashboardData } from '../types'
import { formatCurrency, formatDate, titleCase } from '../utils/format'

export default function AnalyticsDashboardPage() {
  const [tab, setTab] = useState<'executive' | 'behavioral' | 'funnel'>('executive')

  return (
    <div className="page">
      <div className="page-header">
        <h1>Analytics</h1>
      </div>
      <div className="view-toggle">
        <button type="button" className={tab === 'executive' ? 'active' : ''} onClick={() => setTab('executive')}>
          Executive
        </button>
        <button type="button" className={tab === 'behavioral' ? 'active' : ''} onClick={() => setTab('behavioral')}>
          Behavioral
        </button>
        <button type="button" className={tab === 'funnel' ? 'active' : ''} onClick={() => setTab('funnel')}>
          Quotation Funnel
        </button>
      </div>
      {tab === 'executive' ? <ExecutiveTab /> : null}
      {tab === 'behavioral' ? <BehavioralTab /> : null}
      {tab === 'funnel' ? <FunnelTab /> : null}
    </div>
  )
}

function ExecutiveTab() {
  const [data, setData] = useState<ExecutiveDashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getExecutiveDashboard()
      .then((res) => setData(res.data.data))
      .catch((err) => {
        const anyErr = err as { response?: { data?: { message?: string } } }
        setError(anyErr.response?.data?.message ?? 'Unable to load the executive dashboard.')
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (!data) return null

  return (
    <>
      <p className="field-hint">
        {formatDate(data.period.from)} &rarr; {formatDate(data.period.to)}
      </p>
      {data.unavailable_metrics.length > 0 ? (
        <div className="state-block error">
          {data.unavailable_metrics.map((m, i) => (
            <div key={i}>{m}</div>
          ))}
        </div>
      ) : null}

      <h2>Revenue</h2>
      <div className="stat-grid">
        <StatCard label="Total Revenue" value={formatCurrency(data.revenue.total_revenue)} />
        <StatCard label="Outstanding" value={formatCurrency(data.revenue.outstanding_amount)} />
        <StatCard label="Pending Payment Invoices" value={data.revenue.pending_payment_invoices} />
      </div>

      <h2>Leads</h2>
      <div className="stat-grid">
        <StatCard label="Total Leads" value={data.leads.total_leads} />
        <StatCard label="Qualified" value={data.leads.qualified_leads} />
        <StatCard label="Won" value={data.leads.won_leads} />
        <StatCard
          label="Conversion Rate"
          value={data.leads.conversion_rate_percent !== null ? `${data.leads.conversion_rate_percent}%` : '—'}
        />
      </div>

      <h2>Operations</h2>
      <div className="stat-grid">
        <StatCard label="Active Bookings" value={data.operations.active_bookings} />
        <StatCard label="Today's Follow-ups" value={data.operations.todays_follow_ups} />
        <StatCard label="Upcoming Departures (30d)" value={data.operations.upcoming_departures_30d} />
        <StatCard label="Visa Applications" value={data.operations.visa_applications} />
        <StatCard label="Flight Bookings" value={data.operations.flight_bookings} />
        <StatCard label="Hotel Bookings" value={data.operations.hotel_bookings} />
        <StatCard label="Hajj/Umrah Pilgrims" value={data.operations.hajj_umrah_pilgrims} />
        <StatCard label="Student Visa Applications" value={data.operations.student_visa_applications} />
      </div>

      <h2>Profit &amp; Loss</h2>
      <div className="stat-grid">
        <StatCard label="Income" value={formatCurrency(data.profit_and_loss.income)} />
        <StatCard label="Expenses" value={formatCurrency(data.profit_and_loss.expenses)} />
        <StatCard label="Net" value={formatCurrency(data.profit_and_loss.net)} />
        <StatCard
          label="Margin"
          value={data.profit_and_loss.margin_percent !== null ? `${data.profit_and_loss.margin_percent}%` : '—'}
        />
      </div>

      <h2>Revenue Trend</h2>
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>Period</th>
              <th>Revenue</th>
              <th>Payments</th>
            </tr>
          </thead>
          <tbody>
            {data.revenue_trend.map((row) => (
              <tr key={row.period}>
                <td>{row.period}</td>
                <td>{formatCurrency(row.revenue)}</td>
                <td>{row.payment_count}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2>Lead Source Performance</h2>
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>Source</th>
              <th>Total</th>
              <th>Won</th>
              <th>Conversion</th>
              <th>Pipeline Value</th>
            </tr>
          </thead>
          <tbody>
            {data.lead_source_performance.map((row) => (
              <tr key={row.source}>
                <td>{titleCase(row.source)}</td>
                <td>{row.total_leads}</td>
                <td>{row.won}</td>
                <td>{row.conversion_rate_percent !== null ? `${row.conversion_rate_percent}%` : '—'}</td>
                <td>{formatCurrency(row.pipeline_value)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2>Staff Performance</h2>
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Leads Assigned</th>
              <th>Leads Won</th>
              <th>Win Rate</th>
              <th>Bookings Created</th>
              <th>Tasks Completed</th>
            </tr>
          </thead>
          <tbody>
            {data.staff_performance.map((row) => (
              <tr key={row.user_id}>
                <td>{row.name}</td>
                <td>{row.leads_assigned}</td>
                <td>{row.leads_won}</td>
                <td>{row.win_rate_percent !== null ? `${row.win_rate_percent}%` : '—'}</td>
                <td>{row.bookings_created}</td>
                <td>{row.tasks_completed}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}

function BehavioralTab() {
  const [data, setData] = useState<BehavioralAnalyticsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getBehavioralAnalytics()
      .then((res) => setData(res.data.data))
      .catch((err) => {
        const anyErr = err as { response?: { data?: { message?: string } } }
        setError(anyErr.response?.data?.message ?? 'Unable to load behavioral analytics.')
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (!data) return null

  return (
    <>
      <p className="field-hint">
        {formatDate(data.period.from)} &rarr; {formatDate(data.period.to)}
      </p>

      <h2>Time to Conversion</h2>
      <div className="stat-grid">
        <StatCard label="Converted Leads" value={data.time_to_conversion.converted_leads} />
        <StatCard label="Average Days" value={data.time_to_conversion.average_days ?? '—'} />
        <StatCard label="Fastest" value={data.time_to_conversion.fastest_days ?? '—'} />
        <StatCard label="Slowest" value={data.time_to_conversion.slowest_days ?? '—'} />
      </div>

      <h2>Deal Value</h2>
      <div className="stat-grid">
        <StatCard label="Bookings" value={data.deal_value.bookings} />
        <StatCard
          label="Average Value"
          value={data.deal_value.average_value !== null ? formatCurrency(data.deal_value.average_value) : '—'}
        />
        <StatCard label="Total Value" value={formatCurrency(data.deal_value.total_value)} />
      </div>

      <h2>Follow-up Effectiveness</h2>
      <div className="stat-grid">
        <StatCard label="Leads Contacted" value={data.follow_up_effectiveness.leads_contacted} />
        <StatCard label="Contacted &amp; Won" value={data.follow_up_effectiveness.contacted_and_won} />
        <StatCard
          label="Win Rate"
          value={
            data.follow_up_effectiveness.win_rate_percent !== null
              ? `${data.follow_up_effectiveness.win_rate_percent}%`
              : '—'
          }
        />
      </div>

      <h2>Customer Base</h2>
      <div className="stat-grid">
        <StatCard label="Total Customers" value={data.customer_base.total_customers} />
        <StatCard label="Repeat Customers" value={data.customer_base.repeat_customers} />
      </div>

      <h2>Engagement by Channel</h2>
      {data.engagement_by_channel.length === 0 ? (
        <EmptyState message="No communications recorded in this period." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Channel</th>
                <th>Messages</th>
                <th>Read</th>
                <th>Read Rate</th>
              </tr>
            </thead>
            <tbody>
              {data.engagement_by_channel.map((row) => (
                <tr key={row.channel}>
                  <td>{titleCase(row.channel)}</td>
                  <td>{row.messages}</td>
                  <td>{row.read}</td>
                  <td>{row.read_rate_percent !== null ? `${row.read_rate_percent}%` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}

function FunnelTab() {
  const [rows, setRows] = useState<{ status: string; count: number; value: number }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getQuotationFunnel()
      .then((res) => setRows(res.data.data ?? []))
      .catch((err) => {
        const anyErr = err as { response?: { data?: { message?: string } } }
        setError(anyErr.response?.data?.message ?? 'Unable to load the quotation funnel.')
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (rows.length === 0) return <EmptyState message="No quotations in the current period." />

  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Count</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.status}>
              <td>{titleCase(row.status)}</td>
              <td>{row.count}</td>
              <td>{formatCurrency(row.value)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
