import { useState, FormEvent } from 'react'
import { api } from '../../../lib/api'
import { Card, Badge, EmptyState, Button, Input } from '../../../components/ui'
import type { InstallmentPlan } from '../types'

interface Props {
  bookingId: string | undefined
  plans: InstallmentPlan[]
  onChanged: () => void
}

export default function InstallmentSection({ bookingId, plans, onChanged }: Props) {
  const [planForm, setPlanForm] = useState({ totalAmount: '', installments: '3', firstDueDate: '' })
  const [savingPlan, setSavingPlan] = useState(false)
  const [planError, setPlanError] = useState<string | null>(null)

  async function handleCreatePlan(e: FormEvent) {
    e.preventDefault()
    setSavingPlan(true)
    setPlanError(null)
    try {
      await api.post('/installment-plans', {
        booking_id: bookingId,
        total_amount: planForm.totalAmount,
        number_of_installments: planForm.installments,
        first_due_date: planForm.firstDueDate,
      })
      setPlanForm({ totalAmount: '', installments: '3', firstDueDate: '' })
      onChanged()
    } catch (err: any) {
      setPlanError(err.response?.data?.error || 'Could not create installment plan.')
    } finally {
      setSavingPlan(false)
    }
  }

  async function handlePayInstallment(installmentId: number) {
    await api.post(`/installments/${installmentId}/pay`, { payment_method: 'cash' })
    onChanged()
  }

  return (
      <Card className="p-5">
        <h2 className="text-sm font-medium text-ink mb-4">Installment plans</h2>
        {plans.length === 0 ? (
          <EmptyState title="No installment plan yet" description="Split this booking's cost into scheduled payments." />
        ) : (
          <div className="space-y-5 mb-5">
            {plans.map((plan) => (
              <div key={plan.id} className="border border-line p-3">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-sm font-medium text-ink">
                    {plan.total_amount} over {plan.number_of_installments} installments
                  </p>
                  <Badge tone={plan.status === 'completed' ? 'success' : 'default'}>{plan.status}</Badge>
                </div>
                <ul className="space-y-1">
                  {plan.installments.map((inst) => (
                    <li key={inst.id} className="flex items-center justify-between text-xs">
                      <span className="text-ink-soft">
                        #{inst.sequence_number} — due {new Date(inst.due_date).toLocaleDateString()} — {inst.amount}
                      </span>
                      {inst.status === 'paid' ? (
                        <Badge tone="success">paid</Badge>
                      ) : (
                        <button onClick={() => handlePayInstallment(inst.id)} className="text-teal underline">
                          mark paid
                        </button>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}

        <form onSubmit={handleCreatePlan} className="grid grid-cols-3 gap-3 border-t border-line pt-4">
          <Input
            type="number"
            step="0.01"
            placeholder="Total amount"
            required
            value={planForm.totalAmount}
            onChange={(e) => setPlanForm((f) => ({ ...f, totalAmount: e.target.value }))}
          />
          <Input
            type="number"
            min={2}
            max={60}
            placeholder="# installments"
            required
            value={planForm.installments}
            onChange={(e) => setPlanForm((f) => ({ ...f, installments: e.target.value }))}
          />
          <Input
            type="date"
            required
            value={planForm.firstDueDate}
            onChange={(e) => setPlanForm((f) => ({ ...f, firstDueDate: e.target.value }))}
          />
          <Button type="submit" disabled={savingPlan} className="col-span-3">
            {savingPlan ? 'Creating…' : 'Create installment plan'}
          </Button>
          {planError && <p className="col-span-3 text-xs text-danger">{planError}</p>}
        </form>
      </Card>
  )
}
