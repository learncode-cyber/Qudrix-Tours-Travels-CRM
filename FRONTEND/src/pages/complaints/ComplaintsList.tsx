import { useEffect, useState, FormEvent } from 'react'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Complaint {
  id: number
  title: string
  description: string
  category: string
  priority: 'low' | 'medium' | 'high' | 'critical'
  status: 'open' | 'in_progress' | 'escalated' | 'resolved' | 'closed'
  involves_compensation: boolean
  approval_status: string
  sla_deadline: string | null
  assignedStaff: StaffUser | null
}

interface Customer {
  id: number
  name: string
}

interface BookingOption {
  id: number
  booking_number: string
}

interface StaffUser {
  id: number
  name: string
  email: string
}

const STATUS_TONE: Record<string, 'success' | 'danger' | 'default'> = {
  open: 'default', in_progress: 'default', escalated: 'danger', resolved: 'success', closed: 'success',
}

export default function ComplaintsList() {
  const [complaints, setComplaints] = useState<Complaint[]>([])
  const [statusFilter, setStatusFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [customers, setCustomers] = useState<Customer[]>([])
  const [bookings, setBookings] = useState<BookingOption[]>([])
  const [staff, setStaff] = useState<StaffUser[]>([])
  const [form, setForm] = useState({ customer_id: '', booking_id: '', title: '', description: '' })
  const [resolveDrafts, setResolveDrafts] = useState<Record<number, string>>({})
  const [actionError, setActionError] = useState<string | null>(null)

  function load() {
    setLoading(true)
    api
      .get('/complaints', { params: statusFilter ? { status: statusFilter } : {} })
      .then((res) => setComplaints(res.data.data))
      .finally(() => setLoading(false))
  }

  useEffect(load, [statusFilter])

  useEffect(() => {
    api.get('/customers').then((res) => setCustomers(res.data.data))
    api.get('/bookings').then((res) => setBookings(res.data.data))
    api.get('/users').then((res) => setStaff(res.data.data))
  }, [])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      await api.post('/complaints', form)
      setShowForm(false)
      setForm({ customer_id: '', booking_id: '', title: '', description: '' })
      load()
    } catch (err: any) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {})
    } finally {
      setSubmitting(false)
    }
  }

  async function handleStatusChange(id: number, status: string) {
    setActionError(null)
    try {
      await api.put(`/complaints/${id}/status`, { status })
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || `Could not update complaint #${id}.`)
    }
  }

  async function handleResolve(id: number) {
    const resolution = resolveDrafts[id]
    if (!resolution) return
    setActionError(null)
    try {
      await api.post(`/complaints/${id}/resolve`, { resolution })
      setResolveDrafts((d) => ({ ...d, [id]: '' }))
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || `Could not resolve complaint #${id}.`)
    }
  }

  async function handleAssign(id: number, userId: string) {
    if (!userId) return
    setActionError(null)
    try {
      await api.post(`/complaints/${id}/assign`, { user_id: userId })
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || `Could not assign complaint #${id}.`)
    }
  }

  const [compensationTarget, setCompensationTarget] = useState<number | null>(null)
  const [compensationAmount, setCompensationAmount] = useState('')
  const [approvingCompensation, setApprovingCompensation] = useState(false)

  function openCompensationModal(id: number) {
    setCompensationAmount('')
    setActionError(null)
    setCompensationTarget(id)
  }

  async function handleApproveCompensation(e: FormEvent) {
    e.preventDefault()
    if (!compensationTarget) return
    setApprovingCompensation(true)
    setActionError(null)
    try {
      await api.post(`/complaints/${compensationTarget}/approve-compensation`, { amount: compensationAmount })
      setCompensationTarget(null)
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || `Could not approve compensation for complaint #${compensationTarget}.`)
    } finally {
      setApprovingCompensation(false)
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Complaints</h1>
        <Button onClick={() => setShowForm((s) => !s)}>
          <Plus size={16} /> New complaint
        </Button>
      </div>

      <div className="flex gap-2 mb-4">
        {['', 'open', 'in_progress', 'escalated', 'resolved', 'closed'].map((s) => (
          <button
            key={s}
            onClick={() => setStatusFilter(s)}
            className={`px-3 py-1.5 text-xs font-medium capitalize border ${
              statusFilter === s ? 'border-teal text-teal' : 'border-line text-ink-soft hover:text-ink'
            }`}
          >
            {s || 'all'}
          </button>
        ))}
      </div>

      {showForm && (
        <Card className="p-6 mb-6 max-w-lg">
          <form onSubmit={handleSubmit} className="space-y-4">
            <Field label="Customer" error={errors.customer_id}>
              <select
                required
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.customer_id}
                onChange={(e) => setForm((f) => ({ ...f, customer_id: e.target.value }))}
              >
                <option value="">Select customer…</option>
                {customers.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </Field>
            <Field label="Booking" error={errors.booking_id}>
              <select
                required
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.booking_id}
                onChange={(e) => setForm((f) => ({ ...f, booking_id: e.target.value }))}
              >
                <option value="">Select booking…</option>
                {bookings.map((b) => (
                  <option key={b.id} value={b.id}>{b.booking_number}</option>
                ))}
              </select>
            </Field>
            <Field label="Title" error={errors.title}>
              <Input required value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
            </Field>
            <Field label="Description" error={errors.description}>
              <textarea
                required
                rows={4}
                className="w-full border border-line px-3 py-2 text-sm text-ink focus:border-teal"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </Field>
            <p className="text-xs text-ink-soft">Category and priority are set automatically based on the description.</p>
            <Button type="submit" disabled={submitting}>
              {submitting ? 'Saving…' : 'Submit complaint'}
            </Button>
          </form>
        </Card>
      )}

      {actionError && <p className="mb-3 text-sm text-danger">{actionError}</p>}

      <Card>
        {loading ? (
          <Spinner />
        ) : complaints.length === 0 ? (
          <EmptyState title="No complaints" description="Nothing matches this filter." />
        ) : (
          <div className="divide-y divide-line">
            {complaints.map((c) => (
              <div key={c.id} className="p-4">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <p className="font-medium text-ink">{c.title}</p>
                    <p className="text-xs text-ink-soft mt-0.5 capitalize">
                      {c.category} · {c.priority} priority
                      {c.sla_deadline && ` · SLA: ${new Date(c.sla_deadline).toLocaleString()}`}
                      {c.assignedStaff && ` · Assigned to ${c.assignedStaff.name}`}
                    </p>
                  </div>
                  <Badge tone={STATUS_TONE[c.status] || 'default'}>{c.status.replace('_', ' ')}</Badge>
                </div>
                <p className="text-sm text-ink-soft mb-3">{c.description}</p>

                <div className="flex flex-wrap items-center gap-2">
                  {c.status !== 'resolved' && c.status !== 'closed' && (
                    <>
                      <select
                        className="border border-line px-2 py-1 text-xs text-ink"
                        defaultValue=""
                        onChange={(e) => handleAssign(c.id, e.target.value)}
                      >
                        <option value="">Assign to…</option>
                        {staff.map((s) => (
                          <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                      </select>
                      <select
                        className="border border-line px-2 py-1 text-xs text-ink"
                        value={c.status}
                        onChange={(e) => handleStatusChange(c.id, e.target.value)}
                      >
                        <option value="open">Open</option>
                        <option value="in_progress">In progress</option>
                        <option value="escalated">Escalated</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                      </select>
                      {c.involves_compensation && c.approval_status === 'pending' && (
                        <button onClick={() => openCompensationModal(c.id)} className="text-teal underline text-xs">
                          Approve compensation
                        </button>
                      )}
                      <Input
                        placeholder="Resolution note…"
                        className="text-xs w-56"
                        value={resolveDrafts[c.id] || ''}
                        onChange={(e) => setResolveDrafts((d) => ({ ...d, [c.id]: e.target.value }))}
                      />
                      <button onClick={() => handleResolve(c.id)} className="text-teal underline text-xs">
                        Resolve
                      </button>
                    </>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {compensationTarget !== null && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50" onClick={() => setCompensationTarget(null)}>
          <div className="bg-surface border border-line p-6 w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
            <h2 className="text-sm font-medium text-ink mb-4">Approve compensation for complaint #{compensationTarget}</h2>
            <form onSubmit={handleApproveCompensation} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-ink mb-1">Amount</label>
                <Input
                  type="number"
                  step="0.01"
                  min="0.01"
                  required
                  autoFocus
                  value={compensationAmount}
                  onChange={(e) => setCompensationAmount(e.target.value)}
                />
              </div>
              <div className="flex gap-2">
                <Button type="submit" disabled={approvingCompensation}>
                  {approvingCompensation ? 'Approving…' : 'Approve'}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setCompensationTarget(null)}>
                  Cancel
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
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
