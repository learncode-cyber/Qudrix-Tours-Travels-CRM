import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  assignPilgrimRoom,
  assignPilgrimTransport,
  createPilgrim,
  getHajjUmrahGroup,
  getHajjUmrahGroupReport,
  listPilgrims,
  recordPilgrimPayment,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, StatCard } from '../components/ui'
import type { HajjPackage, HajjUmrahGroup, HajjUmrahGroupReport, Pilgrim, UmrahPackage } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyPilgrimForm = {
  name: '',
  passport_number: '',
  passport_expiry: '',
  gender: '',
  date_of_birth: '',
  mahram_name: '',
  amount_due: '',
}

type GroupWithExtras = HajjUmrahGroup & {
  seats_available?: number
  package?: HajjPackage | UmrahPackage | null
}

export default function HajjUmrahGroupDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [tab, setTab] = useState<'pilgrims' | 'report'>('pilgrims')

  const [group, setGroup] = useState<GroupWithExtras | null>(null)
  const [groupLoading, setGroupLoading] = useState(true)
  const [groupError, setGroupError] = useState<string | null>(null)

  const [pilgrims, setPilgrims] = useState<Pilgrim[]>([])
  const [pLoading, setPLoading] = useState(true)
  const [pError, setPError] = useState<string | null>(null)
  const [showPilgrimForm, setShowPilgrimForm] = useState(false)
  const [pilgrimForm, setPilgrimForm] = useState(emptyPilgrimForm)
  const [pilgrimSaving, setPilgrimSaving] = useState(false)
  const [pilgrimFormError, setPilgrimFormError] = useState<string | null>(null)

  const [roomTarget, setRoomTarget] = useState<Pilgrim | null>(null)
  const [roomNumber, setRoomNumber] = useState('')
  const [roomHotelId, setRoomHotelId] = useState('')
  const [roomBusy, setRoomBusy] = useState(false)
  const [roomError, setRoomError] = useState<string | null>(null)

  const [transportTarget, setTransportTarget] = useState<Pilgrim | null>(null)
  const [transportValue, setTransportValue] = useState('')
  const [transportBusy, setTransportBusy] = useState(false)
  const [transportError, setTransportError] = useState<string | null>(null)

  const [paymentTarget, setPaymentTarget] = useState<Pilgrim | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('')
  const [paymentBusy, setPaymentBusy] = useState(false)
  const [paymentError, setPaymentError] = useState<string | null>(null)

  const [report, setReport] = useState<HajjUmrahGroupReport | null>(null)
  const [reportLoading, setReportLoading] = useState(false)
  const [reportError, setReportError] = useState<string | null>(null)
  const [reportLoaded, setReportLoaded] = useState(false)

  async function loadGroup() {
    if (!id) return
    setGroupLoading(true)
    setGroupError(null)
    try {
      const res = await getHajjUmrahGroup(id)
      setGroup(res.data.data)
    } catch (err) {
      setGroupError(getErrorMessage(err, 'Unable to load this group.'))
    } finally {
      setGroupLoading(false)
    }
  }

  async function loadPilgrims() {
    if (!id) return
    setPLoading(true)
    setPError(null)
    try {
      const res = await listPilgrims({ hajj_umrah_group_id: id })
      setPilgrims(res.data.data ?? [])
    } catch (err) {
      setPError(getErrorMessage(err, 'Unable to load pilgrims.'))
    } finally {
      setPLoading(false)
    }
  }

  useEffect(() => {
    loadGroup()
    loadPilgrims()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function loadReport() {
    if (!id) return
    setReportLoading(true)
    setReportError(null)
    try {
      const res = await getHajjUmrahGroupReport(id)
      setReport(res.data.data)
      setReportLoaded(true)
    } catch (err) {
      setReportError(getErrorMessage(err, 'Unable to load the report.'))
    } finally {
      setReportLoading(false)
    }
  }

  function switchTab(next: 'pilgrims' | 'report') {
    setTab(next)
    if (next === 'report' && !reportLoaded) {
      loadReport()
    }
  }

  async function handleCreatePilgrim(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setPilgrimSaving(true)
    setPilgrimFormError(null)
    try {
      await createPilgrim({
        hajj_umrah_group_id: Number(id),
        name: pilgrimForm.name,
        passport_number: pilgrimForm.passport_number || undefined,
        passport_expiry: pilgrimForm.passport_expiry || undefined,
        gender: pilgrimForm.gender || undefined,
        date_of_birth: pilgrimForm.date_of_birth || undefined,
        mahram_name: pilgrimForm.mahram_name || undefined,
        amount_due: pilgrimForm.amount_due ? Number(pilgrimForm.amount_due) : undefined,
      } as Partial<Pilgrim>)
      setPilgrimForm(emptyPilgrimForm)
      setShowPilgrimForm(false)
      await Promise.all([loadPilgrims(), loadGroup()])
      setReportLoaded(false)
    } catch (err) {
      setPilgrimFormError(getErrorMessage(err, 'Unable to register this pilgrim.'))
    } finally {
      setPilgrimSaving(false)
    }
  }

  async function handleAssignRoom(e: FormEvent) {
    e.preventDefault()
    if (!roomTarget) return
    setRoomBusy(true)
    setRoomError(null)
    try {
      await assignPilgrimRoom(roomTarget.id, {
        room_number: roomNumber,
        hotel_id: roomHotelId ? Number(roomHotelId) : undefined,
      })
      setRoomTarget(null)
      setRoomNumber('')
      setRoomHotelId('')
      await loadPilgrims()
    } catch (err) {
      setRoomError(getErrorMessage(err, 'Unable to assign a room.'))
    } finally {
      setRoomBusy(false)
    }
  }

  async function handleAssignTransport(e: FormEvent) {
    e.preventDefault()
    if (!transportTarget) return
    setTransportBusy(true)
    setTransportError(null)
    try {
      await assignPilgrimTransport(transportTarget.id, transportValue)
      setTransportTarget(null)
      setTransportValue('')
      await loadPilgrims()
    } catch (err) {
      setTransportError(getErrorMessage(err, 'Unable to assign transport.'))
    } finally {
      setTransportBusy(false)
    }
  }

  async function handleRecordPayment(e: FormEvent) {
    e.preventDefault()
    if (!paymentTarget) return
    setPaymentBusy(true)
    setPaymentError(null)
    try {
      await recordPilgrimPayment(paymentTarget.id, Number(paymentAmount))
      setPaymentTarget(null)
      setPaymentAmount('')
      await loadPilgrims()
      setReportLoaded(false)
    } catch (err) {
      setPaymentError(getErrorMessage(err, 'Unable to record this payment.'))
    } finally {
      setPaymentBusy(false)
    }
  }

  return (
    <div className="page">
      <Link to="/hajj-umrah" className="back-link">
        &larr; Hajj &amp; Umrah
      </Link>

      {groupLoading ? (
        <Loading />
      ) : groupError ? (
        <ErrorBanner message={groupError} />
      ) : group ? (
        <div className="page-header">
          <div>
            <h1>{group.name}</h1>
            <p className="field-hint">
              {titleCase(group.package_type)} · {formatDate(group.departure_date)} &rarr;{' '}
              {formatDate(group.return_date)} · Capacity {group.capacity} · Seats available{' '}
              {group.seats_available ?? '—'}
            </p>
          </div>
          <Badge text={titleCase(group.status)} tone={statusTone(group.status)} />
        </div>
      ) : null}

      <div className="view-toggle">
        <button type="button" className={tab === 'pilgrims' ? 'active' : ''} onClick={() => switchTab('pilgrims')}>
          Pilgrims
        </button>
        <button type="button" className={tab === 'report' ? 'active' : ''} onClick={() => switchTab('report')}>
          Report
        </button>
      </div>

      {tab === 'pilgrims' ? (
        <>
          <div className="page-header">
            <div className="header-actions">
              <button type="button" className="btn btn-primary" onClick={() => setShowPilgrimForm(true)}>
                + New Pilgrim
              </button>
            </div>
          </div>

          {pLoading ? (
            <Loading />
          ) : pError ? (
            <ErrorBanner message={pError} />
          ) : pilgrims.length === 0 ? (
            <EmptyState message="No pilgrims registered in this group yet." />
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Passport</th>
                    <th>Room</th>
                    <th>Transport</th>
                    <th>Amount Due</th>
                    <th>Amount Paid</th>
                    <th>Balance</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {pilgrims.map((p) => (
                    <tr key={p.id}>
                      <td>{p.name}</td>
                      <td>{p.passport_number ?? '—'}</td>
                      <td>{p.room_number ?? '—'}</td>
                      <td>{p.transport_assignment ?? '—'}</td>
                      <td>{formatCurrency(p.amount_due)}</td>
                      <td>{formatCurrency(p.amount_paid)}</td>
                      <td>{formatCurrency(Number(p.amount_due) - Number(p.amount_paid))}</td>
                      <td>
                        <Badge text={titleCase(p.payment_status)} tone={statusTone(p.payment_status)} />
                      </td>
                      <td>
                        <Badge text={titleCase(p.status)} tone={statusTone(p.status)} />
                      </td>
                      <td>
                        <div className="row-actions">
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            onClick={() => {
                              setRoomTarget(p)
                              setRoomNumber(p.room_number ?? '')
                              setRoomHotelId(p.hotel_id ? String(p.hotel_id) : '')
                              setRoomError(null)
                            }}
                          >
                            Assign Room
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            onClick={() => {
                              setTransportTarget(p)
                              setTransportValue(p.transport_assignment ?? '')
                              setTransportError(null)
                            }}
                          >
                            Assign Transport
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            onClick={() => {
                              setPaymentTarget(p)
                              setPaymentAmount('')
                              setPaymentError(null)
                            }}
                          >
                            Record Payment
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      ) : null}

      {tab === 'report' ? (
        reportLoading ? (
          <Loading />
        ) : reportError ? (
          <ErrorBanner message={reportError} />
        ) : report ? (
          <>
            <div className="stat-grid">
              <StatCard label="Total Pilgrims" value={report.total_pilgrims} />
              <StatCard label="Seats Available" value={report.seats_available} />
              <StatCard label="Total Amount Due" value={formatCurrency(report.total_amount_due)} />
              <StatCard label="Total Amount Paid" value={formatCurrency(report.total_amount_paid)} />
              <StatCard label="Total Balance" value={formatCurrency(report.total_balance)} />
              <StatCard label="Unassigned Rooms" value={report.unassigned_rooms} />
            </div>
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Status</th>
                    <th>Count</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(report.by_status).map(([status, count]) => (
                    <tr key={status}>
                      <td>{titleCase(status)}</td>
                      <td>{count}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        ) : (
          <EmptyState message="No report data available." />
        )
      ) : null}

      {showPilgrimForm ? (
        <Modal title="New Pilgrim" onClose={() => setShowPilgrimForm(false)}>
          <form onSubmit={handleCreatePilgrim} className="stacked-form">
            {pilgrimFormError ? <div className="state-block error">{pilgrimFormError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input
                required
                value={pilgrimForm.name}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Passport Number</span>
              <input
                value={pilgrimForm.passport_number}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, passport_number: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Passport Expiry</span>
              <input
                type="date"
                value={pilgrimForm.passport_expiry}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, passport_expiry: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Gender</span>
              <select
                value={pilgrimForm.gender}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, gender: e.target.value })}
              >
                <option value="">Not specified</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </label>
            <label className="field">
              <span>Date of Birth</span>
              <input
                type="date"
                value={pilgrimForm.date_of_birth}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, date_of_birth: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Mahram Name</span>
              <input
                value={pilgrimForm.mahram_name}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, mahram_name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Amount Due</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={pilgrimForm.amount_due}
                onChange={(e) => setPilgrimForm({ ...pilgrimForm, amount_due: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowPilgrimForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={pilgrimSaving}>
                {pilgrimSaving ? 'Saving...' : 'Register Pilgrim'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {roomTarget ? (
        <Modal title={`Assign Room — ${roomTarget.name}`} onClose={() => setRoomTarget(null)}>
          <form onSubmit={handleAssignRoom} className="stacked-form">
            {roomError ? <div className="state-block error">{roomError}</div> : null}
            <label className="field">
              <span>Room Number</span>
              <input required value={roomNumber} onChange={(e) => setRoomNumber(e.target.value)} />
            </label>
            <label className="field">
              <span>Hotel ID</span>
              <input
                type="number"
                min={1}
                value={roomHotelId}
                onChange={(e) => setRoomHotelId(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setRoomTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={roomBusy}>
                {roomBusy ? 'Saving...' : 'Assign Room'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {transportTarget ? (
        <Modal title={`Assign Transport — ${transportTarget.name}`} onClose={() => setTransportTarget(null)}>
          <form onSubmit={handleAssignTransport} className="stacked-form">
            {transportError ? <div className="state-block error">{transportError}</div> : null}
            <label className="field">
              <span>Transport Assignment</span>
              <input
                required
                placeholder="Bus 3"
                value={transportValue}
                onChange={(e) => setTransportValue(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setTransportTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={transportBusy}>
                {transportBusy ? 'Saving...' : 'Assign Transport'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {paymentTarget ? (
        <Modal title={`Record Payment — ${paymentTarget.name}`} onClose={() => setPaymentTarget(null)}>
          <form onSubmit={handleRecordPayment} className="stacked-form">
            {paymentError ? <div className="state-block error">{paymentError}</div> : null}
            <label className="field">
              <span>Amount</span>
              <input
                required
                type="number"
                min={0.01}
                step="0.01"
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setPaymentTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={paymentBusy}>
                {paymentBusy ? 'Saving...' : 'Record Payment'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
