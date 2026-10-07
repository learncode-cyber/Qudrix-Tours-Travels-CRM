import { useEffect, useState, FormEvent, Fragment } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface AIProvider {
  id: number
  provider: 'gemini' | 'openai' | 'anthropic'
  name: string
  default_model: string | null
  cost_per_1k_prompt_tokens: string | number | null
  cost_per_1k_completion_tokens: string | number | null
  is_active: boolean
  is_default: boolean
  last_tested_at: string | null
  last_test_status: 'not_tested' | 'success' | 'failed'
  last_test_detail: string | null
}

interface UsageStats {
  total_calls: number
  successful: number
  failed: number
  blocked: number
  total_estimated_cost: number
  by_feature: Record<string, number>
}

const STATUS_TONE: Record<string, 'success' | 'danger' | 'default'> = {
  success: 'success', failed: 'danger', not_tested: 'default',
}

export default function AIProvidersPage() {
  const [providers, setProviders] = useState<AIProvider[]>([])
  const [stats, setStats] = useState<UsageStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [testingId, setTestingId] = useState<number | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const EMPTY_FORM = { provider: 'openai', name: '', api_key: '', default_model: '', cost_prompt: '', cost_completion: '', is_default: false }
  const [form, setForm] = useState(EMPTY_FORM)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [costForm, setCostForm] = useState({ cost_prompt: '', cost_completion: '' })
  const [savingCost, setSavingCost] = useState(false)

  function load() {
    setLoading(true)
    Promise.all([api.get('/ai-providers'), api.get('/ai-providers/usage-stats')])
      .then(([provRes, statsRes]) => {
        setProviders(provRes.data.data)
        setStats(statsRes.data.data)
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      const { cost_prompt, cost_completion, ...rest } = form
      await api.post('/ai-providers', {
        ...rest,
        cost_per_1k_prompt_tokens: cost_prompt === '' ? null : Number(cost_prompt),
        cost_per_1k_completion_tokens: cost_completion === '' ? null : Number(cost_completion),
      })
      setShowForm(false)
      setForm(EMPTY_FORM)
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  async function handleTest(id: number) {
    setTestingId(id)
    try {
      await api.post(`/ai-providers/${id}/test`)
      load()
    } finally {
      setTestingId(null)
    }
  }

  function startEditCosts(p: AIProvider) {
    setEditingId(p.id)
    setErrors({})
    setCostForm({
      cost_prompt: p.cost_per_1k_prompt_tokens == null ? '' : String(p.cost_per_1k_prompt_tokens),
      cost_completion: p.cost_per_1k_completion_tokens == null ? '' : String(p.cost_per_1k_completion_tokens),
    })
  }

  async function saveCosts(id: number) {
    setSavingCost(true)
    setErrors({})
    try {
      await api.put(`/ai-providers/${id}`, {
        cost_per_1k_prompt_tokens: costForm.cost_prompt === '' ? null : Number(costForm.cost_prompt),
        cost_per_1k_completion_tokens: costForm.cost_completion === '' ? null : Number(costForm.cost_completion),
      })
      setEditingId(null)
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSavingCost(false)
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Remove this AI provider?')) return
    await api.delete(`/ai-providers/${id}`)
    load()
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">AI Providers</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New provider
        </Button>
      </div>

      {stats && (
        <div className="grid grid-cols-4 gap-4 mb-6">
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Total calls</p>
            <p className="text-lg font-semibold text-ink">{stats.total_calls}</p>
          </Card>
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Successful</p>
            <p className="text-lg font-semibold text-ink">{stats.successful}</p>
          </Card>
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Failed / blocked</p>
            <p className="text-lg font-semibold text-ink">{stats.failed + stats.blocked}</p>
          </Card>
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Estimated cost</p>
            <p className="text-lg font-semibold text-ink">{stats.total_estimated_cost}</p>
          </Card>
        </div>
      )}

      {stats && Object.keys(stats.by_feature || {}).length > 0 && (
        <Card className="p-4 mb-6">
          <p className="text-xs text-ink-soft mb-2">Calls by feature</p>
          <ul className="text-sm text-ink space-y-1">
            {Object.entries(stats.by_feature).map(([feature, calls]) => (
              <li key={feature} className="flex justify-between">
                <span>{feature}</span>
                <span className="text-ink-soft">{calls}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Provider" error={errors.provider}>
              <select
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.provider}
                onChange={(e) => setForm((f) => ({ ...f, provider: e.target.value }))}
              >
                <option value="openai">OpenAI</option>
                <option value="anthropic">Anthropic</option>
                <option value="gemini">Google Gemini</option>
              </select>
            </Field>
            <Field label="Name" error={errors.name}>
              <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </Field>
            <Field label="API key" error={errors.api_key}>
              <Input type="password" required value={form.api_key} onChange={(e) => setForm((f) => ({ ...f, api_key: e.target.value }))} />
            </Field>
            <Field label="Default model" error={errors.default_model}>
              <Input placeholder="e.g. gpt-4o, claude-sonnet-4-6, gemini-1.5-pro" value={form.default_model} onChange={(e) => setForm((f) => ({ ...f, default_model: e.target.value }))} />
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Cost / 1K prompt tokens" error={errors.cost_per_1k_prompt_tokens}>
                <Input type="number" min="0" step="0.000001" value={form.cost_prompt} onChange={(e) => setForm((f) => ({ ...f, cost_prompt: e.target.value }))} />
              </Field>
              <Field label="Cost / 1K completion tokens" error={errors.cost_per_1k_completion_tokens}>
                <Input type="number" min="0" step="0.000001" value={form.cost_completion} onChange={(e) => setForm((f) => ({ ...f, cost_completion: e.target.value }))} />
              </Field>
            </div>
            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={form.is_default} onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked }))} />
              Make this the default provider
            </label>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save provider'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : providers.length === 0 ? (
          <EmptyState title="No AI providers configured" description="Add one to enable AI Sales Agent, Copilot, and package recommendation features." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Provider</th>
                <th className="px-4 py-3 font-medium">Model</th>
                <th className="px-4 py-3 font-medium">Cost / 1K (in / out)</th>
                <th className="px-4 py-3 font-medium">Connection</th>
                <th className="px-4 py-3 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {providers.map((p) => (
                <Fragment key={p.id}>
                <tr className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">
                    {p.name} {p.is_default && <Badge tone="success">default</Badge>}
                  </td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{p.provider}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.default_model || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {p.cost_per_1k_prompt_tokens == null && p.cost_per_1k_completion_tokens == null
                      ? 'not set'
                      : `${p.cost_per_1k_prompt_tokens ?? '—'} / ${p.cost_per_1k_completion_tokens ?? '—'}`}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[p.last_test_status]}>{p.last_test_status.replace('_', ' ')}</Badge>
                    {p.last_test_detail && <p className="text-xs text-ink-soft mt-0.5">{p.last_test_detail}</p>}
                  </td>
                  <td className="px-4 py-3 space-x-3">
                    <button onClick={() => (editingId === p.id ? setEditingId(null) : startEditCosts(p))} className="text-teal underline text-xs">
                      {editingId === p.id ? 'Cancel' : 'Edit costs'}
                    </button>
                    <button onClick={() => handleTest(p.id)} disabled={testingId === p.id} className="text-teal underline text-xs">
                      {testingId === p.id ? 'Testing…' : 'Test'}
                    </button>
                    <button onClick={() => handleDelete(p.id)} className="text-danger underline text-xs">
                      Remove
                    </button>
                  </td>
                </tr>
                {editingId === p.id && (
                  <tr className="border-b border-line bg-canvas/50">
                    <td colSpan={6} className="px-4 py-3">
                      <div className="flex items-end gap-3 max-w-xl">
                        <Field label="Cost / 1K prompt tokens" error={errors.cost_per_1k_prompt_tokens}>
                          <Input type="number" min="0" step="0.000001" value={costForm.cost_prompt} onChange={(e) => setCostForm((f) => ({ ...f, cost_prompt: e.target.value }))} />
                        </Field>
                        <Field label="Cost / 1K completion tokens" error={errors.cost_per_1k_completion_tokens}>
                          <Input type="number" min="0" step="0.000001" value={costForm.cost_completion} onChange={(e) => setCostForm((f) => ({ ...f, cost_completion: e.target.value }))} />
                        </Field>
                        <Button type="button" onClick={() => saveCosts(p.id)} disabled={savingCost}>
                          {savingCost ? 'Saving…' : 'Save'}
                        </Button>
                      </div>
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

function Field({ label, error, children }: { label: string; error?: string[]; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-ink mb-1">{label}</label>
      {children}
      {error && <p className="mt-1 text-xs text-danger">{error[0]}</p>}
    </div>
  )
}
