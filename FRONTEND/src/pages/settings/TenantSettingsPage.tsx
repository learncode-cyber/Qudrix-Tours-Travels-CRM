import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, Spinner } from '../../components/ui'

interface Plan {
  id: number
  name: string
  price: number
  currency: string
  billing_cycle: string
}

interface Tenant {
  id: number
  name: string
  description: string | null
  timezone: string
  currency: string
  language: string
  logo_url: string | null
  primary_color: string | null
  subscriptionPlan: Plan | null
}

export default function TenantSettingsPage() {
  const [tenant, setTenant] = useState<Tenant | null>(null)
  const [plans, setPlans] = useState<Plan[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({ name: '', description: '', timezone: '', currency: '', language: 'en', logo_url: '', primary_color: '' })

  function load() {
    setLoading(true)
    Promise.all([api.get('/tenant'), api.get('/subscription-plans')]).then(([tenantRes, plansRes]) => {
      const t: Tenant = tenantRes.data.data
      setTenant(t)
      setPlans(plansRes.data.data)
      setForm({
        name: t.name || '', description: t.description || '', timezone: t.timezone || '',
        currency: t.currency || '', language: t.language || 'en', logo_url: t.logo_url || '', primary_color: t.primary_color || '',
      })
    }).finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    try {
      await api.put('/tenant', form)
      load()
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Spinner />
  if (!tenant) return null

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold text-ink mb-6">Organization settings</h1>

      <Card className="p-5 mb-6">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-ink">Current plan</p>
            <p className="text-xs text-ink-soft mt-0.5">{tenant.subscriptionPlan?.name || 'No plan assigned'}</p>
          </div>
          {tenant.subscriptionPlan && (
            <Badge tone="success">
              {tenant.subscriptionPlan.price} {tenant.subscriptionPlan.currency} / {tenant.subscriptionPlan.billing_cycle}
            </Badge>
          )}
        </div>
        {plans.length > 0 && (
          <div className="mt-4 pt-4 border-t border-line">
            <p className="text-xs text-ink-soft mb-2">Available plans</p>
            <ul className="text-xs text-ink-soft space-y-1">
              {plans.map((p) => (
                <li key={p.id}>
                  {p.name} — {p.price} {p.currency} / {p.billing_cycle}
                </li>
              ))}
            </ul>
          </div>
        )}
      </Card>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <Field label="Organization name">
            <Input required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          </Field>
          <Field label="Description">
            <textarea
              rows={3}
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Timezone">
              <Input value={form.timezone} onChange={(e) => setForm((f) => ({ ...f, timezone: e.target.value }))} />
            </Field>
            <Field label="Currency">
              <Input maxLength={3} value={form.currency} onChange={(e) => setForm((f) => ({ ...f, currency: e.target.value.toUpperCase() }))} />
            </Field>
          </div>
          <Field label="Language">
            <select
              className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
              value={form.language}
              onChange={(e) => setForm((f) => ({ ...f, language: e.target.value }))}
            >
              <option value="en">English</option>
              <option value="bn">বাংলা (Bengali)</option>
              <option value="ar">العربية (Arabic)</option>
            </select>
          </Field>
          <Field label="Logo URL">
            <Input value={form.logo_url} onChange={(e) => setForm((f) => ({ ...f, logo_url: e.target.value }))} />
          </Field>
          <Field label="Primary color">
            <Input type="color" className="h-10" value={form.primary_color || '#0f766e'} onChange={(e) => setForm((f) => ({ ...f, primary_color: e.target.value }))} />
          </Field>
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save settings'}
          </Button>
        </form>
      </Card>
    </div>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-ink mb-1">{label}</label>
      {children}
    </div>
  )
}
