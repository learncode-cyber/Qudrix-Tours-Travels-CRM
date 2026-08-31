import { useEffect, useState } from 'react'
import { getCrmDashboard, getFullPipeline, getTaskStats } from '../api/endpoints'
import { ErrorBanner, Loading, StatCard } from '../components/ui'
import type { CrmDashboardResponse, Lead, PipelineColumn } from '../types'
import { formatCurrency, formatPercent, titleCase } from '../utils/format'

interface DashboardData {
  totalLeads?: number
  conversionRate?: number
  pipelineValue?: number
  tasksDueToday?: number
  pipelineByStage: { stage: string; value: number; count: number }[]
  source: 'dashboard' | 'fallback'
}

function isPipelineColumn(item: unknown): item is PipelineColumn {
  return !!item && typeof item === 'object' && 'status' in item
}

async function loadFallbackDashboard(): Promise<DashboardData> {
  const [pipelineRes, taskStatsRes] = await Promise.all([
    getFullPipeline(),
    getTaskStats().catch(() => ({ data: {} })),
  ])

  const raw = pipelineRes.data.data
  const byStage: { stage: string; value: number; count: number }[] = []
  let totalLeads = 0

  if (Array.isArray(raw) && raw.length > 0 && isPipelineColumn(raw[0])) {
    for (const col of raw as PipelineColumn[]) {
      const leads = col.leads ?? []
      const value = leads.reduce((sum, l) => sum + (Number(l.estimated_value) || 0), 0)
      byStage.push({ stage: col.status, value, count: leads.length })
      totalLeads += leads.length
    }
  } else if (Array.isArray(raw)) {
    const grouped = new Map<string, { value: number; count: number }>()
    for (const lead of raw as Lead[]) {
      const entry = grouped.get(lead.status) ?? { value: 0, count: 0 }
      entry.value += Number(lead.estimated_value) || 0
      entry.count += 1
      grouped.set(lead.status, entry)
    }
    for (const [stage, entry] of grouped) {
      byStage.push({ stage, ...entry })
    }
    totalLeads = (raw as Lead[]).length
  }

  const wonCount = byStage.find((s) => s.stage === 'won')?.count ?? 0
  const conversionRate = totalLeads > 0 ? wonCount / totalLeads : undefined

  return {
    totalLeads,
    conversionRate,
    pipelineValue: pipelineRes.data.pipeline_value ?? byStage.reduce((s, c) => s + c.value, 0),
    tasksDueToday: (taskStatsRes.data as { due_today?: number }).due_today,
    pipelineByStage: byStage,
    source: 'fallback',
  }
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError(null)
      try {
        const res = await getCrmDashboard()
        const d: CrmDashboardResponse = res.data.data
        const pipelineByStage = Object.entries(d.pipeline_value_by_stage ?? {}).map(
          ([stage, value]) => ({ stage, value, count: 0 }),
        )
        if (!cancelled) {
          setData({
            totalLeads: d.total_leads,
            conversionRate: d.conversion_rate,
            pipelineValue: pipelineByStage.reduce((s, c) => s + c.value, 0),
            tasksDueToday: d.tasks_due_today,
            pipelineByStage,
            source: 'dashboard',
          })
        }
      } catch {
        try {
          const fallback = await loadFallbackDashboard()
          if (!cancelled) setData(fallback)
        } catch {
          if (!cancelled) setError('Unable to load dashboard data.')
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

  if (loading) return <Loading label="Loading dashboard..." />
  if (error) return <ErrorBanner message={error} />
  if (!data) return <ErrorBanner message="No dashboard data." />

  const maxStageValue = Math.max(1, ...data.pipelineByStage.map((s) => s.value))

  return (
    <div className="page">
      <div className="page-header">
        <h1>Dashboard</h1>
        {data.source === 'fallback' ? (
          <span className="badge badge-default">Computed from pipeline &amp; tasks</span>
        ) : null}
      </div>

      <div className="stat-grid">
        <StatCard label="Total Leads" value={data.totalLeads ?? '—'} />
        <StatCard
          label="Conversion Rate"
          value={data.conversionRate !== undefined ? formatPercent(data.conversionRate) : '—'}
        />
        <StatCard label="Pipeline Value" value={formatCurrency(data.pipelineValue)} />
        <StatCard label="Tasks Due Today" value={data.tasksDueToday ?? '—'} />
      </div>

      <section className="panel">
        <h2>Pipeline value by stage</h2>
        {data.pipelineByStage.length === 0 ? (
          <p className="muted">No pipeline data available.</p>
        ) : (
          <div className="stage-bars">
            {data.pipelineByStage.map((s) => (
              <div className="stage-bar-row" key={s.stage}>
                <div className="stage-bar-label">
                  {titleCase(s.stage)}
                  {s.count ? ` (${s.count})` : ''}
                </div>
                <div className="stage-bar-track">
                  <div
                    className="stage-bar-fill"
                    style={{ width: `${(s.value / maxStageValue) * 100}%` }}
                  />
                </div>
                <div className="stage-bar-value">{formatCurrency(s.value)}</div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
