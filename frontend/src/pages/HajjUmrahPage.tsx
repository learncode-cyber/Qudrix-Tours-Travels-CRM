import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  createHajjPackage,
  createHajjUmrahGroup,
  createUmrahPackage,
  listHajjPackages,
  listHajjUmrahGroups,
  listUmrahPackages,
  updateHajjPackage,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { HajjPackage, HajjUmrahGroup, HajjUmrahPackageType, UmrahPackage } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyPackageForm = {
  name: '',
  description: '',
  duration_days: '',
  price: '',
  currency: 'USD',
  max_capacity: '',
  rituals_included: '',
}

const emptyGroupForm = {
  package_type: 'hajj' as HajjUmrahPackageType,
  package_id: '',
  name: '',
  departure_date: '',
  return_date: '',
  capacity: '',
}

const GROUP_STATUS_FILTERS = ['all', 'planned', 'confirmed', 'departed', 'completed', 'cancelled']
const GROUP_TYPE_FILTERS = ['all', 'hajj', 'umrah']

export default function HajjUmrahPage() {
  const [tab, setTab] = useState<'hajj' | 'umrah' | 'groups'>('hajj')

  return (
    <div className="page">
      <div className="page-header">
        <h1>Hajj &amp; Umrah</h1>
      </div>
      <div className="view-toggle">
        <button type="button" className={tab === 'hajj' ? 'active' : ''} onClick={() => setTab('hajj')}>
          Hajj Packages
        </button>
        <button type="button" className={tab === 'umrah' ? 'active' : ''} onClick={() => setTab('umrah')}>
          Umrah Packages
        </button>
        <button type="button" className={tab === 'groups' ? 'active' : ''} onClick={() => setTab('groups')}>
          Groups
        </button>
      </div>
      {tab === 'hajj' ? <HajjPackagesTab /> : null}
      {tab === 'umrah' ? <UmrahPackagesTab /> : null}
      {tab === 'groups' ? <GroupsTab /> : null}
    </div>
  )
}

