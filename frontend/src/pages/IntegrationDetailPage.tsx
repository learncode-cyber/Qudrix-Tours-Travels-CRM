import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  deleteApiConnectorEndpoint,
  executeApiConnector,
  getApiConnector,
  listApiConnectorCallLogs,
  testApiConnectorConnection,
  updateApiConnector,
  updateApiConnectorCredentials,
  upsertApiConnectorEndpoint,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { ApiConnector, ApiConnectorCallLog, ApiConnectorEndpoint } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyEndpointForm = {
  operation: '',
  http_method: 'GET',
  path: '',
  query_template: '',
  request_template: '',
  response_mapping: '',
  response_collection_path: '',
}

export default function IntegrationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [tab, setTab] = useState<'endpoints' | 'logs'>('endpoints')

  const [connector, setConnector] = useState<ApiConnector | null>(null)
  const [contractRequired, setContractRequired] = useState(true)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const [credentialsJson, setCredentialsJson] = useState('{}')
  const [credentialsBusy, setCredentialsBusy] = useState(false)
  const [credentialsSaved, setCredentialsSaved] = useState(false)

  const [testBusy, setTestBusy] = useState(false)
  const [testResult, setTestResult] = useState<{ connected: boolean; error?: string } | null>(null)

  const [showEndpointForm, setShowEndpointForm] = useState(false)
  const [endpointForm, setEndpointForm] = useState(emptyEndpointForm)
  const [endpointSaving, setEndpointSaving] = useState(false)
  const [endpointFormError, setEndpointFormError] = useState<string | null>(null)

  const [execOperation, setExecOperation] = useState('')
  const [execParamsJson, setExecParamsJson] = useState('{}')
  const [execBusy, setExecBusy] = useState(false)
  const [execError, setExecError] = useState<string | null>(null)
  const [execResult, setExecResult] = useState<{ raw: unknown; mapped: unknown; duration_ms: number; status: number } | null>(null)

  const [logs, setLogs] = useState<ApiConnectorCallLog[]>([])
  const [logsLoading, setLogsLoading] = useState(false)
  const [logsError, setLogsError] = useState<string | null>(null)
  const [logsLoaded, setLogsLoaded] = useState(false)

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await getApiConnector(id)
      setConnector(res.data.data)
      setContractRequired(res.data.contract_required)
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this connector.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function loadLogs() {
    if (!id) return
    setLogsLoading(true)
    setLogsError(null)
    try {
      const res = await listApiConnectorCallLogs(id)
      setLogs(res.data.data ?? [])
      setLogsLoaded(true)
    } catch (err) {
      setLogsError(getErrorMessage(err, 'Unable to load call logs.'))
    } finally {
      setLogsLoading(false)
    }
  }

  function switchTab(next: 'endpoints' | 'logs') {
    setTab(next)
    if (next === 'logs' && !logsLoaded) loadLogs()
  }

  async function handleToggleActive() {
    if (!connector) return
    setActionError(null)
    try {
      await updateApiConnector(connector.id, { is_active: !connector.is_active })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to update this connector.'))
    }
  }

  async function handleSaveCredentials(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setCredentialsBusy(true)
    setActionError(null)
    setCredentialsSaved(false)
    try {
      const parsed = JSON.parse(credentialsJson)
      await updateApiConnectorCredentials(id, parsed)
      setCredentialsSaved(true)
      setCredentialsJson('{}')
      await load()
    } catch (err) {
      if (err instanceof SyntaxError) {
        setActionError('Credentials must be valid JSON, e.g. {"token": "..."}.')
      } else {
        setActionError(getErrorMessage(err, 'Unable to save credentials.'))
      }
    } finally {
      setCredentialsBusy(false)
    }
  }

  async function handleTestConnection() {
    if (!id) return
    setTestBusy(true)
    setTestResult(null)
    setActionError(null)
    try {
      const res = await testApiConnectorConnection(id)
      setTestResult(res.data.data)
      await load()
    } catch (err) {
      const anyErr = err as { response?: { data?: { data?: { connected: boolean; error?: string } } } }
      if (anyErr.response?.data?.data) {
        setTestResult(anyErr.response.data.data)
      } else {
        setActionError(getErrorMessage(err, 'Unable to test this connection.'))
      }
    } finally {
      setTestBusy(false)
    }
  }

  async function handleCreateEndpoint(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setEndpointSaving(true)
    setEndpointFormError(null)
    try {
      await upsertApiConnectorEndpoint(id, {
        operation: endpointForm.operation,
        http_method: endpointForm.http_method,
        path: endpointForm.path,
        query_template: endpointForm.query_template ? JSON.parse(endpointForm.query_template) : undefined,
        request_template: endpointForm.request_template ? JSON.parse(endpointForm.request_template) : undefined,
        response_mapping: endpointForm.response_mapping ? JSON.parse(endpointForm.response_mapping) : undefined,
        response_collection_path: endpointForm.response_collection_path || undefined,
      })
      setEndpointForm(emptyEndpointForm)
      setShowEndpointForm(false)
      await load()
    } catch (err) {
      if (err instanceof SyntaxError) {
        setEndpointFormError('Templates and the response mapping must be valid JSON objects.')
      } else {
        setEndpointFormError(getErrorMessage(err, 'Unable to save this endpoint mapping.'))
      }
    } finally {
      setEndpointSaving(false)
    }
  }

  async function handleDeleteEndpoint(endpoint: ApiConnectorEndpoint) {
    if (!id) return
    if (!window.confirm(`Delete the "${endpoint.operation}" endpoint mapping?`)) return
    setActionError(null)
    try {
      await deleteApiConnectorEndpoint(id, endpoint.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this endpoint mapping.'))
    }
  }

  async function handleExecute(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setExecBusy(true)
    setExecError(null)
    setExecResult(null)
    try {
      const params = execParamsJson ? JSON.parse(execParamsJson) : {}
      const res = await executeApiConnector(id, execOperation, params)
      setExecResult(res.data.data)
    } catch (err) {
      if (err instanceof SyntaxError) {
        setExecError('Params must be valid JSON, e.g. {"origin": "DXB"}.')
      } else {
        setExecError(getErrorMessage(err, 'This operation failed.'))
      }
    } finally {
      setExecBusy(false)
    }
  }

  return (
    <div className="page">
      <Link to="/integrations" className="back-link">
        &larr; Integrations
      </Link>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : connector ? (
        <>
          <div className="page-header">
            <div>
              <h1>{connector.name}</h1>
              <p className="field-hint">
                {titleCase(connector.category)} &middot; {connector.base_url}
              </p>
            </div>
            <div className="header-actions">
              <Badge
                text={contractRequired ? 'Contract Required' : titleCase(connector.status)}
                tone={contractRequired ? 'warning' : statusTone(connector.status)}
              />
              <button type="button" className="btn btn-ghost" onClick={handleToggleActive}>
                {connector.is_active ? 'Deactivate' : 'Activate'}
              </button>
              <button type="button" className="btn btn-ghost" onClick={handleTestConnection} disabled={testBusy}>
                {testBusy ? 'Testing...' : 'Test Connection'}
              </button>
            </div>
          </div>

          {actionError ? <ErrorBanner message={actionError} /> : null}
          {testResult ? (
            <div className={`state-block ${testResult.connected ? 'success' : 'error'}`}>
              {testResult.connected ? 'Connected.' : testResult.error}
            </div>
          ) : null}
          {connector.last_test_error && !testResult ? (
            <p className="field-hint">Last test error: {connector.last_test_error}</p>
          ) : null}

          <form onSubmit={handleSaveCredentials} className="stacked-form">
            {credentialsSaved ? <div className="state-block success">Credentials saved (encrypted at rest).</div> : null}
            <label className="field">
              <span>Credentials (JSON)</span>
              <textarea
                placeholder='{"token": "..."}'
                value={credentialsJson}
                onChange={(e) => setCredentialsJson(e.target.value)}
              />
              <p className="field-hint">
                Write-only — never returned by any read, including this page. Shape depends on the
                connector's auth type (e.g. {'{'}"token"{'}'} for bearer, {'{'}"api_key"{'}'} for an API
                key header, {'{'}"username","password"{'}'} for basic).
              </p>
            </label>
            <div className="modal-actions">
              <button type="submit" className="btn btn-primary" disabled={credentialsBusy}>
                {credentialsBusy ? 'Saving...' : 'Save Credentials'}
              </button>
            </div>
          </form>

          <div className="view-toggle">
            <button type="button" className={tab === 'endpoints' ? 'active' : ''} onClick={() => switchTab('endpoints')}>
              Endpoint Mappings
            </button>
            <button type="button" className={tab === 'logs' ? 'active' : ''} onClick={() => switchTab('logs')}>
              Call Logs
            </button>
          </div>

          {tab === 'endpoints' ? (
            <>
              <div className="page-header">
                <div className="header-actions">
                  <button type="button" className="btn btn-primary" onClick={() => setShowEndpointForm(true)}>
                    + Map Endpoint
                  </button>
                </div>
              </div>
              {!connector.endpoints || connector.endpoints.length === 0 ? (
                <EmptyState message="No endpoints mapped yet — this connector cannot be activated until one exists." />
              ) : (
                <div className="table-wrap">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>Operation</th>
                        <th>Method</th>
                        <th>Path</th>
                        <th>Active</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {connector.endpoints.map((ep) => (
                        <tr key={ep.id}>
                          <td>{ep.operation}</td>
                          <td>{ep.http_method}</td>
                          <td>{ep.path}</td>
                          <td>{ep.is_active ? 'Yes' : 'No'}</td>
                          <td>
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => handleDeleteEndpoint(ep)}>
                              Delete
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              <h2>Try an Operation</h2>
              <form onSubmit={handleExecute} className="stacked-form">
                {execError ? <div className="state-block error">{execError}</div> : null}
                <label className="field">
                  <span>Operation</span>
                  <input required value={execOperation} onChange={(e) => setExecOperation(e.target.value)} />
                </label>
                <label className="field">
                  <span>Params (JSON)</span>
                  <textarea value={execParamsJson} onChange={(e) => setExecParamsJson(e.target.value)} />
                </label>
                <div className="modal-actions">
                  <button type="submit" className="btn btn-primary" disabled={execBusy}>
                    {execBusy ? 'Running...' : 'Execute'}
                  </button>
                </div>
              </form>
              {execResult ? (
                <div className="table-wrap">
                  <p>Status {execResult.status} &middot; {execResult.duration_ms}ms</p>
                  <pre>{JSON.stringify(execResult.mapped, null, 2)}</pre>
                </div>
              ) : null}
            </>
          ) : null}

          {tab === 'logs' ? (
            logsLoading ? (
              <Loading />
            ) : logsError ? (
              <ErrorBanner message={logsError} />
            ) : logs.length === 0 ? (
              <EmptyState message="No calls logged yet." />
            ) : (
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Operation</th>
                      <th>Method</th>
                      <th>URL</th>
                      <th>Status</th>
                      <th>Duration</th>
                      <th>Result</th>
                      <th>When</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.map((l) => (
                      <tr key={l.id}>
                        <td>{l.operation}</td>
                        <td>{l.http_method}</td>
                        <td>{l.url}</td>
                        <td>{l.response_status ?? '—'}</td>
                        <td>{l.duration_ms ?? '—'}ms</td>
                        <td>
                          <Badge text={l.success ? 'Success' : 'Failed'} tone={l.success ? 'success' : 'danger'} />
                          {l.error_message ? <div className="field-hint">{l.error_message}</div> : null}
                        </td>
                        <td>{formatDate(l.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )
          ) : null}

          {showEndpointForm ? (
            <Modal title="Map Endpoint" onClose={() => setShowEndpointForm(false)}>
              <form onSubmit={handleCreateEndpoint} className="stacked-form">
                {endpointFormError ? <div className="state-block error">{endpointFormError}</div> : null}
                <label className="field">
                  <span>Operation Name</span>
                  <input
                    required
                    placeholder="search"
                    value={endpointForm.operation}
                    onChange={(e) => setEndpointForm({ ...endpointForm, operation: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>HTTP Method</span>
                  <select
                    value={endpointForm.http_method}
                    onChange={(e) => setEndpointForm({ ...endpointForm, http_method: e.target.value })}
                  >
                    {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => (
                      <option key={m} value={m}>
                        {m}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="field">
                  <span>Path</span>
                  <input
                    required
                    placeholder="/flights/search"
                    value={endpointForm.path}
                    onChange={(e) => setEndpointForm({ ...endpointForm, path: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Query Template (JSON)</span>
                  <textarea
                    placeholder='{"origin": "{{origin}}"}'
                    value={endpointForm.query_template}
                    onChange={(e) => setEndpointForm({ ...endpointForm, query_template: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Request Body Template (JSON)</span>
                  <textarea
                    placeholder='{"apiKey": "{{credential.api_key}}"}'
                    value={endpointForm.request_template}
                    onChange={(e) => setEndpointForm({ ...endpointForm, request_template: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Response Mapping (JSON)</span>
                  <textarea
                    placeholder='{"price": "fare.total"}'
                    value={endpointForm.response_mapping}
                    onChange={(e) => setEndpointForm({ ...endpointForm, response_mapping: e.target.value })}
                  />
                </label>
                <label className="field">
                  <span>Response Collection Path</span>
                  <input
                    placeholder="results"
                    value={endpointForm.response_collection_path}
                    onChange={(e) => setEndpointForm({ ...endpointForm, response_collection_path: e.target.value })}
                  />
                  <p className="field-hint">Leave blank if the response is a single object, not a list.</p>
                </label>
                <div className="modal-actions">
                  <button type="button" className="btn btn-ghost" onClick={() => setShowEndpointForm(false)}>
                    Cancel
                  </button>
                  <button type="submit" className="btn btn-primary" disabled={endpointSaving}>
                    {endpointSaving ? 'Saving...' : 'Save Mapping'}
                  </button>
                </div>
              </form>
            </Modal>
          ) : null}
        </>
      ) : null}
    </div>
  )
}
