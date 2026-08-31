import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  createAiProvider,
  deleteAiProvider,
  getAiUsage,
  listAiProviders,
  testAiProvider,
  updateAiProvider,
  updateAiProviderCredentials,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable, StatCard } from '../components/ui'
import type { AiProvider, AiProviderKey, AiUsageResponse } from '../types'
import { formatCurrency, formatDate, getErrorMessage, titleCase } from '../utils/format'

const PROVIDERS: AiProviderKey[] = ['anthropic', 'openai', 'gemini']

const emptyForm = {
  provider: 'anthropic' as AiProviderKey,
  model: '',
  base_url: '',
  priority: '0',
  monthly_cost_limit_usd: '',
  input_cost_per_million: '',
  output_cost_per_million: '',
  max_output_tokens: '',
}

export default function AiProvidersPage() {
  const [tab, setTab] = useState<'providers' | 'usage'>('providers')

  return (
    <div className="page">
      <div className="page-header">
        <h1>AI Providers</h1>
      </div>
      <p className="field-hint">
        Provider-independent by design: application AI features never name a vendor directly,
        they ask for a completion and the gateway picks a configured, active provider by
        priority, fails over to the next one on error, and records real usage and cost.
      </p>
      <div className="view-toggle">
        <button type="button" className={tab === 'providers' ? 'active' : ''} onClick={() => setTab('providers')}>
          Providers
        </button>
        <button type="button" className={tab === 'usage' ? 'active' : ''} onClick={() => setTab('usage')}>
          Usage
        </button>
      </div>
      {tab === 'providers' ? <ProvidersTab /> : <UsageTab />}
    </div>
  )
}