function HajjPackagesTab() {
  const [packages, setPackages] = useState<HajjPackage[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyPackageForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [editing, setEditing] = useState<HajjPackage | null>(null)
  const [editForm, setEditForm] = useState(emptyPackageForm)
  const [editStatus, setEditStatus] = useState('active')
  const [editSaving, setEditSaving] = useState(false)
  const [editError, setEditError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listHajjPackages()
      setPackages(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load Hajj packages.'))
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
      await createHajjPackage({
        name: form.name,
        description: form.description || undefined,
        duration_days: Number(form.duration_days),
        price: Number(form.price),
        currency: form.currency || undefined,
        max_capacity: Number(form.max_capacity),
        rituals_included: form.rituals_included
          ? form.rituals_included.split(',').map((s) => s.trim()).filter(Boolean)
          : undefined,
      } as Partial<HajjPackage>)
      setForm(emptyPackageForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create Hajj package.'))
    } finally {
      setSaving(false)
    }
  }

  function openEdit(pkg: HajjPackage) {
    setEditing(pkg)
    setEditForm({
      name: pkg.name,
      description: pkg.description ?? '',
      duration_days: String(pkg.duration_days),
      price: String(pkg.price),
      currency: pkg.currency ?? 'USD',
      max_capacity: String(pkg.max_capacity),
      rituals_included: (pkg.rituals_included ?? []).join(', '),
    })
    setEditStatus(pkg.status)
    setEditError(null)
  }

  async function handleEditSave(e: FormEvent) {
    e.preventDefault()
    if (!editing) return
    setEditSaving(true)
    setEditError(null)
    try {
      await updateHajjPackage(editing.id, {
        name: editForm.name,
        description: editForm.description || undefined,
        duration_days: Number(editForm.duration_days),
        price: Number(editForm.price),
        currency: editForm.currency || undefined,
        max_capacity: Number(editForm.max_capacity),
        rituals_included: editForm.rituals_included
          ? editForm.rituals_included.split(',').map((s) => s.trim()).filter(Boolean)
          : undefined,
        status: editStatus,
      } as Partial<HajjPackage>)
      setEditing(null)
      await load()
    } catch (err) {
      setEditError(getErrorMessage(err, 'Unable to update this Hajj package.'))
    } finally {
      setEditSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Hajj packages" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Hajj Package
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : packages.length === 0 ? (
        <EmptyState message="No Hajj packages found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Max Capacity</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.duration_days} days</td>
                  <td>{formatCurrency(p.price)}</td>
                  <td>{p.max_capacity}</td>
                  <td>
                    <Badge text={titleCase(p.status)} tone={statusTone(p.status)} />
                  </td>
                  <td>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => openEdit(p)}>
                      Edit
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Hajj Package" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Duration (days)</span>
              <input
                required
                type="number"
                min={1}
                value={form.duration_days}
                onChange={(e) => setForm({ ...form, duration_days: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price</span>
              <input
                required
                type="number"
                min={0}
                step="0.01"
                value={form.price}
                onChange={(e) => setForm({ ...form, price: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                maxLength={3}
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value.toUpperCase() })}
              />
            </label>
            <label className="field">
              <span>Max Capacity</span>
              <input
                required
                type="number"
                min={1}
                value={form.max_capacity}
                onChange={(e) => setForm({ ...form, max_capacity: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Rituals Included</span>
              <input
                placeholder="tawaf, saee, ..."
                value={form.rituals_included}
                onChange={(e) => setForm({ ...form, rituals_included: e.target.value })}
              />
              <p className="field-hint">Comma-separated list.</p>
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

      {editing ? (
        <Modal title={`Edit — ${editing.name}`} onClose={() => setEditing(null)}>
          <form onSubmit={handleEditSave} className="stacked-form">
            {editError ? <div className="state-block error">{editError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input
                required
                value={editForm.name}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                value={editForm.description}
                onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Duration (days)</span>
              <input
                required
                type="number"
                min={1}
                value={editForm.duration_days}
                onChange={(e) => setEditForm({ ...editForm, duration_days: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price</span>
              <input
                required
                type="number"
                min={0}
                step="0.01"
                value={editForm.price}
                onChange={(e) => setEditForm({ ...editForm, price: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                maxLength={3}
                value={editForm.currency}
                onChange={(e) => setEditForm({ ...editForm, currency: e.target.value.toUpperCase() })}
              />
            </label>
            <label className="field">
              <span>Max Capacity</span>
              <input
                required
                type="number"
                min={1}
                value={editForm.max_capacity}
                onChange={(e) => setEditForm({ ...editForm, max_capacity: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Rituals Included</span>
              <input
                placeholder="tawaf, saee, ..."
                value={editForm.rituals_included}
                onChange={(e) => setEditForm({ ...editForm, rituals_included: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Status</span>
              <select value={editStatus} onChange={(e) => setEditStatus(e.target.value)}>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="discontinued">Discontinued</option>
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setEditing(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={editSaving}>
                {editSaving ? 'Saving...' : 'Save Changes'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}

function UmrahPackagesTab() {
  const [packages, setPackages] = useState<UmrahPackage[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyPackageForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listUmrahPackages()
      setPackages(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load Umrah packages.'))
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
      await createUmrahPackage({
        name: form.name,
        description: form.description || undefined,
        duration_days: Number(form.duration_days),
        price: Number(form.price),
        currency: form.currency || undefined,
        max_capacity: Number(form.max_capacity),
        rituals_included: form.rituals_included
          ? form.rituals_included.split(',').map((s) => s.trim()).filter(Boolean)
          : undefined,
      } as Partial<UmrahPackage>)
      setForm(emptyPackageForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create Umrah package.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Umrah packages" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Umrah Package
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : packages.length === 0 ? (
        <EmptyState message="No Umrah packages found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Max Capacity</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {packages.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.duration_days} days</td>
                  <td>{formatCurrency(p.price)}</td>
                  <td>{p.max_capacity}</td>
                  <td>
                    <Badge text={titleCase(p.status)} tone={statusTone(p.status)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Umrah Package" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Duration (days)</span>
              <input
                required
                type="number"
                min={1}
                value={form.duration_days}
                onChange={(e) => setForm({ ...form, duration_days: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price</span>
              <input
                required
                type="number"
                min={0}
                step="0.01"
                value={form.price}
                onChange={(e) => setForm({ ...form, price: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                maxLength={3}
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value.toUpperCase() })}
              />
            </label>
            <label className="field">
              <span>Max Capacity</span>
              <input
                required
                type="number"
                min={1}
                value={form.max_capacity}
                onChange={(e) => setForm({ ...form, max_capacity: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Rituals Included</span>
              <input
                placeholder="umrah, ..."
                value={form.rituals_included}
                onChange={(e) => setForm({ ...form, rituals_included: e.target.value })}
              />
              <p className="field-hint">Comma-separated list.</p>
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
    </>
  )
}

function GroupsTab() {
  const [groups, setGroups] = useState<HajjUmrahGroup[]>([])
  const [hajjPackages, setHajjPackages] = useState<HajjPackage[]>([])
  const [umrahPackages, setUmrahPackages] = useState<UmrahPackage[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [typeFilter, setTypeFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyGroupForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listHajjUmrahGroups({
        package_type: typeFilter === 'all' ? undefined : typeFilter,
        status: statusFilter === 'all' ? undefined : statusFilter,
      })
      setGroups(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load groups.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    listHajjPackages()
      .then((res) => setHajjPackages(res.data.data ?? []))
      .catch(() => setHajjPackages([]))
    listUmrahPackages()
      .then((res) => setUmrahPackages(res.data.data ?? []))
      .catch(() => setUmrahPackages([]))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [typeFilter, statusFilter])

  const packageOptions = useMemo(
    () => (form.package_type === 'hajj' ? hajjPackages : umrahPackages),
    [form.package_type, hajjPackages, umrahPackages],
  )

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createHajjUmrahGroup({
        package_type: form.package_type,
        package_id: Number(form.package_id),
        name: form.name,
        departure_date: form.departure_date,
        return_date: form.return_date,
        capacity: Number(form.capacity),
      } as Partial<HajjUmrahGroup>)
      setForm(emptyGroupForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create group.'))
    } finally {
      setSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Hajj/Umrah groups" />

  return (
    <>
      <div className="page-header">
        <div className="view-toggle">
          {GROUP_TYPE_FILTERS.map((t) => (
            <button key={t} type="button" className={typeFilter === t ? 'active' : ''} onClick={() => setTypeFilter(t)}>
              {titleCase(t)}
            </button>
          ))}
        </div>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Group
          </button>
        </div>
      </div>
      <div className="view-toggle">
        {GROUP_STATUS_FILTERS.map((s) => (
          <button key={s} type="button" className={statusFilter === s ? 'active' : ''} onClick={() => setStatusFilter(s)}>
            {titleCase(s)}
          </button>
        ))}
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : groups.length === 0 ? (
        <EmptyState message="No groups found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Departure</th>
                <th>Return</th>
                <th>Capacity</th>
                <th>Pilgrims</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {groups.map((g) => (
                <tr key={g.id}>
                  <td>
                    <Link to={`/hajj-umrah/groups/${g.id}`}>{g.name}</Link>
                  </td>
                  <td>{titleCase(g.package_type)}</td>
                  <td>{formatDate(g.departure_date)}</td>
                  <td>{formatDate(g.return_date)}</td>
                  <td>{g.capacity}</td>
                  <td>{g.pilgrims_count ?? '—'}</td>
                  <td>
                    <Badge text={titleCase(g.status)} tone={statusTone(g.status)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Group" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Package Type</span>
              <select
                value={form.package_type}
                onChange={(e) =>
                  setForm({ ...form, package_type: e.target.value as HajjUmrahPackageType, package_id: '' })
                }
              >
                <option value="hajj">Hajj</option>
                <option value="umrah">Umrah</option>
              </select>
            </label>
            <label className="field">
              <span>Package</span>
              <select
                required
                value={form.package_id}
                onChange={(e) => setForm({ ...form, package_id: e.target.value })}
              >
                <option value="">Select a package...</option>
                {packageOptions.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Departure Date</span>
              <input
                required
                type="date"
                value={form.departure_date}
                onChange={(e) => setForm({ ...form, departure_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Return Date</span>
              <input
                required
                type="date"
                value={form.return_date}
                onChange={(e) => setForm({ ...form, return_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Capacity</span>
              <input
                required
                type="number"
                min={1}
                value={form.capacity}
                onChange={(e) => setForm({ ...form, capacity: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Group'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}
