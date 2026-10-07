import { useEffect, useState, FormEvent } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Card, Badge, Spinner, EmptyState, Button, Input } from '../../components/ui'
import { ArrowLeft } from 'lucide-react'

interface Performance {
  agent: { id: number; name: string; agent_code: string; status: string }
  leads_count: number
  bookings_count: number
  total_commission_earned: string
  total_commission_paid: string
  commission_balance: number
}

interface LedgerEntry {
  id: number
  type: 'earned' | 'paid' | 'adjustment'
  amount: string
  balance_after: string
  notes: string | null
  created_at: string
}

export default function AgentDetail() {
  const { id } = useParams()
  const [performance, setPerformance] = useState<Performance | null>(null)
  const [ledger, setLedger] = useState<LedgerEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [payoutAmount, setPayoutAmount] = useState('')
  const [payoutError, setPayoutError] = useState<string | null>(null)
  const [payingOut, setPayingOut] = useState(false)

  function load() {
    setLoading(true)
    Promise.all([api.get(`/agents/${id}/performance`), api.get(`/agents/${id}/commission-ledger`)])
      .then(([perfRes, ledgerRes]) => {
        setPerformance(perfRes.data.data)
        setLedger(ledgerRes.data.data)
      })
      .finally(() => setLoading(false))
  }

  useEffect(load, [id])

  async function handlePayout(e: FormEvent) {
    e.preventDefault()
    setPayingOut(true)
    setPayoutError(null)
    try {
      await api.post(`/agents/${id}/pay-commission`, { amount: payoutAmount })
      setPayoutAmount('')
      load()
    } catch (err: any) {
      setPayoutError(err.response?.data?.error || 'Could not record payout.')
    } finally {
      setPayingOut(false)
    }
  }

  if (loading) return <Spinner />
  if (!performance) return <EmptyState title="Agent not found" />

  const { agent } = performance

  return (
    <div className="max-w-2xl">
      <Link to="/agents" className="inline-flex items-center gap-1 text-sm text-ink-soft hover:text-ink mb-4">
        <ArrowLeft size={15} /> Back to agents
      </Link>

      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold text-ink">{agent.name}</h1>
          <p className="text-sm text-ink-soft mt-0.5">{agent.agent_code}</p>
        </div>
        <Badge tone={agent.status === 'active' ? 'success' : agent.status === 'suspended' ? 'danger' : 'default'}>
          {agent.status}
        </Badge>
      </div>

      <div className="grid grid-cols-3 gap-4 mb-6">
        <Card className="p-4">
          <p className="text-xs text-ink-soft">Leads referred</p>
          <p className="text-lg font-semibold text-ink">{performance.leads_count}</p>
        </Card>
        <Card className="p-4">
          <p className="text-xs text-ink-soft">Bookings referred</p>
          <p className="text-lg font-semibold text-ink">{performance.bookings_count}</p>
        </Card>
        <Card className="p-4">
          <p className="text-xs text-ink-soft">Outstanding balance</p>
          <p className="text-lg font-semibold text-ink">{performance.commission_balance.toFixed(2)}</p>
        </Card>
      </div>

      <Card className="p-5 mb-6">
        <h2 className="text-sm font-medium text-ink mb-3">Record a payout</h2>
        <form onSubmit={handlePayout} className="flex items-end gap-3">
          <div className="flex-1">
            <label className="block text-sm font-medium text-ink mb-1">Amount</label>
            <Input
              type="number"
              step="0.01"
              required
              value={payoutAmount}
              onChange={(e) => setPayoutAmount(e.target.value)}
            />
          </div>
          <Button type="submit" disabled={payingOut}>
            {payingOut ? 'Recording…' : 'Pay out'}
          </Button>
        </form>
        {payoutError && <p className="mt-2 text-xs text-danger">{payoutError}</p>}
      </Card>

      <Card className="p-5">
        <h2 className="text-sm font-medium text-ink mb-4">Commission ledger</h2>
        {ledger.length === 0 ? (
          <EmptyState title="No commission activity yet" description="Earned and paid commission will appear here." />
        ) : (
          <ol className="space-y-3">
            {ledger.map((entry) => (
              <li key={entry.id} className="flex items-center justify-between text-sm border-b border-line last:border-0 pb-3 last:pb-0">
                <div>
                  <span className="font-medium capitalize text-ink">{entry.type}</span>
                  <span className="text-ink-soft"> — {entry.notes || 'No notes'}</span>
                  <p className="text-xs text-ink-soft/70 mt-0.5">{new Date(entry.created_at).toLocaleString()}</p>
                </div>
                <div className="text-right">
                  <p className={entry.type === 'paid' ? 'text-danger' : 'text-success'}>
                    {entry.type === 'paid' ? '-' : '+'}
                    {entry.amount}
                  </p>
                  <p className="text-xs text-ink-soft">Balance: {entry.balance_after}</p>
                </div>
              </li>
            ))}
          </ol>
        )}
      </Card>
    </div>
  )
}
