import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  addContactListMembers,
  createCampaign,
  createContactList,
  createCoupon,
  getCampaignReport,
  listCampaigns,
  listContactLists,
  listCoupons,
  prepareCampaign,
  sendCampaign,
  validateCoupon,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type {
  CampaignChannel,
  CampaignData,
  CampaignReport,
  ContactListData,
  CouponData,
  CouponDiscountType,
  CouponValidationResult,
} from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

export default function MarketingPage() {
  const [tab, setTab] = useState<'lists' | 'campaigns' | 'coupons'>('campaigns')

  return (
    <div className="page">
      <div className="page-header">
        <h1>Marketing</h1>
      </div>
      <div className="view-toggle">
        <button type="button" className={tab === 'campaigns' ? 'active' : ''} onClick={() => setTab('campaigns')}>
          Campaigns
        </button>
        <button type="button" className={tab === 'lists' ? 'active' : ''} onClick={() => setTab('lists')}>
          Contact Lists
        </button>
        <button type="button" className={tab === 'coupons' ? 'active' : ''} onClick={() => setTab('coupons')}>
          Coupons
        </button>
      </div>
      {tab === 'campaigns' ? <CampaignsTab /> : tab === 'lists' ? <ContactListsTab /> : <CouponsTab />}
    </div>
  )
}

