import { useEffect, useState } from 'react'
import { api } from '../../lib/api'
import { Card, Badge, EmptyState, Spinner } from '../../components/ui'

interface Lead {
  id: number
  name: string
  email: string | null
  phone: string | null
  status: string
  priority: string
  source: string | null
  assignedTo: { id: number; name: string } | null
}

interface StaffUser {
  id: number
  name: string
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  new: 'default',
  contacted: 'default',
  qualified: 'warn',
  proposal: 'warn',
  negotiation: 'warn',
  won: 'success',
  lost: 'danger',
}

export default function LeadsList() {
  const [leads, setLeads] = useState<Lead[]>([])
  const [staff, setStaff] = useState<StaffUser[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [assignError, setAssignError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get('/leads')
      .then((res) => setLeads(res.data.data))
      .catch(() => setError('Could not load leads.'))
      .finally(() => setLoading(false))
    api.get('/users').then((res) => setStaff(res.data.data))
  }, [])

  async function handleAssign(leadId: number, userId: string) {
    if (!userId) return
    setAssignError(null)
    try {
      await api.put(`/leads/${leadId}/assign`, { assigned_to: userId })
      setLeads((prev) => prev.map((l) => (l.id === leadId ? { ...l, assignedTo: staff.find((s) => s.id === Number(userId)) || null } : l)))
    } catch (err: any) {
      setAssignError(err.response?.data?.error || `Could not assign lead #${leadId}.`)
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Leads</h1>

      {assignError && <p className="mb-3 text-sm text-danger">{assignError}</p>}

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : leads.length === 0 ? (
          <EmptyState title="No leads yet" description="New leads (from the website, referrals, etc.) will appear here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">Source</th>
                <th className="px-4 py-3 font-medium">Priority</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Assigned to</th>
              </tr>
            </thead>
            <tbody>
              {leads.map((l) => (
                <tr key={l.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{l.name}</td>
                  <td className="px-4 py-3 text-ink-soft">{l.email || l.phone || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{l.source || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{l.priority}</td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[l.status] || 'default'}>{l.status}</Badge>
                  </td>
                  <td className="px-4 py-3">
                    <select
                      className="border border-line px-2 py-1 text-xs text-ink"
                      value={l.assignedTo?.id || ''}
                      onChange={(e) => handleAssign(l.id, e.target.value)}
                    >
                      <option value="">Unassigned</option>
                      {staff.map((s) => (
                        <option key={s.id} value={s.id}>{s.name}</option>
                      ))}
                    </select>
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
