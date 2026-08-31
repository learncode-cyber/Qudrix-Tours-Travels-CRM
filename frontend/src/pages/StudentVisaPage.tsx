import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import {
  createStudentVisaApplication,
  listStudentVisaApplications,
  recordStudentVisaOfferLetter,
  scheduleStudentVisaEmbassyAppointment,
  updateStudentVisaStatus,
  updateStudentVisaVisaStatus,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal, NotAvailable } from '../components/ui'
import type { StudentVisaApplication } from '../types'
import { formatCurrency, getErrorMessage, statusTone, titleCase } from '../utils/format'

const STATUS_FILTERS = [
  'all',
  'inquiry',
  'documents_pending',
  'applied',
  'offer_received',
  'visa_appointment_scheduled',
  'visa_submitted',
  'visa_approved',
  'visa_rejected',
  'enrolled',
]

const APPLICATION_STATUS_OPTIONS = STATUS_FILTERS.filter((s) => s !== 'all')
const VISA_STATUS_OPTIONS = ['not_applied', 'submitted', 'approved', 'rejected']

const emptyForm = {
  student_name: '',
  destination_country: '',
  date_of_birth: '',
  university: '',
  course: '',
  intake: '',
  service_fee: '',
  service_fee_currency: 'USD',
}

export default function StudentVisaPage() {
  const [applications, setApplications] = useState<StudentVisaApplication[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [statusFilter, setStatusFilter] = useState('all')
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const [statusTarget, setStatusTarget] = useState<StudentVisaApplication | null>(null)
  const [statusValue, setStatusValue] = useState('inquiry')
  const [statusBusy, setStatusBusy] = useState(false)
  const [statusError, setStatusError] = useState<string | null>(null)

  const [offerTarget, setOfferTarget] = useState<StudentVisaApplication | null>(null)
  const [offerDate, setOfferDate] = useState('')
  const [offerBusy, setOfferBusy] = useState(false)
  const [offerError, setOfferError] = useState<string | null>(null)

  const [appointmentTarget, setAppointmentTarget] = useState<StudentVisaApplication | null>(null)
  const [appointmentValue, setAppointmentValue] = useState('')
  const [appointmentBusy, setAppointmentBusy] = useState(false)
  const [appointmentError, setAppointmentError] = useState<string | null>(null)

  const [visaStatusTarget, setVisaStatusTarget] = useState<StudentVisaApplication | null>(null)
  const [visaStatusValue, setVisaStatusValue] = useState('not_applied')
  const [visaStatusBusy, setVisaStatusBusy] = useState(false)
  const [visaStatusError, setVisaStatusError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const res = await listStudentVisaApplications(
        statusFilter === 'all' ? undefined : { application_status: statusFilter },
      )
      setApplications(res.data.data ?? [])
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load student visa applications.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter])

  const filtered = useMemo(() => applications, [applications])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createStudentVisaApplication({
        student_name: form.student_name,
        destination_country: form.destination_country.toUpperCase(),
        date_of_birth: form.date_of_birth || undefined,
        university: form.university || undefined,
        course: form.course || undefined,
        intake: form.intake || undefined,
        service_fee: form.service_fee ? Number(form.service_fee) : undefined,
        service_fee_currency: form.service_fee_currency || undefined,
      } as Partial<StudentVisaApplication>)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create this application.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleUpdateStatus(e: FormEvent) {
    e.preventDefault()
    if (!statusTarget) return
    setStatusBusy(true)
    setStatusError(null)
    try {
      await updateStudentVisaStatus(statusTarget.id, statusValue)
      setStatusTarget(null)
      await load()
    } catch (err) {
      setStatusError(getErrorMessage(err, 'Unable to update the application status.'))
    } finally {
      setStatusBusy(false)
    }
  }

  async function handleRecordOffer(e: FormEvent) {
    e.preventDefault()
    if (!offerTarget) return
    setOfferBusy(true)
    setOfferError(null)
    try {
      await recordStudentVisaOfferLetter(offerTarget.id, offerDate)
      setOfferTarget(null)
      setOfferDate('')
      await load()
    } catch (err) {
      setOfferError(getErrorMessage(err, 'Unable to record the offer letter.'))
    } finally {
      setOfferBusy(false)
    }
  }

  async function handleScheduleAppointment(e: FormEvent) {
    e.preventDefault()
    if (!appointmentTarget) return
    setAppointmentBusy(true)
    setAppointmentError(null)
    try {
      await scheduleStudentVisaEmbassyAppointment(appointmentTarget.id, appointmentValue)
      setAppointmentTarget(null)
      setAppointmentValue('')
      await load()
    } catch (err) {
      setAppointmentError(getErrorMessage(err, 'Unable to schedule the embassy appointment.'))
    } finally {
      setAppointmentBusy(false)
    }
  }

  async function handleUpdateVisaStatus(e: FormEvent) {
    e.preventDefault()
    if (!visaStatusTarget) return
    setVisaStatusBusy(true)
    setVisaStatusError(null)
    try {
      await updateStudentVisaVisaStatus(visaStatusTarget.id, visaStatusValue)
      setVisaStatusTarget(null)
      await load()
    } catch (err) {
      setVisaStatusError(getErrorMessage(err, 'Unable to update the visa status.'))
    } finally {
      setVisaStatusBusy(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Student visa applications" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Student Visa</h1>
      </div>
      <div className="page-header">
        <div className="view-toggle">
          {STATUS_FILTERS.map((s) => (
            <button
              key={s}
              type="button"
              className={statusFilter === s ? 'active' : ''}
              onClick={() => setStatusFilter(s)}
            >
              {titleCase(s)}
            </button>
          ))}
        </div>
        <div className="header-actions">
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Application
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState message="No student visa applications found." />
      ) : (
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Destination</th>
                <th>University</th>
                <th>Course</th>
                <th>Intake</th>
                <th>Application Status</th>
                <th>Visa Status</th>
                <th>Service Fee</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((a) => (
                <tr key={a.id}>
                  <td>{a.student_name}</td>
                  <td>{a.destination_country}</td>
                  <td>{a.university ?? '—'}</td>
                  <td>{a.course ?? '—'}</td>
                  <td>{a.intake ?? '—'}</td>
                  <td>
                    <Badge text={titleCase(a.application_status)} tone={statusTone(a.application_status)} />
                  </td>
                  <td>
                    <Badge text={titleCase(a.visa_status)} tone={statusTone(a.visa_status)} />
                  </td>
                  <td>
                    {a.service_fee ? `${formatCurrency(a.service_fee)} ${a.service_fee_currency ?? ''}` : '—'}
                  </td>
                  <td>
                    <div className="row-actions">
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => {
                          setStatusTarget(a)
                          setStatusValue(a.application_status)
                          setStatusError(null)
                        }}
                      >
                        Update Status
                      </button>
                      {!a.offer_letter_received ? (
                        <button
                          type="button"
                          className="btn btn-ghost btn-sm"
                          onClick={() => {
                            setOfferTarget(a)
                            setOfferDate('')
                            setOfferError(null)
                          }}
                        >
                          Record Offer Letter
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => {
                          setAppointmentTarget(a)
                          setAppointmentValue('')
                          setAppointmentError(null)
                        }}
                      >
                        Schedule Appointment
                      </button>
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={() => {
                          setVisaStatusTarget(a)
                          setVisaStatusValue(a.visa_status)
                          setVisaStatusError(null)
                        }}
                      >
                        Update Visa Status
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
        <Modal title="New Student Visa Application" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Student Name</span>
              <input
                required
                value={form.student_name}
                onChange={(e) => setForm({ ...form, student_name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Destination Country</span>
              <input
                required
                maxLength={2}
                value={form.destination_country}
                onChange={(e) => setForm({ ...form, destination_country: e.target.value })}
              />
              <p className="field-hint">2-letter ISO code (e.g. GB, US).</p>
            </label>
            <label className="field">
              <span>Date of Birth</span>
              <input
                type="date"
                value={form.date_of_birth}
                onChange={(e) => setForm({ ...form, date_of_birth: e.target.value })}
              />
            </label>
            <label className="field">
              <span>University</span>
              <input
                value={form.university}
                onChange={(e) => setForm({ ...form, university: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Course</span>
              <input value={form.course} onChange={(e) => setForm({ ...form, course: e.target.value })} />
            </label>
            <label className="field">
              <span>Intake</span>
              <input
                placeholder="Fall 2027"
                value={form.intake}
                onChange={(e) => setForm({ ...form, intake: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Service Fee</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={form.service_fee}
                onChange={(e) => setForm({ ...form, service_fee: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Service Fee Currency</span>
              <input
                maxLength={3}
                value={form.service_fee_currency}
                onChange={(e) => setForm({ ...form, service_fee_currency: e.target.value.toUpperCase() })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Application'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {statusTarget ? (
        <Modal title={`Update Status — ${statusTarget.student_name}`} onClose={() => setStatusTarget(null)}>
          <form onSubmit={handleUpdateStatus} className="stacked-form">
            {statusError ? <div className="state-block error">{statusError}</div> : null}
            <label className="field">
              <span>Application Status</span>
              <select value={statusValue} onChange={(e) => setStatusValue(e.target.value)}>
                {APPLICATION_STATUS_OPTIONS.map((s) => (
                  <option key={s} value={s}>
                    {titleCase(s)}
                  </option>
                ))}
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setStatusTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={statusBusy}>
                {statusBusy ? 'Saving...' : 'Update Status'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {offerTarget ? (
        <Modal title={`Record Offer Letter — ${offerTarget.student_name}`} onClose={() => setOfferTarget(null)}>
          <form onSubmit={handleRecordOffer} className="stacked-form">
            {offerError ? <div className="state-block error">{offerError}</div> : null}
            <label className="field">
              <span>Offer Letter Date</span>
              <input required type="date" value={offerDate} onChange={(e) => setOfferDate(e.target.value)} />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setOfferTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={offerBusy}>
                {offerBusy ? 'Saving...' : 'Record Offer Letter'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {appointmentTarget ? (
        <Modal
          title={`Schedule Embassy Appointment — ${appointmentTarget.student_name}`}
          onClose={() => setAppointmentTarget(null)}
        >
          <form onSubmit={handleScheduleAppointment} className="stacked-form">
            {appointmentError ? <div className="state-block error">{appointmentError}</div> : null}
            <label className="field">
              <span>Appointment Date &amp; Time</span>
              <input
                required
                type="datetime-local"
                value={appointmentValue}
                onChange={(e) => setAppointmentValue(e.target.value)}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setAppointmentTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={appointmentBusy}>
                {appointmentBusy ? 'Saving...' : 'Schedule Appointment'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {visaStatusTarget ? (
        <Modal title={`Update Visa Status — ${visaStatusTarget.student_name}`} onClose={() => setVisaStatusTarget(null)}>
          <form onSubmit={handleUpdateVisaStatus} className="stacked-form">
            {visaStatusError ? <div className="state-block error">{visaStatusError}</div> : null}
            <label className="field">
              <span>Visa Status</span>
              <select value={visaStatusValue} onChange={(e) => setVisaStatusValue(e.target.value)}>
                {VISA_STATUS_OPTIONS.map((s) => (
                  <option key={s} value={s}>
                    {titleCase(s)}
                  </option>
                ))}
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setVisaStatusTarget(null)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={visaStatusBusy}>
                {visaStatusBusy ? 'Saving...' : 'Update Visa Status'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