function ContactListsTab() {
  const [lists, setLists] = useState<ContactListData[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [memberModal, setMemberModal] = useState<ContactListData | null>(null)
  const [customerIds, setCustomerIds] = useState('')
  const [leadIds, setLeadIds] = useState('')
  const [memberError, setMemberError] = useState<string | null>(null)
  const [memberSaving, setMemberSaving] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listContactLists()
      setLists(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load contact lists.'))
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
      await createContactList({ name, description: description || undefined })
      setName('')
      setDescription('')
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this list.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleAddMembers(e: FormEvent) {
    e.preventDefault()
    if (!memberModal) return
    setMemberSaving(true)
    setMemberError(null)
    try {
      await addContactListMembers(memberModal.id, {
        customer_ids: customerIds
          .split(',')
          .map((s) => Number(s.trim()))
          .filter((n) => !Number.isNaN(n) && n > 0),
        lead_ids: leadIds
          .split(',')
          .map((s) => Number(s.trim()))
          .filter((n) => !Number.isNaN(n) && n > 0),
      })
      setCustomerIds('')
      setLeadIds('')
      setMemberModal(null)
      await load()
    } catch (err) {
      setMemberError(getErrorMessage(err, 'Unable to add members.'))
    } finally {
      setMemberSaving(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Contact lists" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New List
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : lists.length === 0 ? (
        <EmptyState message="No contact lists yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Members</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {lists.map((l) => (
                <tr key={l.id}>
                  <td>{l.name}</td>
                  <td>{l.description ?? '—'}</td>
                  <td>{l.members_count ?? 0}</td>
                  <td>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={() => setMemberModal(l)}>
                      Add Members
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Contact List" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={name} onChange={(e) => setName(e.target.value)} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea value={description} onChange={(e) => setDescription(e.target.value)} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create List'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {memberModal ? (
        <Modal title={`Add Members to "${memberModal.name}"`} onClose={() => setMemberModal(null)}>
          <form onSubmit={handleAddMembers} className="stacked-form">
            {memberError ? <div className="state-block error">{memberError}</div> : null}
            <label className="field">
              <span>Customer IDs (comma-separated)</span>
              <input value={customerIds} onChange={(e) => setCustomerIds(e.target.value)} placeholder="1, 2, 3" />
            </label>
            <label className="field">
              <span>Lead IDs (comma-separated)</span>
              <input value={leadIds} onChange={(e) => setLeadIds(e.target.value)} placeholder="4, 5" />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setMemberModal(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={memberSaving}>
                {memberSaving ? 'Adding...' : 'Add Members'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </>
  )
}

const emptyCampaignForm = {
  name: '',
  channel: 'email' as CampaignChannel,
  contact_list_id: '',
  subject: '',
  body: '',
}

function CampaignsTab() {
  const [campaigns, setCampaigns] = useState<CampaignData[]>([])
  const [lists, setLists] = useState<ContactListData[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyCampaignForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [report, setReport] = useState<CampaignReport | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const [campaignRes, listRes] = await Promise.all([listCampaigns(), listContactLists()])
      setCampaigns(campaignRes.data.data ?? [])
      setLists(listRes.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load campaigns.'))
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
      await createCampaign({
        name: form.name,
        channel: form.channel,
        contact_list_id: form.contact_list_id ? Number(form.contact_list_id) : undefined,
        subject: form.subject || undefined,
        body: form.body,
      })
      setForm(emptyCampaignForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this campaign.'))
    } finally {
      setSaving(false)
    }
  }

  async function handlePrepare(id: number) {
    setBusyId(id)
    setActionError(null)
    try {
      await prepareCampaign(id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to prepare this campaign.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleSend(id: number) {
    setBusyId(id)
    setActionError(null)
    try {
      await sendCampaign(id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to send this campaign.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleViewReport(id: number) {
    setActionError(null)
    try {
      const res = await getCampaignReport(id)
      setReport(res.data.data)
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to load this campaign report.'))
    }
  }

  if (notAvailable) return <NotAvailable label="Campaigns" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Campaign
          </button>
        </div>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : campaigns.length === 0 ? (
        <EmptyState message="No campaigns yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Channel</th>
                <th>Status</th>
                <th>Recipients</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {campaigns.map((c) => (
                <tr key={c.id}>
                  <td>{c.name}</td>
                  <td>{titleCase(c.channel)}</td>
                  <td>
                    <Badge text={titleCase(c.status)} tone={statusTone(c.status)} />
                  </td>
                  <td>{c.recipients_count ?? '—'}</td>
                  <td>
                    <div className="row-actions">
                      {c.status === 'draft' || c.status === 'scheduled' ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          disabled={busyId === c.id}
                          onClick={() => handlePrepare(c.id)}
                        >
                          Prepare
                        </button>
                      ) : null}
                      {c.status === 'draft' || c.status === 'scheduled' ? (
                        <button
                          type="button"
                          className="btn btn-primary btn-sm"
                          disabled={busyId === c.id}
                          onClick={() => handleSend(c.id)}
                        >
                          Send
                        </button>
                      ) : null}
                      <button type="button" className="btn btn-ghost btn-sm" onClick={() => handleViewReport(c.id)}>
                        Report
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Campaign" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </label>
            <label className="field">
              <span>Channel</span>
              <select
                value={form.channel}
                onChange={(e) => setForm({ ...form, channel: e.target.value as CampaignChannel })}
              >
                <option value="email">Email</option>
                <option value="sms">SMS</option>
                <option value="whatsapp">WhatsApp</option>
              </select>
            </label>
            <label className="field">
              <span>Contact List</span>
              <select
                value={form.contact_list_id}
                onChange={(e) => setForm({ ...form, contact_list_id: e.target.value })}
              >
                <option value="">— none —</option>
                {lists.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.name}
                  </option>
                ))}
              </select>
            </label>
            {form.channel === 'email' ? (
              <label className="field">
                <span>Subject</span>
                <input value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} />
              </label>
            ) : null}
            <label className="field">
              <span>Message</span>
              <textarea required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Campaign'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {report ? (
        <Modal title={`Report — ${report.campaign.name}`} onClose={() => setReport(null)}>
          <div className="stacked-form">
            <div className="field-hint">
              Sent {report.stats.sent} · Failed {report.stats.failed} · Skipped {report.stats.skipped} · Pending{' '}
              {report.stats.pending} ·{' '}
              {report.stats.delivery_rate_percent !== null ? `${report.stats.delivery_rate_percent}% delivered` : 'no recipients yet'}
            </div>
            {report.failures.length === 0 ? (
              <EmptyState message="No failed or skipped recipients." />
            ) : (
              <ul className="simple-list" style={{ flexDirection: 'column' }}>
                {report.failures.map((f, i) => (
                  <li key={i}>
                    <Badge text={titleCase(f.status)} tone="danger" /> {f.destination || '(no destination)'} —{' '}
                    {f.failure_reason}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </Modal>
      ) : null}
    </>
  )
}

function CouponsTab() {
  const [coupons, setCoupons] = useState<CouponData[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [code, setCode] = useState('')
  const [discountType, setDiscountType] = useState<CouponDiscountType>('percentage')
  const [discountValue, setDiscountValue] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [testModal, setTestModal] = useState<CouponData | null>(null)
  const [testAmount, setTestAmount] = useState('')
  const [testResult, setTestResult] = useState<CouponValidationResult | null>(null)
  const [testError, setTestError] = useState<string | null>(null)
  const [testBusy, setTestBusy] = useState(false)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listCoupons()
      setCoupons(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load coupons.'))
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
      await createCoupon({ code, discount_type: discountType, discount_value: Number(discountValue) })
      setCode('')
      setDiscountValue('')
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this coupon.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleTest(e: FormEvent) {
    e.preventDefault()
    if (!testModal) return
    setTestBusy(true)
    setTestError(null)
    setTestResult(null)
    try {
      const res = await validateCoupon(testModal.code, Number(testAmount))
      setTestResult(res.data.data)
    } catch (err) {
      setTestError(getErrorMessage(err, 'Unable to validate this coupon.'))
    } finally {
      setTestBusy(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Coupons" />

  return (
    <>
      <div className="page-header">
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Coupon
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : coupons.length === 0 ? (
        <EmptyState message="No coupons yet." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Used</th>
                <th>Valid Until</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {coupons.map((c) => (
                <tr key={c.id}>
                  <td>{c.code}</td>
                  <td>
                    {c.discount_type === 'percentage' ? `${c.discount_value}%` : formatCurrency(c.discount_value)}
                  </td>
                  <td>
                    {c.used_count}
                    {c.usage_limit ? ` / ${c.usage_limit}` : ''}
                  </td>
                  <td>{c.valid_until ? formatDate(c.valid_until) : '—'}</td>
                  <td>
                    <Badge text={c.is_active ? 'Active' : 'Inactive'} tone={c.is_active ? 'success' : 'default'} />
                  </td>
                  <td>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      onClick={() => {
                        setTestModal(c)
                        setTestResult(null)
                      }}
                    >
                      Test
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showForm ? (
        <Modal title="New Coupon" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Code</span>
              <input required value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} />
            </label>
            <label className="field">
              <span>Discount Type</span>
              <select value={discountType} onChange={(e) => setDiscountType(e.target.value as CouponDiscountType)}>
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed Amount</option>
              </select>
            </label>
            <label className="field">
              <span>Discount Value</span>
              <input
                type="number"
                min={0}
                required
                value={discountValue}
                onChange={(e) => setDiscountValue(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Coupon'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {testModal ? (
        <Modal title={`Test Coupon "${testModal.code}"`} onClose={() => setTestModal(null)}>
          <form onSubmit={handleTest} className="stacked-form">
            {testError ? <div className="state-block error">{testError}</div> : null}
            <label className="field">
              <span>Booking Amount</span>
              <input
                type="number"
                min={0}
                required
                value={testAmount}
                onChange={(e) => setTestAmount(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="submit" className="btn btn-primary" disabled={testBusy}>
                {testBusy ? 'Checking...' : 'Validate'}
              </button>
            </div>
            {testResult ? (
              <div className={`state-block ${testResult.valid ? 'empty' : 'error'}`}>
                {testResult.valid
                  ? `Valid — discount: ${formatCurrency(testResult.discount)}`
                  : `Invalid — ${testResult.reason}`}
              </div>
            ) : null}
          </form>
        </Modal>
      ) : null}
    </>
  )
}
