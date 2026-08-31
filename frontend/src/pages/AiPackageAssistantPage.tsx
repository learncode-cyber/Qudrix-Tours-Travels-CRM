import { useState } from 'react'
import type { FormEvent } from 'react'
import { aiInterpretRequirements, aiProposePackage } from '../api/endpoints'
import { EmptyState, Loading } from '../components/ui'
import type { AiInterpretedRequirements, AiPackageProposalResult } from '../types'
import { formatCurrency, getErrorMessage } from '../utils/format'

export default function AiPackageAssistantPage() {
  const [text, setText] = useState('')
  const [interpreting, setInterpreting] = useState(false)
  const [interpretError, setInterpretError] = useState<string | null>(null)
  const [requirements, setRequirements] = useState<AiInterpretedRequirements | null>(null)

  const [destination, setDestination] = useState('')
  const [travelDate, setTravelDate] = useState('')
  const [groupSize, setGroupSize] = useState('1')

  const [proposing, setProposing] = useState(false)
  const [proposeError, setProposeError] = useState<string | null>(null)
  const [proposal, setProposal] = useState<AiPackageProposalResult | null>(null)

  async function handleInterpret(e: FormEvent) {
    e.preventDefault()
    setInterpreting(true)
    setInterpretError(null)
    setRequirements(null)
    try {
      const res = await aiInterpretRequirements(text)
      const data = res.data.data
      setRequirements(data)
      setDestination(data.destination ?? '')
      setTravelDate(data.travel_date ?? '')
      setGroupSize(data.group_size ? String(data.group_size) : '1')
    } catch (err) {
      setInterpretError(getErrorMessage(err, 'Unable to interpret this request.'))
    } finally {
      setInterpreting(false)
    }
  }

  async function handlePropose(e: FormEvent) {
    e.preventDefault()
    setProposing(true)
    setProposeError(null)
    setProposal(null)
    try {
      const res = await aiProposePackage({
        destination: destination || undefined,
        travel_date: travelDate || undefined,
        group_size: groupSize ? Number(groupSize) : undefined,
      })
      setProposal(res.data.data)
    } catch (err) {
      const anyErr = err as { response?: { status?: number; data?: { error?: string; details?: unknown } } }
      if (anyErr.response?.status === 422) {
        setProposeError(
          `${anyErr.response.data?.error ?? 'Verification failed.'} The assistant named a component that no longer exists or lacks availability — try again.`,
        )
      } else {
        setProposeError(getErrorMessage(err, 'Unable to propose a package.'))
      }
    } finally {
      setProposing(false)
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>AI Package Assistant</h1>
      </div>
      <p className="field-hint">
        Two-step, grounded process: the assistant only ever turns free text into structured
        requirements, then chooses among inventory it is actually shown — every component it
        names is re-verified against real availability and priced by the deterministic pricing
        engine, never by the model. The result is a draft for you to review, not a booking.
      </p>

      <h2>1. Describe the trip</h2>
      <form onSubmit={handleInterpret} className="stacked-form">
        {interpretError ? <div className="state-block error">{interpretError}</div> : null}
        <label className="field">
          <span>Customer's request</span>
          <textarea
            required
            placeholder="We'd like to go to Dubai for 2 people around June 1st, budget around $3000."
            value={text}
            onChange={(e) => setText(e.target.value)}
          />
        </label>
        <div className="modal-actions">
          <button type="submit" className="btn btn-primary" disabled={interpreting}>
            {interpreting ? 'Interpreting...' : 'Interpret Requirements'}
          </button>
        </div>
      </form>

      {requirements ? (
        <div className="table-wrap">
          <p>
            Destination: {requirements.destination ?? '—'} &middot; Travel date:{' '}
            {requirements.travel_date ?? '—'} &middot; Group size: {requirements.group_size ?? '—'}
          </p>
          {requirements.missing_information && requirements.missing_information.length > 0 ? (
            <p className="field-hint">
              Still needed: {requirements.missing_information.join(', ')}
            </p>
          ) : null}
        </div>
      ) : null}

      <h2>2. Propose a package from real inventory</h2>
      <form onSubmit={handlePropose} className="stacked-form">
        {proposeError ? <div className="state-block error">{proposeError}</div> : null}
        <label className="field">
          <span>Destination</span>
          <input value={destination} onChange={(e) => setDestination(e.target.value)} />
        </label>
        <label className="field">
          <span>Travel Date</span>
          <input type="date" value={travelDate} onChange={(e) => setTravelDate(e.target.value)} />
        </label>
        <label className="field">
          <span>Group Size</span>
          <input type="number" min={1} value={groupSize} onChange={(e) => setGroupSize(e.target.value)} />
        </label>
        <div className="modal-actions">
          <button type="submit" className="btn btn-primary" disabled={proposing}>
            {proposing ? 'Proposing...' : 'Propose Package'}
          </button>
        </div>
      </form>

      {proposing ? <Loading /> : null}

      {proposal ? (
        proposal.proposal === null ? (
          <EmptyState message={proposal.message ?? 'No matching inventory is currently available.'} />
        ) : (
          <div className="table-wrap">
            {proposal.proposal.summary ? <p>{proposal.proposal.summary}</p> : null}
            {proposal.verified.length > 0 ? (
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Component</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                  </tr>
                </thead>
                <tbody>
                  {proposal.verified.map((c, i) => (
                    <tr key={i}>
                      <td>{c.description}</td>
                      <td>{c.quantity}</td>
                      <td>{formatCurrency(c.unit_price)}</td>
                      <td>{formatCurrency(c.line_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <EmptyState message={proposal.message ?? 'The assistant did not propose a usable component.'} />
            )}
            {proposal.pricing ? (
              <p>
                Base cost {formatCurrency(proposal.pricing.base_cost)} &rarr; final price{' '}
                <strong>{formatCurrency(proposal.pricing.final_price)}</strong>
              </p>
            ) : null}
            {proposal.proposal.upsell_suggestions && proposal.proposal.upsell_suggestions.length > 0 ? (
              <p className="field-hint">
                Upsell ideas: {proposal.proposal.upsell_suggestions.join(', ')}
              </p>
            ) : null}
            {proposal.requires_human_approval ? (
              <div className="state-block success">
                This is a draft proposal — nothing has been booked or saved. Use the Package
                Builder page to actually build and save it once you've reviewed it.
              </div>
            ) : null}
          </div>
        )
      ) : null}
    </div>
  )
}
