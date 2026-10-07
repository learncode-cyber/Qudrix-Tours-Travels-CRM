import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Agent {
  id: number
  agent_code: string
  name: string
  phone: string
  commission_type: 'percentage' | 'fixed'
  commission_rate: string
  total_commission_earned: string
  total_commission_paid: string
  status: string
}

export default function AgentsList() {
  const [agents, setAgents] = useState<Agent[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    api
      .get('/agents', { params: { page } })
      .then((res) => {
        setAgents(res.data.data)
        setLastPage(res.data.pagination.last_page)
        setError(null)
      })
      .catch(() => setError('Could not load agents.'))
      .finally(() => setLoading(false))
  }, [page])

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Agents</h1>
        <Link to="/agents/new">
          <Button>
            <Plus size={16} /> New agent
          </Button>
        </Link>
      </div>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : agents.length === 0 ? (
          <EmptyState title="No agents yet" description="Referral agents you add will show up here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Code</th>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Phone</th>
                <th className="px-4 py-3 font-medium">Commission</th>
                <th className="px-4 py-3 font-medium">Balance</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {agents.map((a) => {
                const balance = Number(a.total_commission_earned) - Number(a.total_commission_paid)
                return (
                  <tr key={a.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                    <td className="px-4 py-3 text-ink-soft">{a.agent_code}</td>
                    <td className="px-4 py-3">
                      <Link to={`/agents/${a.id}`} className="font-medium text-ink hover:text-teal">
                        {a.name}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-ink-soft">{a.phone}</td>
                    <td className="px-4 py-3 text-ink-soft">
                      {a.commission_rate}
                      {a.commission_type === 'percentage' ? '%' : ''}
                    </td>
                    <td className="px-4 py-3 text-ink-soft">{balance.toFixed(2)}</td>
                    <td className="px-4 py-3">
                      <Badge tone={a.status === 'active' ? 'success' : a.status === 'suspended' ? 'danger' : 'default'}>
                        {a.status}
                      </Badge>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
      </Card>

      {lastPage > 1 && (
        <div className="flex justify-end gap-2 mt-4">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="flex items-center px-2 text-sm text-ink-soft">
            Page {page} of {lastPage}
          </span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      )}
    </div>
  )
}
