import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { createCustomer, listCustomers } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Customer } from '../types'
import { getErrorMessage } from '../utils/format'

const emptyForm = { name: '', email: '', phone: '', type: '', status: '' }

export default function CustomersPage() {
  const navigate = useNavigate()
  const [customers, setCustomers] = useState<Customer[]>([])
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
      const res = await listCustomers()
      setCustomers(res.data.data ?? [])
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load customers.'))
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
      // The backend's field is customer_type, not type — the API rejects
      // (422) a create/update that only sends `type`.
      await createCustomer({
        name: form.name,
        email: form.email || undefined,
        phone: form.phone || undefined,
        customer_type: form.type || 'individual',
        status: form.status || undefined,
      })
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create customer.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Customers</h1>
        <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
          + New Customer
        </button>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : customers.length === 0 ? (
        <EmptyState message="No customers yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Type</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {customers.map((c) => (
                <tr key={c.id} className="clickable-row" onClick={() => navigate(`/customers/${c.id}`)}>
                  <td>{c.name}</td>
                  <td>{c.email ?? '—'}</td>
                  <td>{c.phone ?? '—'}</td>
                  <td>{c.customer_type ?? '—'}</td>
                  <td>{c.status ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Customer" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input
                required
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Phone</span>
              <input
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Type</span>
              <input
                value={form.type}
                onChange={(e) => setForm({ ...form, type: e.target.value })}
                placeholder="individual / corporate"
              />
            </label>
            <label className="field">
              <span>Status</span>
              <input
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
                placeholder="active / inactive"
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Customer'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
