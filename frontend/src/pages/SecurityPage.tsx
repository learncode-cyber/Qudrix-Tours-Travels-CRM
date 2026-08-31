import { useEffect, useState } from 'react'
import { getAccessLogs, getAuditLogs, getFailedLogins, getSecuritySummary } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, NotAvailable, StatCard } from '../components/ui'
import type { AccessLogEntry, AuditLogEntry, FailedLoginAttemptEntry, SecuritySummary } from '../types'
import { formatDate, getErrorMessage, titleCase } from '../utils/format'

export default function SecurityPage() {
  const [tab, setTab] = useState<'access' | 'audit' | 'failed'>('access')
  const [summary, setSummary] = useState<SecuritySummary | null>(null)
  const [forbidden, setForbidden] = useState(false)
  const [notAvailable, setNotAvailable] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getSecuritySummary()
      .then((res) => setSummary(res.data.data))
      .catch((err) => {
        const anyErr = err as { response?: { status?: number } }
        if (anyErr.response?.status === 403) {
          setForbidden(true)
        } else if (anyErr.response?.status === 404) {
          setNotAvailable(true)
        } else {
          setError(getErrorMessage(err, 'Unable to load the security summary.'))
        }
      })
  }, [])

  if (notAvailable) return <NotAvailable label="Security trail" />
  if (forbidden) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Security Trail</h1>
        </div>
        <ErrorBanner message="Only an administrator can view the security trail (access logs, audit logs, failed logins)." />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Security Trail</h1>
      </div>

      {error ? <ErrorBanner message={error} /> : null}

      {summary ? (
        <div className="stat-grid">
          <StatCard label="Requests (24h)" value={summary.total_requests} />
          <StatCard label="Suspicious (24h)" value={summary.suspicious_requests} />
          <StatCard label="Failed Logins (24h)" value={summary.failed_logins} />
        </div>
      ) : null}

      <div className="view-toggle">
        <button type="button" className={tab === 'access' ? 'active' : ''} onClick={() => setTab('access')}>
          Access Logs
        </button>
        <button type="button" className={tab === 'audit' ? 'active' : ''} onClick={() => setTab('audit')}>
          Audit Logs
        </button>
        <button type="button" className={tab === 'failed' ? 'active' : ''} onClick={() => setTab('failed')}>
          Failed Logins
        </button>
      </div>

      {tab === 'access' ? <AccessLogsTab /> : tab === 'audit' ? <AuditLogsTab /> : <FailedLoginsTab />}
    </div>
  )
}

function AccessLogsTab() {
  const [logs, setLogs] = useState<AccessLogEntry[]>([])
  const [suspiciousOnly, setSuspiciousOnly] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    getAccessLogs({ suspicious_only: suspiciousOnly || undefined })
      .then((res) => setLogs(res.data.data ?? []))
      .catch((err) => setError(getErrorMessage(err, 'Unable to load access logs.')))
      .finally(() => setLoading(false))
  }, [suspiciousOnly])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />

  return (
    <>
      <label className="field field-inline">
        <input type="checkbox" checked={suspiciousOnly} onChange={(e) => setSuspiciousOnly(e.target.checked)} />
        <span>Suspicious only</span>
      </label>
      {logs.length === 0 ? (
        <EmptyState message="No access log entries." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>When</th>
                <th>Method</th>
                <th>URL</th>
                <th>Status</th>
                <th>IP</th>
                <th>Flag</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((l) => (
                <tr key={l.id}>
                  <td>{formatDate(l.created_at)}</td>
                  <td>{l.method}</td>
                  <td style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {l.url}
                  </td>
                  <td>{l.status_code ?? '—'}</td>
                  <td>{l.ip_address ?? '—'}</td>
                  <td>
                    {l.is_suspicious ? <Badge text={l.suspicion_reason ?? 'Suspicious'} tone="danger" /> : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}

function AuditLogsTab() {
  const [logs, setLogs] = useState<AuditLogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getAuditLogs()
      .then((res) => setLogs(res.data.data ?? []))
      .catch((err) => setError(getErrorMessage(err, 'Unable to load audit logs.')))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (logs.length === 0) return <EmptyState message="No audit log entries." />

  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Description</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          {logs.map((l) => (
            <tr key={l.id}>
              <td>{formatDate(l.created_at)}</td>
              <td>{l.action}</td>
              <td>
                {titleCase(l.entity_type)}
                {l.entity_id ? ` #${l.entity_id}` : ''}
              </td>
              <td>{l.description ?? '—'}</td>
              <td>{l.ip_address ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function FailedLoginsTab() {
  const [attempts, setAttempts] = useState<FailedLoginAttemptEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getFailedLogins()
      .then((res) => setAttempts(res.data.data ?? []))
      .catch((err) => setError(getErrorMessage(err, 'Unable to load failed login attempts.')))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (attempts.length === 0) return <EmptyState message="No failed login attempts recorded." />

  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Email</th>
            <th>IP</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
          {attempts.map((a) => (
            <tr key={a.id}>
              <td>{formatDate(a.created_at)}</td>
              <td>{a.email}</td>
              <td>{a.ip_address ?? '—'}</td>
              <td>{titleCase(a.reason)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
