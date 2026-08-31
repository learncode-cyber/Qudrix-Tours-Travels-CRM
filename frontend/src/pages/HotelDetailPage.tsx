import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  createHotelRoomType,
  createRoomBlock,
  getHotel,
  listHotelRoomTypes,
  listRoomBlocks,
  releaseRoomBlock,
} from '../api/endpoints'
import { Badge, EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Hotel, HotelRoomType, RoomBlock } from '../types'
import { formatCurrency, formatDate, getErrorMessage, statusTone, titleCase } from '../utils/format'

const emptyRoomTypeForm = {
  name: '',
  capacity: '',
  total_rooms: '',
  price_per_night: '',
  currency: 'USD',
}

const emptyBlockForm = {
  hotel_room_type_id: '',
  group_booking_id: '',
  name: '',
  blocked_rooms: '',
  start_date: '',
  end_date: '',
  notes: '',
}

export default function HotelDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [tab, setTab] = useState<'room-types' | 'room-blocks'>('room-types')

  const [hotel, setHotel] = useState<Hotel | null>(null)
  const [hotelLoading, setHotelLoading] = useState(true)
  const [hotelError, setHotelError] = useState<string | null>(null)

  const [roomTypes, setRoomTypes] = useState<HotelRoomType[]>([])
  const [rtLoading, setRtLoading] = useState(true)
  const [rtError, setRtError] = useState<string | null>(null)
  const [showRtForm, setShowRtForm] = useState(false)
  const [rtForm, setRtForm] = useState(emptyRoomTypeForm)
  const [rtSaving, setRtSaving] = useState(false)
  const [rtFormError, setRtFormError] = useState<string | null>(null)

  const [blocks, setBlocks] = useState<RoomBlock[]>([])
  const [blocksLoading, setBlocksLoading] = useState(true)
  const [blocksError, setBlocksError] = useState<string | null>(null)
  const [showBlockForm, setShowBlockForm] = useState(false)
  const [blockForm, setBlockForm] = useState(emptyBlockForm)
  const [blockSaving, setBlockSaving] = useState(false)
  const [blockFormError, setBlockFormError] = useState<string | null>(null)
  const [blockActionError, setBlockActionError] = useState<string | null>(null)
  const [busyBlockId, setBusyBlockId] = useState<number | null>(null)

  async function loadHotel() {
    if (!id) return
    setHotelLoading(true)
    setHotelError(null)
    try {
      const res = await getHotel(id)
      setHotel(res.data.data)
    } catch (err) {
      setHotelError(getErrorMessage(err, 'Unable to load this hotel.'))
    } finally {
      setHotelLoading(false)
    }
  }

  async function loadRoomTypes() {
    if (!id) return
    setRtLoading(true)
    setRtError(null)
    try {
      const res = await listHotelRoomTypes(id)
      setRoomTypes(res.data.data ?? [])
    } catch (err) {
      setRtError(getErrorMessage(err, 'Unable to load room types.'))
    } finally {
      setRtLoading(false)
    }
  }

  async function loadBlocks() {
    setBlocksLoading(true)
    setBlocksError(null)
    try {
      const res = await listRoomBlocks()
      setBlocks((res.data.data ?? []).filter((b) => String(b.hotel_id) === String(id)))
    } catch (err) {
      setBlocksError(getErrorMessage(err, 'Unable to load room blocks.'))
    } finally {
      setBlocksLoading(false)
    }
  }

  useEffect(() => {
    loadHotel()
    loadRoomTypes()
    loadBlocks()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  async function handleCreateRoomType(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setRtSaving(true)
    setRtFormError(null)
    try {
      await createHotelRoomType(id, {
        name: rtForm.name,
        capacity: rtForm.capacity ? Number(rtForm.capacity) : undefined,
        total_rooms: rtForm.total_rooms ? Number(rtForm.total_rooms) : undefined,
        price_per_night: rtForm.price_per_night ? Number(rtForm.price_per_night) : undefined,
        currency: rtForm.currency,
      })
      setRtForm(emptyRoomTypeForm)
      setShowRtForm(false)
      await loadRoomTypes()
    } catch (err) {
      setRtFormError(getErrorMessage(err, 'Unable to create room type.'))
    } finally {
      setRtSaving(false)
    }
  }

  async function handleCreateBlock(e: FormEvent) {
    e.preventDefault()
    if (!id) return
    setBlockSaving(true)
    setBlockFormError(null)
    try {
      await createRoomBlock({
        hotel_id: Number(id),
        hotel_room_type_id: Number(blockForm.hotel_room_type_id),
        group_booking_id: blockForm.group_booking_id ? Number(blockForm.group_booking_id) : undefined,
        name: blockForm.name || undefined,
        blocked_rooms: Number(blockForm.blocked_rooms),
        start_date: blockForm.start_date,
        end_date: blockForm.end_date,
        notes: blockForm.notes || undefined,
      })
      setBlockForm(emptyBlockForm)
      setShowBlockForm(false)
      await loadBlocks()
    } catch (err) {
      setBlockFormError(getErrorMessage(err, 'Unable to create room block.'))
    } finally {
      setBlockSaving(false)
    }
  }

  async function handleRelease(block: RoomBlock) {
    const roomsStr = window.prompt('How many rooms to release?', '1')
    if (!roomsStr) return
    const rooms = Number(roomsStr)
    if (!rooms || rooms <= 0) return
    setBusyBlockId(block.id)
    setBlockActionError(null)
    try {
      await releaseRoomBlock(block.id, rooms)
      await loadBlocks()
    } catch (err) {
      setBlockActionError(getErrorMessage(err, 'Unable to release rooms.'))
    } finally {
      setBusyBlockId(null)
    }
  }

  if (hotelLoading) return <Loading label="Loading hotel..." />
  if (hotelError) return <ErrorBanner message={hotelError} />
  if (!hotel) return <ErrorBanner message="Hotel not found." />

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/hotels" className="back-link">
            ← Back to hotels
          </Link>
          <h1>{hotel.name}</h1>
        </div>
      </div>

      <section className="panel">
        <h2>Details</h2>
        <div className="detail-grid">
          <div>
            <span className="detail-label">Location</span>
            <span>
              {hotel.city ?? '—'}
              {hotel.country ? `, ${hotel.country}` : ''}
            </span>
          </div>
          <div>
            <span className="detail-label">Address</span>
            <span>{hotel.address ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Phone</span>
            <span>{hotel.phone ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Email</span>
            <span>{hotel.email ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Star Rating</span>
            <span>{hotel.star_rating ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Rooms</span>
            <span>
              {hotel.available_rooms ?? '—'} / {hotel.total_rooms ?? '—'} available
            </span>
          </div>
          <div>
            <span className="detail-label">Price / Night</span>
            <span>{formatCurrency(hotel.price_per_night)}</span>
          </div>
        </div>
      </section>

      <div className="view-toggle">
        <button type="button" className={tab === 'room-types' ? 'active' : ''} onClick={() => setTab('room-types')}>
          Room Types
        </button>
        <button type="button" className={tab === 'room-blocks' ? 'active' : ''} onClick={() => setTab('room-blocks')}>
          Room Blocks
        </button>
      </div>

      {tab === 'room-types' ? (
        <section className="panel">
          <div className="page-header">
            <h2>Room Types</h2>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowRtForm(true)}>
              + New Room Type
            </button>
          </div>
          {rtLoading ? (
            <Loading />
          ) : rtError ? (
            <ErrorBanner message={rtError} />
          ) : roomTypes.length === 0 ? (
            <EmptyState message="No room types for this hotel yet." />
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Capacity</th>
                    <th>Rooms</th>
                    <th>Price / Night</th>
                  </tr>
                </thead>
                <tbody>
                  {roomTypes.map((rt) => (
                    <tr key={rt.id}>
                      <td>{rt.name}</td>
                      <td>{rt.capacity ?? '—'}</td>
                      <td>
                        {rt.available_rooms ?? '—'} / {rt.total_rooms ?? '—'}
                      </td>
                      <td>{formatCurrency(rt.price_per_night)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : (
        <section className="panel">
          <div className="page-header">
            <h2>Room Blocks</h2>
            <button
              type="button"
              className="btn btn-primary btn-sm"
              onClick={() => setShowBlockForm(true)}
              disabled={roomTypes.length === 0}
            >
              + New Room Block
            </button>
          </div>
          {blockActionError ? <ErrorBanner message={blockActionError} /> : null}
          {blocksLoading ? (
            <Loading />
          ) : blocksError ? (
            <ErrorBanner message={blocksError} />
          ) : blocks.length === 0 ? (
            <EmptyState message="No room blocks for this hotel." />
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Room Type</th>
                    <th>Blocked</th>
                    <th>Released</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {blocks.map((b) => (
                    <tr key={b.id}>
                      <td>{b.name ?? `Block #${b.id}`}</td>
                      <td>{b.roomType?.name ?? `Room Type #${b.hotel_room_type_id}`}</td>
                      <td>{b.blocked_rooms}</td>
                      <td>{b.released_rooms ?? 0}</td>
                      <td>
                        {formatDate(b.start_date)} – {formatDate(b.end_date)}
                      </td>
                      <td>
                        <Badge text={titleCase(b.status)} tone={statusTone(b.status)} />
                      </td>
                      <td>
                        {b.status !== 'released' ? (
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            disabled={busyBlockId === b.id}
                            onClick={() => handleRelease(b)}
                          >
                            Release
                          </button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}

      {showRtForm ? (
        <Modal title="New Room Type" onClose={() => setShowRtForm(false)}>
          <form onSubmit={handleCreateRoomType} className="stacked-form">
            {rtFormError ? <div className="state-block error">{rtFormError}</div> : null}
            <label className="field">
              <span>Name</span>
              <input
                required
                value={rtForm.name}
                onChange={(e) => setRtForm({ ...rtForm, name: e.target.value })}
                placeholder="Deluxe Double"
              />
            </label>
            <label className="field">
              <span>Capacity</span>
              <input
                type="number"
                min={1}
                value={rtForm.capacity}
                onChange={(e) => setRtForm({ ...rtForm, capacity: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Total Rooms</span>
              <input
                type="number"
                min={0}
                value={rtForm.total_rooms}
                onChange={(e) => setRtForm({ ...rtForm, total_rooms: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Price / Night</span>
              <input
                type="number"
                min={0}
                value={rtForm.price_per_night}
                onChange={(e) => setRtForm({ ...rtForm, price_per_night: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Currency</span>
              <input
                value={rtForm.currency}
                onChange={(e) => setRtForm({ ...rtForm, currency: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowRtForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={rtSaving}>
                {rtSaving ? 'Saving...' : 'Create Room Type'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}

      {showBlockForm ? (
        <Modal title="New Room Block" onClose={() => setShowBlockForm(false)}>
          <form onSubmit={handleCreateBlock} className="stacked-form">
            {blockFormError ? <div className="state-block error">{blockFormError}</div> : null}
            <label className="field">
              <span>Room Type</span>
              <select
                required
                value={blockForm.hotel_room_type_id}
                onChange={(e) => setBlockForm({ ...blockForm, hotel_room_type_id: e.target.value })}
              >
                <option value="">Select a room type...</option>
                {roomTypes.map((rt) => (
                  <option key={rt.id} value={rt.id}>
                    {rt.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Block Name</span>
              <input
                value={blockForm.name}
                onChange={(e) => setBlockForm({ ...blockForm, name: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Group Booking ID</span>
              <input
                value={blockForm.group_booking_id}
                onChange={(e) => setBlockForm({ ...blockForm, group_booking_id: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Blocked Rooms</span>
              <input
                type="number"
                min={1}
                required
                value={blockForm.blocked_rooms}
                onChange={(e) => setBlockForm({ ...blockForm, blocked_rooms: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Start Date</span>
              <input
                type="date"
                required
                value={blockForm.start_date}
                onChange={(e) => setBlockForm({ ...blockForm, start_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>End Date</span>
              <input
                type="date"
                required
                value={blockForm.end_date}
                onChange={(e) => setBlockForm({ ...blockForm, end_date: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Notes</span>
              <textarea
                value={blockForm.notes}
                onChange={(e) => setBlockForm({ ...blockForm, notes: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowBlockForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={blockSaving}>
                {blockSaving ? 'Saving...' : 'Create Block'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
