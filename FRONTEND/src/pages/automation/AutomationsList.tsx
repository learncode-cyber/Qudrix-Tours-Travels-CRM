import { useEffect, useState, FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Automation {
  id: number
  name: string
  trigger_type: string
  status: 'draft' | 'active' | 'paused' | 'archived'
  is_active: boolean
  logs_count: number
}

interface Summary {
  total_automations: number
  active_automations: number
  total_runs: number
}

const TRIGGER_OPTIONS = ['booking_created', 'customer_added', 'invoice_created', 'payment_received', 'webhook']

export default function AutomationsList() {
  const [automations, setAutomations] = useState<Automation[]>([])
  const [summary, setSummary] = useState<Summary | null>(null)
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState({ name: '', trigger_type: 'booking_created', status: 'draft' })

  function load() {
    setLoading(true)
    Promise.all([api.get('/automations'), api.get('/automation-dashboard/summary')])
      .then(([autoRes, summaryRes]) => {
        setAutomations(autoRes.data.data)
        setSummary(summaryRes.data.data)
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/automations', form)
      setShowForm(false)
      setForm({ name: '', trigger_type: 'booking_created', status: 'draft' })
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Automations</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New automation
        </Button>
      </div>

      {summary && (
        <div className="grid grid-cols-3 gap-4 mb-6">
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Total automations</p>
            <p className="text-lg font-semibold text-ink">{summary.total_automations}</p>
          </Card>
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Active</p>
            <p className="text-lg font-semibold text-ink">{summary.active_automations}</p>
          </Card>
          <Card className="p-4">
            <p className="text-xs text-ink-soft">Total runs</p>
            <p className="text-lg font-semibold text-ink">{summary.total_runs}</p>
          </Card>
        </div>
      )}

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Name</label>
              <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
              {errors.name && <p className="mt-1 text-xs text-danger">{errors.name[0]}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-ink mb-1">Trigger</label>
              <select
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.trigger_type}
                onChange={(e) => setForm((f) => ({ ...f, trigger_type: e.target.value }))}
              >
                {TRIGGER_OPTIONS.map((t) => (
                  <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>
                ))}
              </select>
            </div>
            <p className="text-xs text-ink-soft">Created as a draft — add steps and activate it from the detail page.</p>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Create automation'}
            </Button>
          </form>
        </Card>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : automations.length === 0 ? (
          <EmptyState title="No automations yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Trigger</th>
                <th className="px-4 py-3 font-medium">Runs</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {automations.map((a) => (
                <tr key={a.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">
                    <Link to={`/automations/${a.id}`} className="hover:text-teal">{a.name}</Link>
                  </td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{a.trigger_type.replace(/_/g, ' ')}</td>
                  <td className="px-4 py-3 text-ink-soft">{a.logs_count}</td>
                  <td className="px-4 py-3">
                    <Badge tone={a.status === 'active' ? 'success' : a.status === 'archived' ? 'danger' : 'default'}>{a.status}</Badge>
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
