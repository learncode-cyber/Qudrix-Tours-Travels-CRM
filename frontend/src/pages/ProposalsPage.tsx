import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { listProposals, rejectProposal, sendProposal, signProposal } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, NotAvailable } from '../components/ui'
import type { Proposal } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function ProposalsPage() {
  const [proposals, setProposals] = useState<Proposal[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listProposals()
      setProposals(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load proposals.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function runAction(id: number, action: () => Promise<unknown>) {
    setBusyId(id)
    setActionError(null)
    try {
      await action()
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Action failed.'))
    } finally {
      setBusyId(null)
    }
  }

  if (notAvailable) {
    return (
      <div className="page">
        <div className="page-header">
          <h1>Proposals</h1>
        </div>
        <NotAvailable label="Proposals" />
      </div>
    )
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Proposals</h1>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : proposals.length === 0 ? (
        <EmptyState message="No proposals yet. Create one from an accepted quotation." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Number</th>
                <th>Title</th>
                <th>Status</th>
                <th>Quotation</th>
                <th>Expiry</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {proposals.map((p) => (
                <tr key={p.id}>
                  <td>{p.proposal_number ?? p.id}</td>
                  <td>{p.title ?? '—'}</td>
                  <td>
                    <Badge text={titleCase(p.status)} tone={statusTone(p.status)} />
                  </td>
                  <td>
                    {p.quotation_id ? (
                      <Link to={`/quotations/${p.quotation_id}`}>#{p.quotation_id}</Link>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td>{formatDate(p.expiry_date)}</td>
                  <td>
                    <div className="row-actions">
                      {p.status === 'draft' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === p.id}
                          onClick={() => runAction(p.id, () => sendProposal(p.id))}
                        >
                          Send
                        </button>
                      ) : null}
                      {p.status === 'sent' ? (
                        <>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            disabled={busyId === p.id}
                            onClick={() => runAction(p.id, () => signProposal(p.id))}
                          >
                            Sign
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            disabled={busyId === p.id}
                            onClick={() => runAction(p.id, () => rejectProposal(p.id))}
                          >
                            Reject
                          </button>
                        </>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
