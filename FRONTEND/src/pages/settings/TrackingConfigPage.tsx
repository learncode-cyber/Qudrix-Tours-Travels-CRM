import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, Spinner } from '../../components/ui'

export default function TrackingConfigPage() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [metaConfigured, setMetaConfigured] = useState(false)
  const [ga4Configured, setGa4Configured] = useState(false)
  const [form, setForm] = useState({
    meta_pixel_id: '', meta_conversions_api_token: '', ga4_measurement_id: '', ga4_api_secret: '',
  })

  function load() {
    setLoading(true)
    api
      .get('/tracking-config')
      .then((res) => {
        setMetaConfigured(res.data.meta_configured)
        setGa4Configured(res.data.ga4_configured)
        if (res.data.data) {
          setForm((f) => ({
            ...f,
            meta_pixel_id: res.data.data.meta_pixel_id || '',
            ga4_measurement_id: res.data.data.ga4_measurement_id || '',
          }))
        }
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    try {
      // Don't send blank secret fields — an empty string would overwrite
      // (and effectively delete) an already-saved token/secret, which
      // contradicts the "leave blank to keep current" placeholder text.
      const payload: Record<string, string> = {
        meta_pixel_id: form.meta_pixel_id,
        ga4_measurement_id: form.ga4_measurement_id,
      }
      if (form.meta_conversions_api_token) payload.meta_conversions_api_token = form.meta_conversions_api_token
      if (form.ga4_api_secret) payload.ga4_api_secret = form.ga4_api_secret

      await api.put('/tracking-config', payload)
      setForm((f) => ({ ...f, meta_conversions_api_token: '', ga4_api_secret: '' }))
      load()
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Spinner />

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold text-ink mb-2">Marketing tracking</h1>
      <p className="text-sm text-ink-soft mb-6">
        Connect Meta Conversions API and GA4 to attribute leads, applications, and payments back to your ad campaigns.
      </p>

      <div className="flex gap-2 mb-6">
        <Badge tone={metaConfigured ? 'success' : 'default'}>Meta: {metaConfigured ? 'configured' : 'not configured'}</Badge>
        <Badge tone={ga4Configured ? 'success' : 'default'}>GA4: {ga4Configured ? 'configured' : 'not configured'}</Badge>
      </div>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <h2 className="text-sm font-medium text-ink mb-3">Meta Pixel / Conversions API</h2>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-ink mb-1">Pixel ID</label>
                <Input
                  value={form.meta_pixel_id}
                  onChange={(e) => setForm((f) => ({ ...f, meta_pixel_id: e.target.value }))}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-ink mb-1">Conversions API access token</label>
                <Input
                  type="password"
                  placeholder={metaConfigured ? '••••••••  (leave blank to keep current)' : ''}
                  value={form.meta_conversions_api_token}
                  onChange={(e) => setForm((f) => ({ ...f, meta_conversions_api_token: e.target.value }))}
                />
              </div>
            </div>
          </div>

          <div className="border-t border-line pt-4">
            <h2 className="text-sm font-medium text-ink mb-3">Google Analytics 4</h2>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-ink mb-1">Measurement ID</label>
                <Input
                  value={form.ga4_measurement_id}
                  onChange={(e) => setForm((f) => ({ ...f, ga4_measurement_id: e.target.value }))}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-ink mb-1">API secret</label>
                <Input
                  type="password"
                  placeholder={ga4Configured ? '••••••••  (leave blank to keep current)' : ''}
                  value={form.ga4_api_secret}
                  onChange={(e) => setForm((f) => ({ ...f, ga4_api_secret: e.target.value }))}
                />
              </div>
            </div>
          </div>

          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save tracking settings'}
          </Button>
        </form>
      </Card>
    </div>
  )
}
