import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { createPackage, deletePackage, listPackages } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Package } from '../types'
import { formatCurrency, getErrorMessage } from '../utils/format'

const emptyForm = {
  name: '',
  destination: '',
  days: '',
  nights: '',
  base_price: '',
  description: '',
}

export default function PackagesPage() {
  const [packages, setPackages] = useState<Package[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const res = await listPackages()
      setPackages(res.data.data ?? [])
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load packages.'))
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
      await createPackage({
        name: form.name,
        destination: form.destination || undefined,
        days: form.days ? Number(form.days) : undefined,
        nights: form.nights ? Number(form.nights) : undefined,
        base_price: form.base_price ? Number(form.base_price) : undefined,
        description: form.description || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create package.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(id: number) {
    await deletePackage(id)
    await load()
  }

  if (loading) return <Loading label="Loading packages..." />
  if (error) return <ErrorBanner message={error} />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Packages</h1>
        <button className="btn btn-primary" onClick={() => setShowForm(true)}>
          + New Package
        </button>
      </div>

      {packages.length === 0 ? (
        <EmptyState message="No packages yet. Bookings and quotations need at least one package to reference." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Destination</th>
                <th>Days / Nights</th>
                <th>Base Price</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.destination ?? '—'}</td>
                  <td>
                    {p.days ?? '—'} / {p.nights ?? '—'}
                  </td>
                  <td>{p.base_price ? formatCurrency(p.base_price) : '—'}</td>
                  <td>{p.is_active === false ? 'inactive' : 'active'}</td>
                  <td>
                    <button className="btn btn-ghost btn-sm" onClick={() => handleDelete(p.id)}>
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Package" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Destination</span>
              <input
                value={form.destination}
                onChange={(e) => setForm({ ...form, destination: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Days</span>
              <input
                type="number"
                min={0}
                value={form.days}
                onChange={(e) => setForm({ ...form, days: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Nights</span>
              <input
                type="number"
                min={0}
                value={form.nights}
                onChange={(e) => setForm({ ...form, nights: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Base Price</span>
              <input
                type="number"
                min={0}
                value={form.base_price}
                onChange={(e) => setForm({ ...form, base_price: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Package'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
