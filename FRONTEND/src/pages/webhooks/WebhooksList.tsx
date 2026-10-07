import { useEffect, useState, FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, adminApi } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Webhook {
  id: number
  url: string
  events: string[]
  is_active: boolean
  last_triggered_at: string | null
}

interface ApiKeyOption {
  id: number
  name: string
}

export default function WebhooksList() {
  const [webhooks, setWebhooks] = useState<Webhook[]>([])
  const [apiKeys, setApiKeys] = useState<ApiKeyOption[]>([])
  const [availableEvents, setAvailableEvents] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [form, setForm] = useState({ api_key_id: '', url: '', events: [] as string[] })

  function load() {
    setLoading(true)
    adminApi.get('/admin/api/webhooks').then((res) => setWebhooks(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  useEffect(() => {
    api.get('/api-keys').then((res) => setApiKeys(res.data.data))
    adminApi.get('/admin/api/webhooks/events').then((res) => setAvailableEvents(res.data.events))
  }, [])

  function toggleEvent(event: string) {
    setForm((f) => ({
      ...f,
      events: f.events.includes(event) ? f.events.filter((e) => e !== event) : [...f.events, event],
    }))
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setFormError(null)
    try {
      await adminApi.post('/admin/api/webhooks', form)
      setShowForm(false)
      setForm({ api_key_id: '', url: '', events: [] })
      load()
    } catch (err: any) {
      setFormError(err.response?.data?.message || 'Could not create webhook.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Webhooks</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New webhook
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-ink mb-1">API key</label>
              <select
                required
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.api_key_id}
                onChange={(e) => setForm((f) => ({ ...f, api_key_id: e.target.value }))}
              >
                <option value="">Select API key…</option>
                {apiKeys.map((k) => (
                  <option key={k.id} value={k.id}>{k.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-1">URL</label>
              <Input type="url" required value={form.url} onChange={(e) => setForm((f) => ({ ...f, url: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-2">Events</label>
              <div className="flex flex-wrap gap-2">
                {availableEvents.map((ev) => (
                  <label key={ev} className="flex items-center gap-1 text-xs text-ink-soft border border-line px-2 py-1">
                    <input type="checkbox" checked={form.events.includes(ev)} onChange={() => toggleEvent(ev)} />
                    {ev}
                  </label>
                ))}
              </div>
            </div>
            <Button type="submit" disabled={submitting || form.events.length === 0}>
              {submitting ? 'Creating…' : 'Create webhook'}
            </Button>
            {formError && <p className="text-xs text-danger">{formError}</p>}
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : webhooks.length === 0 ? (
          <EmptyState title="No webhooks yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">URL</th>
                <th className="px-4 py-3 font-medium">Events</th>
                <th className="px-4 py-3 font-medium">Last triggered</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {webhooks.map((w) => (
                <tr key={w.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">
                    <Link to={`/webhooks/${w.id}`} className="hover:text-teal break-all">{w.url}</Link>
                  </td>
                  <td className="px-4 py-3 text-ink-soft text-xs">{(w.events || []).join(', ')}</td>
                  <td className="px-4 py-3 text-ink-soft">{w.last_triggered_at ? new Date(w.last_triggered_at).toLocaleString() : 'Never'}</td>
                  <td className="px-4 py-3">
                    <Badge tone={w.is_active ? 'success' : 'default'}>{w.is_active ? 'active' : 'inactive'}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
