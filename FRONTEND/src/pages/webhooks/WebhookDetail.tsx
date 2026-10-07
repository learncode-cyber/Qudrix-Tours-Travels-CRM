import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { adminApi } from '../../lib/api'
import { Card, Badge, Spinner, EmptyState, Button } from '../../components/ui'
import { ArrowLeft } from 'lucide-react'

interface Webhook {
  id: number
  url: string
  events: string[]
  is_active: boolean
  retry_limit: number
}

interface Stats {
  total_deliveries: number
  successful: number
  failed: number
  pending: number
  success_rate: number
  last_triggered: string | null
  last_status: string | null
}

interface Delivery {
  id: number
  status: 'success' | 'failed' | 'pending';
  response_status: number | null
  created_at: string
}

export default function WebhookDetail() {
  const { id } = useParams()
  const [webhook, setWebhook] = useState<Webhook | null>(null)
  const [stats, setStats] = useState<Stats | null>(null)
  const [deliveries, setDeliveries] = useState<Delivery[]>([])
  const [loading, setLoading] = useState(true)
  const [newSecret, setNewSecret] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [busy, setBusy] = useState<string | null>(null)

  function load() {
    setLoading(true)
    Promise.all([
      adminApi.get(`/admin/api/webhooks/${id}`),
      adminApi.get(`/admin/api/webhooks/${id}/deliveries`),
    ])
      .then(([showRes, delivRes]) => {
        setWebhook(showRes.data.data)
        setStats(showRes.data.statistics)
        setDeliveries(delivRes.data.data)
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [id])

  async function handleTest() {
    setBusy('test')
    setActionError(null)
    try {
      await adminApi.post(`/admin/api/webhooks/${id}/test`)
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.message || 'Test failed.')
    } finally {
      setBusy(null)
    }
  }

  async function handleToggle() {
    setBusy('toggle')
    try {
      await adminApi.post(`/admin/api/webhooks/${id}/toggle`)
      load()
    } finally {
      setBusy(null)
    }
  }

  async function handleRotateSecret() {
    if (!confirm('Rotate the secret? Anything verifying signatures with the old secret will start failing.')) return
    setBusy('rotate')
    try {
      const res = await adminApi.post(`/admin/api/webhooks/${id}/rotate-secret`)
      setNewSecret(res.data.data.secret)
    } finally {
      setBusy(null)
    }
  }

  async function handleRetry(deliveryId: number) {
    setActionError(null)
    try {
      await adminApi.post(`/admin/api/webhooks/${id}/retry`, { delivery_id: deliveryId })
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.message || `Could not retry delivery #${deliveryId}.`)
    }
  }

  if (loading) return <Spinner />
  if (!webhook) return <EmptyState title="Webhook not found" />

  return (
    <div className="max-w-2xl">
      <Link to="/webhooks" className="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink mb-4">
        <ArrowLeft size={15} /> Back to webhooks
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-lg font-semibold text-ink break-all">{webhook.url}</h1>
          <p className="text-sm text-ink-soft mt-0.5">{(webhook.events || []).join(', ')}</p>
        </div>
        <Badge tone={webhook.is_active ? 'success' : 'default'}>{webhook.is_active ? 'active' : 'inactive'}</Badge>
      </div>

      {newSecret && (
        <Card className="p-5 mb-6 border-2 border-teal">
          <p className="text-sm font-medium text-ink mb-2">New secret — save it now, it will not be shown again.</p>
          <code className="block bg-canvas p-2 text-xs break-all mb-3">{newSecret}</code>
          <Button variant="secondary" onClick={() => setNewSecret(null)}>Dismiss</Button>
        </Card>
      )}

      {stats && (
        <div className="grid grid-cols-4 gap-3 mb-6">
          <Card className="p-3"><p className="text-xs text-ink-soft">Total</p><p className="text-lg font-semibold text-ink">{stats.total_deliveries}</p></Card>
          <Card className="p-3"><p className="text-xs text-ink-soft">Success</p><p className="text-lg font-semibold text-ink">{stats.successful}</p></Card>
          <Card className="p-3"><p className="text-xs text-ink-soft">Failed</p><p className="text-lg font-semibold text-ink">{stats.failed}</p></Card>
          <Card className="p-3"><p className="text-xs text-ink-soft">Success rate</p><p className="text-lg font-semibold text-ink">{stats.success_rate}%</p></Card>
        </div>
      )}

      <Card className="p-5 mb-6">
        <h2 className="text-sm font-medium text-ink mb-3">Actions</h2>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={handleTest} disabled={busy !== null}>{busy === 'test' ? 'Sending…' : 'Send test event'}</Button>
          <Button variant="secondary" onClick={handleToggle} disabled={busy !== null}>{busy === 'toggle' ? 'Updating…' : webhook.is_active ? 'Deactivate' : 'Activate'}</Button>
          <Button variant="secondary" onClick={handleRotateSecret} disabled={busy !== null}>{busy === 'rotate' ? 'Rotating…' : 'Rotate secret'}</Button>
        </div>
        {actionError && <p className="mt-2 text-xs text-danger">{actionError}</p>}
      </Card>

      <Card className="p-5">
        <h2 className="text-sm font-medium text-ink mb-4">Recent deliveries</h2>
        {deliveries.length === 0 ? (
          <EmptyState title="No deliveries yet" />
        ) : (
          <ul className="space-y-2">
            {deliveries.map((d) => (
              <li key={d.id} className="flex items-center justify-between text-sm border-b border-line last:border-0 pb-2 last:pb-0">
                <span className="text-ink-soft">
                  {new Date(d.created_at).toLocaleString()} {d.response_status ? `· HTTP ${d.response_status}` : ''}
                </span>
                <div className="flex items-center gap-2">
                  <Badge tone={d.status === 'success' ? 'success' : d.status === 'failed' ? 'danger' : 'default'}>{d.status}</Badge>
                  {d.status === 'failed' && (
                    <button onClick={() => handleRetry(d.id)} className="text-teal underline text-xs">Retry</button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}
