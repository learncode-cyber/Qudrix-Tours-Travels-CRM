import { useEffect, useState, FormEvent } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, Spinner, EmptyState, Button, Input } from '../../components/ui'
import { ArrowLeft } from 'lucide-react'

interface Step {
  id: number
  step_order: number
  action_type: string
  delay_seconds: number | null
  action_config: Record<string, string> | null
}

interface Automation {
  id: number
  name: string
  trigger_type: string
  status: 'draft' | 'active' | 'paused' | 'archived'
  is_active: boolean
  run_count: number
  steps: Step[]
}

// Must match AutomationEngine::executeAction() — any other value falls to
// "Unknown action type". Field names are the config keys the engine reads.
const ACTION_FIELDS: Record<string, { key: string; label: string }[]> = {
  send_email: [{ key: 'to', label: 'To (email)' }, { key: 'subject', label: 'Subject' }],
  send_sms: [{ key: 'phone', label: 'Phone' }, { key: 'message', label: 'Message' }],
  create_task: [{ key: 'title', label: 'Task title' }, { key: 'assignee', label: 'Assignee' }],
  update_customer: [{ key: 'field', label: 'Customer field' }, { key: 'value', label: 'New value' }],
  create_notification: [{ key: 'message', label: 'Message' }],
  webhook: [{ key: 'url', label: 'URL' }],
  delay: [],
}
const ACTION_TYPES = Object.keys(ACTION_FIELDS)

