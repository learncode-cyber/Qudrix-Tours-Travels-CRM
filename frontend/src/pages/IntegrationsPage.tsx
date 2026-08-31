import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { createApiConnector, listApiConnectors } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { ApiConnector, ApiConnectorAuthType, ApiConnectorCategory } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const CATEGORIES: ApiConnectorCategory[] = [
  'flight', 'hotel', 'visa', 'payment', 'sms', 'whatsapp', 'email', 'ai', 'analytics', 'crm', 'other',
]
const AUTH_TYPES: ApiConnectorAuthType[] = ['none', 'bearer', 'api_key_header', 'api_key_query', 'basic', 'custom_headers']

const emptyForm = {
  name: '',
  category: 'flight' as ApiConnectorCategory,
  provider_name: '',
  base_url: '',
  auth_type: 'bearer' as ApiConnectorAuthType,
  auth_key_name: '',
}

export default function IntegrationsPage() {
  const [connectors, setConnectors] = useState<(ApiConnector & { contract_required: boolean })[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listApiConnectors(categoryFilter === 'all' ? undefined : categoryFilter)
      setConnectors(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load integrations.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [categoryFilter])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createApiConnector({
        name: form.name,
        category: form.category,
        provider_name: form.provider_name || undefined,
        base_url: form.base_url,
        auth_type: form.auth_type,
        auth_key_name: form.auth_key_name || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this connector.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Integrations" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Integrations</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Connector
          </button>
        </div>
      </div>
      <p className="field-hint">
        Every connector here needs the operator's own real provider contract (base URL, auth,
        per-operation endpoints) — nothing is invented or pre-wired to a third party. A connector
        with no mapped endpoint shows "Contract Required" and cannot be activated.
      </p>
      <div className="view-toggle">
        {['all', ...CATEGORIES].map((c) => (
          <button key={c} type="button" className={categoryFilter === c ? 'active' : ''} onClick={() => setCategoryFilter(c)}>
            {titleCase(c)}
          </button>
        ))}
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : connectors.length === 0 ? (
        <EmptyState message="No connectors configured." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Base URL</th>
                <th>Status</th>
                <th>Active</th>
                <th>Last Test</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {connectors.map((c) => (
                <tr key={c.id}>
                  <td>{c.name}</td>
                  <td>{titleCase(c.category)}</td>
                  <td>{c.base_url}</td>
                  <td>
                    <Badge
                      text={c.contract_required ? 'Contract Required' : titleCase(c.status)}
                      tone={c.contract_required ? 'warning' : statusTone(c.status)}
                    />
                  </td>
                  <td>{c.is_active ? 'Yes' : 'No'}</td>
                  <td>{c.last_test_at ? formatDate(c.last_test_at) : '—'}</td>
                  <td>
                    <Link to={`/integrations/${c.id}`} className="btn btn-ghost btn-sm">
                      Manage
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Connector" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Category</span>
              <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value as ApiConnectorCategory })}>
                {CATEGORIES.map((c) => (
                  <option key={c} value={c}>
                    {titleCase(c)}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Provider Name</span>
              <input value={form.provider_name} onChange={(e) => setForm({ ...form, provider_name: e.target.value })} />
            </label>
            <label className="field">
              <span>Base URL</span>
              <input
                required
                type="url"
                placeholder="https://api.provider.com"
                value={form.base_url}
                onChange={(e) => setForm({ ...form, base_url: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Auth Type</span>
              <select value={form.auth_type} onChange={(e) => setForm({ ...form, auth_type: e.target.value as ApiConnectorAuthType })}>
                {AUTH_TYPES.map((a) => (
                  <option key={a} value={a}>
                    {titleCase(a)}
                  </option>
                ))}
              </select>
            </label>
            {form.auth_type === 'api_key_header' ? (
              <label className="field">
                <span>Auth Header Name</span>
                <input
                  placeholder="X-API-Key"
                  value={form.auth_key_name}
                  onChange={(e) => setForm({ ...form, auth_key_name: e.target.value })}
                />
              </label>
            ) : null}
            <p className="field-hint">
              Credentials are set separately once the connector exists, on its own dedicated
              endpoint — they're never returned by any read.
            </p>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Connector'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
