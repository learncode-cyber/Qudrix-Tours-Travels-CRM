import { useEffect, useState, FormEvent, Fragment } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface ApiKey {
  id: number
  name: string
  key: string
  description: string | null
  permissions: string[] | null
  allowed_ips: string[] | null
  is_active: boolean
  used_count: number
  last_used_at: string | null
  expires_at: string | null
}

export default function ApiKeysPage() {
  const [keys, setKeys] = useState<ApiKey[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState({ name: '', description: '', permissions: '', allowed_ips: '' })
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editForm, setEditForm] = useState({ permissions: '', allowed_ips: '' })
  const [savingEdit, setSavingEdit] = useState(false)
  // The secret is only ever returned once, at creation — this is not
  // fetched from anywhere else, so it must be held in local state and
  // shown to the user before it's gone for good.
  const [newSecret, setNewSecret] = useState<{ key: string; secret: string } | null>(null)

  function load() {
    setLoading(true)
    api.get('/api-keys').then((res) => setKeys(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  // Comma-separated text is friendlier to type than a JSON array, and the
  // backend just wants a plain array of strings either way.
  function toArray(commaSeparated: string): string[] | undefined {
    const items = commaSeparated.split(',').map((s) => s.trim()).filter(Boolean)
    return items.length > 0 ? items : undefined
  }

  function openEdit(k: ApiKey) {
    setEditingId(k.id)
    setEditForm({
      permissions: (k.permissions || []).join(', '),
      allowed_ips: (k.allowed_ips || []).join(', '),
    })
  }

  async function handleSaveEdit(e: FormEvent, id: number) {
    e.preventDefault()
    setSavingEdit(true)
    try {
      await api.patch(`/api-keys/${id}`, {
        permissions: toArray(editForm.permissions) || [],
        allowed_ips: toArray(editForm.allowed_ips) || [],
      })
      setEditingId(null)
      load()
    } finally {
      setSavingEdit(false)
    }
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    try {
      const res = await api.post('/api-keys', {
        name: form.name,
        description: form.description,
        permissions: toArray(form.permissions),
        allowed_ips: toArray(form.allowed_ips),
      })
      setNewSecret({ key: res.data.data.key, secret: res.data.data.secret })
      setForm({ name: '', description: '', permissions: '', allowed_ips: '' })
      setShowForm(false)
      load()
    } finally {
      setSubmitting(false)
    }
  }

  async function handleRevoke(id: number) {
    if (!confirm('Revoke this API key? Anything using it will stop working immediately.')) return
    await api.post(`/api-keys/${id}/revoke`)
    load()
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">API Keys</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New API key
        </Button>
      </div>

      {newSecret && (
        <Card className="p-5 mb-6 border-2 border-teal">
          <p className="text-sm font-medium text-ink mb-2">Save this secret now — it will not be shown again.</p>
          <p className="text-xs text-ink-soft mb-1">Key</p>
          <code className="block bg-canvas p-2 text-xs mb-2 break-all">{newSecret.key}</code>
          <p className="text-xs text-ink-soft mb-1">Secret</p>
          <code className="block bg-canvas p-2 text-xs mb-3 break-all">{newSecret.secret}</code>
          <Button variant="secondary" onClick={() => setNewSecret(null)}>
            I've saved it, dismiss
          </Button>
        </Card>
      )}

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Name</label>
              <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Description</label>
              <Input value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Permissions (comma-separated)</label>
              <Input placeholder="e.g. bookings.read, invoices.read" value={form.permissions} onChange={(e) => setForm((f) => ({ ...f, permissions: e.target.value }))} />
              <p className="mt-1 text-xs text-ink-soft">Leave blank for no scope restrictions.</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Allowed IPs (comma-separated)</label>
              <Input placeholder="e.g. 203.0.113.4, 198.51.100.0" value={form.allowed_ips} onChange={(e) => setForm((f) => ({ ...f, allowed_ips: e.target.value }))} />
              <p className="mt-1 text-xs text-ink-soft">Leave blank to allow any IP.</p>
            </div>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Creating…' : 'Create key'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : keys.length === 0 ? (
          <EmptyState title="No API keys yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Key</th>
                <th className="px-4 py-3 font-medium">Used</th>
                <th className="px-4 py-3 font-medium">Last used</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {keys.map((k) => (
                <Fragment key={k.id}>
                  <tr key={k.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                    <td className="px-4 py-3 font-medium text-ink">{k.name}</td>
                    <td className="px-4 py-3 text-ink-soft font-mono text-xs">{k.key}</td>
                    <td className="px-4 py-3 text-ink-soft">{k.used_count}</td>
                    <td className="px-4 py-3 text-ink-soft">{k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : 'Never'}</td>
                    <td className="px-4 py-3">
                      <Badge tone={k.is_active ? 'success' : 'default'}>{k.is_active ? 'active' : 'revoked'}</Badge>
                    </td>
                    <td className="px-4 py-3 space-x-3">
                      {k.is_active && (
                        <>
                          <button onClick={() => openEdit(k)} className="text-teal underline text-xs">
                            Edit scope
                          </button>
                          <button onClick={() => handleRevoke(k.id)} className="text-danger underline text-xs">
                            Revoke
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                  {editingId === k.id && (
                    <tr key={`${k.id}-edit`} className="border-b border-line bg-canvas/40">
                      <td colSpan={6} className="px-4 py-4">
                        <form onSubmit={(e) => handleSaveEdit(e, k.id)} className="grid grid-cols-2 gap-3">
                          <div>
                            <label className="block text-xs font-medium text-ink mb-1">Permissions (comma-separated)</label>
                            <Input
                              value={editForm.permissions}
                              onChange={(e) => setEditForm((f) => ({ ...f, permissions: e.target.value }))}
                            />
                          </div>
                          <div>
                            <label className="block text-xs font-medium text-ink mb-1">Allowed IPs (comma-separated)</label>
                            <Input
                              value={editForm.allowed_ips}
                              onChange={(e) => setEditForm((f) => ({ ...f, allowed_ips: e.target.value }))}
                            />
                          </div>
                          <div className="col-span-2 flex gap-2">
                            <Button type="submit" disabled={savingEdit}>
                              {savingEdit ? 'Saving…' : 'Save'}
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => setEditingId(null)}>
                              Cancel
                            </Button>
                          </div>
                        </form>
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  )
}
