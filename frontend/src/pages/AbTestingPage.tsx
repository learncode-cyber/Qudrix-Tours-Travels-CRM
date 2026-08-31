import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { createAbExperiment, listAbExperiments } from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { AbExperiment } from '../types'
import { formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyForm = {
  name: '',
  hypothesis: '',
  subject_type: 'sales_script',
}

export default function AbTestingPage() {
  const [experiments, setExperiments] = useState<AbExperiment[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listAbExperiments()
      setExperiments(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load A/B experiments.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createAbExperiment({
        name: form.name,
        hypothesis: form.hypothesis || undefined,
        subject_type: form.subject_type || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this experiment.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="A/B experiments" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>A/B Testing</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Experiment
          </button>
        </div>
      </div>
      <p className="field-hint">
        Results only ever name a winner once every variant has a real sample of at least 30
        assignments — below that, the observed rates are shown but no winner is declared.
      </p>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : experiments.length === 0 ? (
        <EmptyState message="No A/B experiments yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Variants</th>
                <th>Assignments</th>
                <th>Started</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {experiments.map((exp) => (
                <tr key={exp.id}>
                  <td>
                    <Link to={`/ab-testing/${exp.id}`}>{exp.name}</Link>
                  </td>
                  <td>{titleCase(exp.subject_type)}</td>
                  <td>
                    <Badge text={titleCase(exp.status)} tone={statusTone(exp.status)} />
                  </td>
                  <td>{exp.variants_count ?? '—'}</td>
                  <td>{exp.assignments_count ?? '—'}</td>
                  <td>{formatDate(exp.started_at)}</td>
                  <td>
                    <Link to={`/ab-testing/${exp.id}`} className="btn btn-ghost btn-sm">
                      Manage
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New A/B Experiment" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Hypothesis</span>
              <textarea value={form.hypothesis} onChange={(e) => setForm({ ...form, hypothesis: e.target.value })} />
            </label>
            <label className="field">
              <span>Subject</span>
              <select value={form.subject_type} onChange={(e) => setForm({ ...form, subject_type: e.target.value })}>
                <option value="sales_script">Sales Script</option>
                <option value="email_template">Email Template</option>
                <option value="follow_up_sequence">Follow-up Sequence</option>
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Experiment'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
