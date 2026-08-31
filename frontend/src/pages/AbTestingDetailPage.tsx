import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  addAbVariant,
  assignAbExperiment,
  getAbExperiment,
  getAbExperimentResults,
  startAbExperiment,
  stopAbExperiment,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { AbExperiment, AbExperimentResults } from '../types'
import { formatCurrency, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function AbTestingDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [experiment, setExperiment] = useState<AbExperiment | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const [showVariantForm, setShowVariantForm] = useState(false)
  const [variantLabel, setVariantLabel] = useState('')
  const [variantContent, setVariantContent] = useState('')
  const [variantWeight, setVariantWeight] = useState('1')
  const [variantSaving, setVariantSaving] = useState(false)
  const [variantError, setVariantError] = useState<string | null>(null)

  const [showAssignForm, setShowAssignForm] = useState(false)
  const [assignLeadId, setAssignLeadId] = useState('')
  const [assignBusy, setAssignBusy] = useState(false)
  const [assignError, setAssignError] = useState<string | null>(null)
  const [assignResult, setAssignResult] = useState<string | null>(null)

  const [results, setResults] = useState<AbExperimentResults | null>(null)
  const [resultsLoading, setResultsLoading] = useState(false)

  async function load() {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await getAbExperiment(id)
      setExperiment(res.data.data)
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load this experiment.'))
    } finally {
      setLoading(false)
    }
  }

  async function loadResults() {
    if (!id) return
    setResultsLoading(true)
    try {
      const res = await getAbExperimentResults(id)
      setResults(res.data.data)
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to load results.'))
    } finally {
      setResultsLoading(false)
    }
  }

  useEffect(() => {
    load()
    loadResults()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function handleAddVariant(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setVariantSaving(true)
    setVariantError(null)
    try {
      await addAbVariant(id, { label: variantLabel, content: variantContent, weight: Number(variantWeight) })
      setVariantLabel('')
      setVariantContent('')
      setVariantWeight('1')
      setShowVariantForm(false)
      await load()
    } catch (err) {
      setVariantError(getErrorMessage(err, 'Unable to save this variant.'))
    } finally {
      setVariantSaving(false)
    }
  }

  async function handleStart() {
    if (!id) return
    setActionError(null)
    try {
      await startAbExperiment(id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to start this experiment.'))
    }
  }

  async function handleStop() {
    if (!id) return
    setActionError(null)
    try {
      await stopAbExperiment(id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to stop this experiment.'))
    }
  }

  async function handleAssign(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setAssignBusy(true)
    setAssignError(null)
    setAssignResult(null)
    try {
      const res = await assignAbExperiment(id, assignLeadId)
      setAssignResult(`Assigned to variant ${res.data.data.variant?.label ?? res.data.data.ab_variant_id}.`)
      setAssignLeadId('')
      await loadResults()
    } catch (err) {
      setAssignError(getErrorMessage(err, 'Unable to assign this lead.'))
    } finally {
      setAssignBusy(false)
    }
  }

  return (
    <div className="page">
      <Link to="/ab-testing" className="back-link">
        &larr; A/B Testing
      </Link>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : experiment ? (
        <>
          <div className="page-header">
            <div>
              <h1>{experiment.name}</h1>
              {experiment.hypothesis ? <p className="field-hint">{experiment.hypothesis}</p> : null}
            </div>
            <div className="header-actions">
              <Badge text={titleCase(experiment.status)} tone={statusTone(experiment.status)} />
              {experiment.status === 'draft' ? (
                <button type="button" className="btn btn-primary" onClick={handleStart}>
                  Start Experiment
                </button>
              ) : null}
              {experiment.status === 'running' ? (
                <>
                  <button type="button" className="btn btn-ghost" onClick={() => setShowAssignForm(true)}>
                    Assign a Lead
                  </button>
                  <button type="button" className="btn btn-ghost" onClick={handleStop}>
                    Stop Experiment
                  </button>
                </>
              ) : null}
            </div>
          </div>

          {actionError ? <ErrorBanner message={actionError} /> : null}

          <div className="page-header">
            <h2>Variants</h2>
            {experiment.status === 'draft' ? (
              <button type="button" className="btn btn-ghost" onClick={() => setShowVariantForm(true)}>
                + Add Variant
              </button>
            ) : null}
          </div>
          {!experiment.variants || experiment.variants.length === 0 ? (
            <EmptyState message="No variants yet — add at least two before starting." />
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Label</th>
                    <th>Content</th>
                    <th>Weight</th>
                  </tr>
                </thead>
                <tbody>
                  {experiment.variants.map((v) => (
                    <tr key={v.id}>
                      <td>{v.label}</td>
                      <td>{v.content}</td>
                      <td>{v.weight}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <h2>Results</h2>
          {resultsLoading ? (
            <Loading />
          ) : results ? (
            <>
              <div
                className={`state-block ${results.winner.decided ? 'success' : 'error'}`}
              >
                {results.winner.decided
                  ? `Leading variant: ${results.winner.variant_label} (${results.winner.conversion_rate_percent}% conversion, +${results.winner.margin_over_next_percent}pp over next). ${results.winner.note}`
                  : results.winner.reason}
              </div>
              <div className="table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Variant</th>
                      <th>Assignments</th>
                      <th>Responded</th>
                      <th>Response Rate</th>
                      <th>Converted</th>
                      <th>Conversion Rate</th>
                      <th>Total Value</th>
                      <th>Avg Value</th>
                    </tr>
                  </thead>
                  <tbody>
                    {results.variants.map((v) => (
                      <tr key={v.variant_id}>
                        <td>{v.label}</td>
                        <td>{v.assignments}</td>
                        <td>{v.responded}</td>
                        <td>{v.response_rate_percent !== null ? `${v.response_rate_percent}%` : '—'}</td>
                        <td>{v.converted}</td>
                        <td>{v.conversion_rate_percent !== null ? `${v.conversion_rate_percent}%` : '—'}</td>
                        <td>{formatCurrency(v.total_booking_value)}</td>
                        <td>{v.average_booking_value !== null ? formatCurrency(v.average_booking_value) : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ) : null}
        </>
      ) : null}

      {showVariantForm ? (
        <Modal title="Add Variant" onClose={() => setShowVariantForm(false)}>
          <form onSubmit={handleAddVariant} className="stacked-form">
            {variantError ? <div className="state-block error">{variantError}</div> : null}
            <label className="field">
              <span>Label</span>
              <input required maxLength={16} placeholder="A" value={variantLabel} onChange={(e) => setVariantLabel(e.target.value)} />
            </label>
            <label className="field">
              <span>Content</span>
              <textarea required value={variantContent} onChange={(e) => setVariantContent(e.target.value)} />
            </label>
            <label className="field">
              <span>Weight</span>
              <input type="number" min={1} value={variantWeight} onChange={(e) => setVariantWeight(e.target.value)} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowVariantForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={variantSaving}>
                {variantSaving ? 'Saving...' : 'Save Variant'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {showAssignForm ? (
        <Modal title="Assign a Lead" onClose={() => setShowAssignForm(false)}>
          <form onSubmit={handleAssign} className="stacked-form">
            {assignError ? <div className="state-block error">{assignError}</div> : null}
            {assignResult ? <div className="state-block success">{assignResult}</div> : null}
            <label className="field">
              <span>Lead ID</span>
              <input required value={assignLeadId} onChange={(e) => setAssignLeadId(e.target.value)} />
              <p className="field-hint">
                A lead is always assigned to the same variant — reassigning the same lead returns
                its existing assignment.
              </p>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowAssignForm(false)}>
                Close
              </button>
              <button type="submit" className="btn btn-primary" disabled={assignBusy}>
                {assignBusy ? 'Assigning...' : 'Assign'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
