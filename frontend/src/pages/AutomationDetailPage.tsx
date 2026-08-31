import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  clearAutomationLogs,
  executeAutomation,
  getAutomation,
  getAutomationLogs,
  getAutomationStats,
  testAutomation,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, StatCard } from '../components/ui'
import type { Automation, AutomationLogData, AutomationStats } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function AutomationDetailPage() {
  const { id } = useParams()
  const [automation, setAutomation] = useState<Automation | null>(null)
  const [logs, setLogs] = useState<AutomationLogData[]>([])
  const [stats, setStats] = useState<AutomationStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [runError, setRunError] = useState<string | null>(null)
  const [running, setRunning] = useState(false)
  const [testing, setTesting] = useState(false)
  const [lastResult, setLastResult] = useState<Record<string, unknown> | null>(null)

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const [autoRes, logsRes, statsRes] = await Promise.all([
        getAutomation(id),
        getAutomationLogs(id),
        getAutomationStats(id),
      ])
      setAutomation(autoRes.data.data)
      setLogs(logsRes.data.data ?? [])
      setStats(statsRes.data.data)
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this automation.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function handleExecute() {
    if (!id) return
    setRunning(true)
    setRunError(null)
    try {
      const res = await executeAutomation(id, {})
      setLastResult(res.data.data)
      await load()
    } catch (err) {
      setRunError(getErrorMessage(err, 'Execution failed.'))
    } finally {
      setRunning(false)
    }
  }

  async function handleTest() {
    if (!id) return
    setTesting(true)
    setRunError(null)
    try {
      const res = await testAutomation(id, {})
      setLastResult(res.data.data as unknown as Record<string, unknown>)
    } catch (err) {
      setRunError(getErrorMessage(err, 'Test run failed.'))
    } finally {
      setTesting(false)
    }
  }

  async function handleClearLogs() {
    if (!id) return
    if (!window.confirm('Clear all execution logs for this automation?')) return
    try {
      await clearAutomationLogs(id)
      await load()
    } catch (err) {
      setRunError(getErrorMessage(err, 'Unable to clear logs.'))
    }
  }

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (!automation) return <EmptyState message="Automation not found." />

  return (
    <div className="page">
      <div className="page-header">
        <h1>{automation.name}</h1>
        <Link to="/automations" className="btn btn-ghost btn-sm">
          Back to Automations
        </Link>
      </div>

      <div className="field-hint">
        <Badge text={titleCase(automation.status)} tone={statusTone(automation.status)} /> · Trigger:{' '}
        {titleCase(automation.trigger_type)} · Runs: {automation.run_count} · Last run:{' '}
        {formatDate(automation.last_run_at)}
      </div>

      {stats ? (
        <div className="stat-grid">
          <StatCard label="Total Runs" value={stats.total_runs} />
          <StatCard label="Successes" value={stats.success_count} />
          <StatCard label="Errors" value={stats.error_count} />
          <StatCard label="Avg Execution" value={`${stats.avg_execution_time_ms} ms`} />
        </div>
      ) : null}

      <div className="panel">
        <h3>Steps</h3>
        {automation.steps && automation.steps.length > 0 ? (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Action</th>
                  <th>Config</th>
                  <th>Condition</th>
                </tr>
              </thead>
              <tbody>
                {automation.steps
                  .slice()
                  .sort((a, b) => a.step_order - b.step_order)
                  .map((s) => (
                    <tr key={s.id}>
                      <td>{s.step_order}</td>
                      <td>{titleCase(s.action_type)}</td>
                      <td>
                        <code>{JSON.stringify(s.action_config)}</code>
                      </td>
                      <td>{s.condition_type ? <code>{JSON.stringify(s.condition_config)}</code> : '—'}</td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState message="No steps configured on this automation yet." />
        )}
      </div>

      <div className="panel">
        <h3>Run</h3>
        {runError ? <ErrorBanner message={runError} /> : null}
        <div className="row-actions">
          <button type="button" className="btn btn-ghost btn-sm" onClick={handleTest} disabled={testing}>
            {testing ? 'Testing...' : 'Test (preview only)'}
          </button>
          <button type="button" className="btn btn-primary btn-sm" onClick={handleExecute} disabled={running}>
            {running ? 'Executing...' : 'Execute Now'}
          </button>
        </div>
        {lastResult ? (
          <pre className="field-hint" style={{ whiteSpace: 'pre-wrap' }}>
            {JSON.stringify(lastResult, null, 2)}
          </pre>
        ) : null}
      </div>

      <div className="panel">
        <div className="page-header">
          <h3>Execution Logs</h3>
          <button type="button" className="btn btn-ghost btn-sm" onClick={handleClearLogs}>
            Clear Logs
          </button>
        </div>
        {logs.length === 0 ? (
          <EmptyState message="No executions logged yet." />
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Status</th>
                  <th>Started</th>
                  <th>Duration</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((l) => (
                  <tr key={l.id}>
                    <td>
                      <Badge text={titleCase(l.status)} tone={l.status === 'success' ? 'success' : 'danger'} />
                    </td>
                    <td>{formatDate(l.started_at)}</td>
                    <td>{l.execution_time_ms !== null ? `${l.execution_time_ms} ms` : '—'}</td>
                    <td>{l.error_message ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