export default function AutomationDetail() {
  const { id } = useParams()
  const [automation, setAutomation] = useState<Automation | null>(null)
  const [loading, setLoading] = useState(true)
  const [stepForm, setStepForm] = useState<{ action_type: string; delay_seconds: string; config: Record<string, string> }>({ action_type: 'send_email', delay_seconds: '0', config: {} })
  const [addingStep, setAddingStep] = useState(false)
  const [runResult, setRunResult] = useState<{ label: string; data: unknown } | null>(null)
  const [running, setRunning] = useState<'test' | 'execute' | null>(null)
  const [statusError, setStatusError] = useState<string | null>(null)

  function load() {
    setLoading(true)
    api.get(`/automations/${id}`).then((res) => setAutomation(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [id])

  async function handleAddStep(e: FormEvent) {
    e.preventDefault()
    setAddingStep(true)
    try {
      const nextOrder = (automation?.steps.length || 0) + 1
      await api.post(`/automations/${id}/steps`, {
        step_order: nextOrder,
        action_type: stepForm.action_type,
        delay_seconds: stepForm.delay_seconds || 0,
        action_config: Object.fromEntries(
          Object.entries(stepForm.config).filter(([k, v]) => v !== '' && ACTION_FIELDS[stepForm.action_type].some((f) => f.key === k))
        ),
      })
      setStepForm({ action_type: 'send_email', delay_seconds: '0', config: {} })
      load()
    } finally {
      setAddingStep(false)
    }
  }

  async function handleRemoveStep(stepId: number) {
    await api.delete(`/automations/${id}/steps/${stepId}`)
    load()
  }

  async function handleMove(index: number, dir: -1 | 1) {
    const ordered = [...(automation?.steps || [])].sort((a, b) => a.step_order - b.step_order)
    const a = ordered[index]
    const b = ordered[index + dir]
    if (!a || !b) return
    // Swap the two step_order values.
    await Promise.all([
      api.put(`/automations/${id}/steps/${a.id}`, { step_order: b.step_order }),
      api.put(`/automations/${id}/steps/${b.id}`, { step_order: a.step_order }),
    ])
    load()
  }

  async function handleStatusChange(status: string) {
    setStatusError(null)
    try {
      await api.put(`/automations/${id}`, { status, is_active: status === 'active' })
      load()
    } catch (err: any) {
      setStatusError(err.response?.data?.error || 'Could not update status.')
    }
  }

  async function handleTest() {
    setRunning('test')
    try {
      const res = await api.post(`/automations/${id}/test`, { test_data: {} })
      setRunResult({ label: 'Test run result', data: res.data.data })
    } finally {
      setRunning(null)
    }
  }

  async function handleExecute() {
    setRunning('execute')
    try {
      const res = await api.post(`/automations/${id}/execute`, { trigger_data: {} })
      setRunResult({ label: 'Execution result', data: res.data.data })
      load()
    } finally {
      setRunning(null)
    }
  }

  if (loading) return <Spinner />
  if (!automation) return <EmptyState title="Automation not found" />

  return (
    <div className="max-w-2xl">
      <Link to="/automations" className="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink mb-4">
        <ArrowLeft size={15} /> Back to automations
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold text-ink">{automation.name}</h1>
          <p className="text-sm text-ink-soft mt-0.5 capitalize">
            Trigger: {automation.trigger_type.replace(/_/g, ' ')} · {automation.run_count} run(s)
          </p>
        </div>
        <Badge tone={automation.status === 'active' ? 'success' : automation.status === 'archived' ? 'danger' : 'default'}>
          {automation.status}
        </Badge>
      </div>

      <Card className="p-5 mb-6">
        <h2 className="text-sm font-medium text-ink mb-3">Status</h2>
        <div className="flex flex-wrap gap-2">
          {['draft', 'active', 'paused', 'archived'].map((s) => (
            <button
              key={s}
              onClick={() => handleStatusChange(s)}
              className={`px-3 py-1.5 text-xs font-medium capitalize border ${
                automation.status === s ? 'border-teal text-teal' : 'border-line text-ink-soft hover:text-ink'
              }`}
            >
              {s}
            </button>
          ))}
        </div>
        {statusError && <p className="mt-2 text-xs text-danger">{statusError}</p>}
      </Card>

      <Card className="p-5 mb-6">
        <h2 className="text-sm font-medium text-ink mb-4">Steps</h2>
        {automation.steps.length === 0 ? (
          <EmptyState title="No steps yet" description="An automation with no steps runs but does nothing." />
        ) : (
          <ol className="space-y-2 mb-5">
            {[...automation.steps]
              .sort((a, b) => a.step_order - b.step_order)
              .map((step, index, all) => (
                <li key={step.id} className="flex items-center justify-between text-sm border-b border-line last:border-0 pb-2 last:pb-0">
                  <span className="text-ink">
                    #{step.step_order} — {step.action_type.replace(/_/g, ' ')}
                    {step.delay_seconds ? ` (after ${step.delay_seconds}s)` : ''}
                    {step.action_config && Object.keys(step.action_config).length > 0 && (
                      <span className="block text-xs text-ink-soft">
                        {Object.entries(step.action_config).map(([k, v]) => `${k}: ${v}`).join(' · ')}
                      </span>
                    )}
                  </span>
                  <span className="space-x-3 whitespace-nowrap">
                    <button onClick={() => handleMove(index, -1)} disabled={index === 0} className="text-teal underline text-xs disabled:opacity-40">Up</button>
                    <button onClick={() => handleMove(index, 1)} disabled={index === all.length - 1} className="text-teal underline text-xs disabled:opacity-40">Down</button>
                    <button onClick={() => handleRemoveStep(step.id)} className="text-danger underline text-xs">
                      Remove
                    </button>
                  </span>
                </li>
              ))}
          </ol>
        )}

        <form onSubmit={handleAddStep} className="flex flex-wrap items-end gap-2 border-t border-line pt-4">
          <div className="flex-1">
            <label className="block text-xs font-medium text-ink mb-1">Action</label>
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={stepForm.action_type}
              onChange={(e) => setStepForm((f) => ({ ...f, action_type: e.target.value, config: {} }))}
            >
              {ACTION_TYPES.map((t) => (
                <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>
              ))}
            </select>
          </div>
          <div className="w-28">
            <label className="block text-xs font-medium text-ink mb-1">Delay (s)</label>
            <Input type="number" min={0} value={stepForm.delay_seconds} onChange={(e) => setStepForm((f) => ({ ...f, delay_seconds: e.target.value }))} />
          </div>
          {ACTION_FIELDS[stepForm.action_type].map((field) => (
            <div key={field.key} className="w-full">
              <label className="block text-xs font-medium text-ink mb-1">{field.label}</label>
              <Input
                value={stepForm.config[field.key] || ''}
                onChange={(e) => setStepForm((f) => ({ ...f, config: { ...f.config, [field.key]: e.target.value } }))}
              />
            </div>
          ))}
          <Button type="submit" disabled={addingStep}>
            {addingStep ? 'Adding…' : 'Add step'}
          </Button>
        </form>
      </Card>

      <Card className="p-5">
        <h2 className="text-sm font-medium text-ink mb-4">Run</h2>
        <div className="flex gap-2 mb-4">
          <Button variant="secondary" onClick={handleTest} disabled={running !== null}>
            {running === 'test' ? 'Testing…' : 'Test run'}
          </Button>
          <Button onClick={handleExecute} disabled={running !== null}>
            {running === 'execute' ? 'Executing…' : 'Execute now'}
          </Button>
        </div>
        {runResult && (
          <div>
            <p className="text-xs font-medium text-ink mb-1">{runResult.label}</p>
            <pre className="bg-canvas p-3 text-xs overflow-x-auto">{JSON.stringify(runResult.data, null, 2)}</pre>
          </div>
        )}
      </Card>
    </div>
  )
}