function ProvidersTab() {
  const [providers, setProviders] = useState<AiProvider[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const [credTarget, setCredTarget] = useState<AiProvider | null>(null)
  const [credValue, setCredValue] = useState('')
  const [credBusy, setCredBusy] = useState(false)
  const [credError, setCredError] = useState<string | null>(null)

  const [testBusy, setTestBusy] = useState<number | null>(null)
  const [testResults, setTestResults] = useState<Record<number, { ok: boolean; message: string }>>({})

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listAiProviders()
      setProviders(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load AI providers.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createAiProvider({
        provider: form.provider,
        model: form.model,
        base_url: form.base_url || undefined,
        priority: form.priority ? Number(form.priority) : undefined,
        monthly_cost_limit_usd: form.monthly_cost_limit_usd ? Number(form.monthly_cost_limit_usd) : undefined,
        input_cost_per_million: form.input_cost_per_million ? Number(form.input_cost_per_million) : undefined,
        output_cost_per_million: form.output_cost_per_million ? Number(form.output_cost_per_million) : undefined,
        max_output_tokens: form.max_output_tokens ? Number(form.max_output_tokens) : undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this provider.'))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(p: AiProvider) {
    setActionError(null)
    try {
      await updateAiProvider(p.id, { is_active: !p.is_active })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to update this provider.'))
    }
  }

  async function makeDefault(p: AiProvider) {
    setActionError(null)
    try {
      await updateAiProvider(p.id, { is_default: true })
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to set this as the default provider.'))
    }
  }

  async function handleDelete(p: AiProvider) {
    if (!window.confirm(`Delete provider "${p.provider}/${p.model}"?`)) return
    setActionError(null)
    try {
      await deleteAiProvider(p.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to delete this provider.'))
    }
  }

  async function handleSaveCredentials(e: FormEvent) {
    e.preventDefault()
    if (!credTarget) return
    setCredBusy(true)
    setCredError(null)
    try {
      await updateAiProviderCredentials(credTarget.id, credValue)
      setCredTarget(null)
      setCredValue('')
      await load()
    } catch (err) {
      setCredError(getErrorMessage(err, 'Unable to save this API key.'))
    } finally {
      setCredBusy(false)
    }
  }

  async function handleTest(p: AiProvider) {
    setTestBusy(p.id)
    try {
      const res = await testAiProvider(p.id)
      const data = res.data.data
      setTestResults((prev) => ({
        ...prev,
        [p.id]: data.ok
          ? { ok: true, message: `OK — replied "${data.reply}" in ${data.latency_ms}ms` }
          : { ok: false, message: data.error },
      }))
      await load()
    } catch (err) {
      const anyErr = err as { response?: { data?: { data?: { ok: boolean; error?: string } } } }
      const data = anyErr.response?.data?.data
      setTestResults((prev) => ({
        ...prev,
        [p.id]: { ok: false, message: data?.error ?? getErrorMessage(err, 'Test failed.') },
      }))
    } finally {
      setTestBusy(null)
    }
  }

  if (notAvailable) return <NotAvailable label="AI providers" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Provider
          </button>
        </div>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : providers.length === 0 ? (
        <EmptyState message="No AI providers configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Provider</th>
                <th>Model</th>
                <th>Priority</th>
                <th>Default</th>
                <th>Credentials</th>
                <th>Active</th>
                <th>Last Test</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {providers.map((p) => (
                <tr key={p.id}>
                  <td>{titleCase(p.provider)}</td>
                  <td>{p.model}</td>
                  <td>{p.priority ?? 0}</td>
                  <td>
                    {p.is_default ? (
                      <Badge text="Default" tone="success" />
                    ) : (
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => makeDefault(p)}>
                        Make Default
                      </button>
                    )}
                  </td>
                  <td>
                    <Badge text={p.credentials_configured ? 'Configured' : 'Missing'} tone={p.credentials_configured ? 'success' : 'warning'} />
                  </td>
                  <td>{p.is_active ? 'Yes' : 'No'}</td>
                  <td>
                    {formatDate(p.last_test_at)}
                    {p.last_test_error ? <div className="field-hint">{p.last_test_error}</div> : null}
                  </td>
                  <td>
                    <div className="row-actions">
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => {
                          setCredTarget(p)
                          setCredValue('')
                          setCredError(null)
                        }}
                      >
                        Set API Key
                      </button>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => toggleActive(p)}>
                        {p.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={testBusy === p.id}
                        onClick={() => handleTest(p)}
                      >
                        {testBusy === p.id ? 'Testing...' : 'Test'}
                      </button>
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => handleDelete(p)}>
                        Delete
                      </button>
                    </div>
                    {testResults[p.id] ? (
                      <div className={`state-block ${testResults[p.id].ok ? 'success' : 'error'}`}>
                        {testResults[p.id].message}
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New AI Provider" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Provider</span>
              <select value={form.provider} onChange={(e) => setForm({ ...form, provider: e.target.value as AiProviderKey })}>
                {PROVIDERS.map((p) => (
                  <option key={p} value={p}>
                    {titleCase(p)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Model</span>
              <input
                required
                placeholder="claude-sonnet-5"
                value={form.model}
                onChange={(e) => setForm({ ...form, model: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Base URL</span>
              <input
                type="url"
                placeholder="Leave blank to use the provider's default"
                value={form.base_url}
                onChange={(e) => setForm({ ...form, base_url: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Priority</span>
              <input
                type="number"
                min={0}
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}
              />
              <p className="field-hint">Lower numbers are tried first; the default provider is always tried before priority order.</p>
            </label>
            <label className="field">
              <span>Monthly Cost Limit (USD)</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={form.monthly_cost_limit_usd}
                onChange={(e) => setForm({ ...form, monthly_cost_limit_usd: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Input Cost / Million Tokens (USD)</span>
              <input
                type="number"
                min={0}
                step="0.0001"
                value={form.input_cost_per_million}
                onChange={(e) => setForm({ ...form, input_cost_per_million: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Output Cost / Million Tokens (USD)</span>
              <input
                type="number"
                min={0}
                step="0.0001"
                value={form.output_cost_per_million}
                onChange={(e) => setForm({ ...form, output_cost_per_million: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Max Output Tokens</span>
              <input
                type="number"
                min={1}
                value={form.max_output_tokens}
                onChange={(e) => setForm({ ...form, max_output_tokens: e.target.value })}
              />
            </label>
            <p className="field-hint">
              The API key is set separately once the provider exists, on its own dedicated
              endpoint — it's never returned by any read.
            </p>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Provider'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {credTarget ? (
        <Modal title={`Set API Key — ${credTarget.provider}/${credTarget.model}`} onClose={() => setCredTarget(null)}>
          <form onSubmit={handleSaveCredentials} className="stacked-form">
            {credError ? <div className="state-block error">{credError}</div> : null}
            <label className="field">
              <span>API Key</span>
              <input required type="password" value={credValue} onChange={(e) => setCredValue(e.target.value)} />
              <p className="field-hint">Encrypted at rest, never returned by any read — including this form on reopen.</p>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setCredTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={credBusy}>
                {credBusy ? 'Saving...' : 'Save API Key'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}

function UsageTab() {
  const [usage, setUsage] = useState<AiUsageResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await getAiUsage()
      setUsage(res.data.data)
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load AI usage.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  if (notAvailable) return <NotAvailable label="AI usage" />
  if (loading) return <Loading />
  if (error) return <ErrorBanner message={error} />
  if (!usage) return null

  return (
    <>
      <p className="field-hint">Since {formatDate(usage.since)}</p>
      <div className="stat-grid">
        <StatCard label="Total Calls" value={usage.total_calls} />
        <StatCard label="Total Cost" value={formatCurrency(usage.total_cost_usd)} />
      </div>
      {usage.providers_without_cost_rates.length > 0 ? (
        <div className="state-block error">
          Cost figures exclude {usage.providers_without_cost_rates.length} provider(s) with no
          configured per-token rates — their real spend is unknown, not zero.
        </div>
      ) : null}
      {usage.breakdown.length === 0 ? (
        <EmptyState message="No AI usage recorded yet this period." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Feature</th>
                <th>Status</th>
                <th>Calls</th>
                <th>Prompt Tokens</th>
                <th>Completion Tokens</th>
                <th>Cost</th>
                <th>Avg Latency</th>
              </tr>
            </thead>
            <tbody>
              {usage.breakdown.map((row, i) => (
                <tr key={i}>
                  <td>{row.feature}</td>
                  <td>
                    <Badge text={titleCase(row.status)} tone={row.status === 'success' ? 'success' : 'danger'} />
                  </td>
                  <td>{row.calls}</td>
                  <td>{row.prompt_tokens}</td>
                  <td>{row.completion_tokens}</td>
                  <td>{formatCurrency(row.cost_usd)}</td>
                  <td>{row.avg_latency_ms ? `${Math.round(row.avg_latency_ms)}ms` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
