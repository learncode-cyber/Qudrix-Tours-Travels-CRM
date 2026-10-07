import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface StudentVisaApplication {
  id: number
  student_name: string
  destination_country: string
  is_al_azhar: boolean
  azhar_faculty: string | null
  status: string
  visa_status: string
  application_deadline: string | null
}

const STATUS_SEQUENCE = [
  'inquiry', 'documents_pending', 'application_submitted', 'offer_received',
  'offer_accepted', 'visa_applied', 'visa_approved', 'enrolled',
]

export default function StudentVisaList() {
  const [applications, setApplications] = useState<StudentVisaApplication[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [advanceError, setAdvanceError] = useState<string | null>(null)
  const [form, setForm] = useState({
    student_name: '', student_email: '', destination_country: '',
    is_al_azhar: false, azhar_faculty: '', azhar_level: '', university: '',
  })

  function load() {
    setLoading(true)
    api.get('/student-visas').then((res) => setApplications(res.data.data)).finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/student-visas', form)
      setShowForm(false)
      setForm({ student_name: '', student_email: '', destination_country: '', is_al_azhar: false, azhar_faculty: '', azhar_level: '', university: '' })
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  async function handleAdvance(appId: number, currentStatus: string) {
    const idx = STATUS_SEQUENCE.indexOf(currentStatus)
    const next = STATUS_SEQUENCE[idx + 1]
    if (!next) return
    setAdvanceError(null)
    try {
      await api.post(`/student-visas/${appId}/advance`, { status: next })
      load()
    } catch (err: any) {
      setAdvanceError(err.response?.data?.error || `Could not advance application #${appId}.`)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Student Visa &amp; Al-Azhar Applications</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New application
        </Button>
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Student name" error={errors.student_name}>
              <Input required value={form.student_name} onChange={(e) => setForm((f) => ({ ...f, student_name: e.target.value }))} />
            </Field>
            <Field label="Email" error={errors.student_email}>
              <Input type="email" value={form.student_email} onChange={(e) => setForm((f) => ({ ...f, student_email: e.target.value }))} />
            </Field>
            <Field label="Destination country" error={errors.destination_country}>
              <Input required value={form.destination_country} onChange={(e) => setForm((f) => ({ ...f, destination_country: e.target.value }))} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-ink">
              <input
                type="checkbox"
                checked={form.is_al_azhar}
                onChange={(e) => setForm((f) => ({ ...f, is_al_azhar: e.target.checked }))}
              />
              This is an Al-Azhar admission
            </label>
            {form.is_al_azhar && (
              <>
                <Field label="Azhar faculty" error={errors.azhar_faculty}>
                  <Input
                    required={form.is_al_azhar}
                    value={form.azhar_faculty}
                    onChange={(e) => setForm((f) => ({ ...f, azhar_faculty: e.target.value }))}
                  />
                </Field>
                <Field label="Azhar level" error={errors.azhar_level}>
                  <select
                    className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                    value={form.azhar_level}
                    onChange={(e) => setForm((f) => ({ ...f, azhar_level: e.target.value }))}
                  >
                    <option value="">Select…</option>
                    <option value="preparatory">Preparatory</option>
                    <option value="bachelor">Bachelor</option>
                    <option value="masters">Masters</option>
                    <option value="phd">PhD</option>
                    <option value="arabic_language_institute">Arabic Language Institute</option>
                  </select>
                </Field>
              </>
            )}
            {!form.is_al_azhar && (
              <Field label="University" error={errors.university}>
                <Input value={form.university} onChange={(e) => setForm((f) => ({ ...f, university: e.target.value }))} />
              </Field>
            )}
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Save application'}
            </Button>
          </form>
        </Card>
      )}

      {advanceError && <p className="mb-3 text-sm text-danger">{advanceError}</p>}

      <Card>
        {loading ? (
          <Spinner />
        ) : applications.length === 0 ? (
          <EmptyState title="No applications yet" />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Student</th>
                <th className="px-4 py-3 font-medium">Destination</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Visa status</th>
                <th className="px-4 py-3 font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {applications.map((a) => (
                <tr key={a.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{a.student_name}</td>
                  <td className="px-4 py-3 text-ink-soft">{a.destination_country}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {a.is_al_azhar ? (
                      <span>Al-Azhar{a.azhar_faculty ? ` — ${a.azhar_faculty}` : ''}</span>
                    ) : (
                      'Standard'
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone="default">{a.status.replace(/_/g, ' ')}</Badge>
                  </td>
                  <td className="px-4 py-3 text-ink-soft">{a.visa_status.replace(/_/g, ' ')}</td>
                  <td className="px-4 py-3">
                    {STATUS_SEQUENCE.includes(a.status) && a.status !== 'enrolled' && (
                      <button onClick={() => handleAdvance(a.id, a.status)} className="text-teal underline text-xs">
                        Advance to {STATUS_SEQUENCE[STATUS_SEQUENCE.indexOf(a.status) + 1]?.replace(/_/g, ' ')}
                      </button>
                    )}
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

function Field({ label, error, children }: { label: string; error?: string[]; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-ink mb-1">{label}</label>
      {children}
      {error && <p className="mt-1 text-xs text-danger">{error[0]}</p>}
    </div>
  )
}
