import { useEffect, useState } from 'react'
import { api } from '../../lib/api'
import { Card, Badge, Button, EmptyState, Spinner } from '../../components/ui'

interface Proposal {
  id: number
  proposal_number: string
  title: string | null
  status: string
  expiry_date: string | null
  customer: { name: string } | null
  lead: { name: string } | null
}

const STATUS_TONE: Record<string, 'default' | 'success' | 'warn' | 'danger'> = {
  draft: 'default',
  sent: 'warn',
  signed: 'success',
  rejected: 'danger',
}

export default function ProposalsList() {
  const [proposals, setProposals] = useState<Proposal[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  function load() {
    setLoading(true)
    api
      .get('/proposals')
      .then((res) => setProposals(res.data.data))
      .catch(() => setError('Could not load proposals.'))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function handleAction(id: number, action: 'send' | 'sign' | 'reject') {
    setActionError(null)
    try {
      await api.post(`/proposals/${id}/${action}`)
      load()
    } catch (err: any) {
      setActionError(err.response?.data?.error || `Could not ${action} this proposal.`)
    }
  }

  return (
    <div>
      <h1 className="text-xl font-semibold text-ink mb-6">Proposals</h1>

      {actionError && (
        <p className="mb-4 text-sm text-danger bg-danger/10 border border-danger/30 px-3 py-2">{actionError}</p>
      )}

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : proposals.length === 0 ? (
          <EmptyState title="No proposals yet" description="Proposals created from accepted quotations will appear here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Number</th>
                <th className="px-4 py-3 font-medium">Title</th>
                <th className="px-4 py-3 font-medium">For</th>
                <th className="px-4 py-3 font-medium">Expires</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {proposals.map((p) => (
                <tr key={p.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-mono text-xs text-ink">{p.proposal_number}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.title || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">{p.customer?.name || p.lead?.name || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft">
                    {p.expiry_date ? new Date(p.expiry_date).toLocaleDateString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <Badge tone={STATUS_TONE[p.status] || 'default'}>{p.status}</Badge>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {p.status === 'draft' && (
                        <Button variant="secondary" className="text-xs px-2 py-1" onClick={() => handleAction(p.id, 'send')}>
                          Send
                        </Button>
                      )}
                      {p.status === 'sent' && (
                        <>
                          <Button variant="secondary" className="text-xs px-2 py-1" onClick={() => handleAction(p.id, 'sign')}>
                            Mark signed
                          </Button>
                          <Button variant="ghost" className="text-xs px-2 py-1" onClick={() => handleAction(p.id, 'reject')}>
                            Reject
                          </Button>
                        </>
                      )}
                    </div>
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
