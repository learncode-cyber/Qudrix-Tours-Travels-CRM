import { useState, FormEvent } from 'react'
import { api } from '../../../lib/api'
import { Card, EmptyState, Button, Input } from '../../../components/ui'
import type { Booking, Hotel } from '../types'

interface Props {
  bookingId: string | undefined
  booking: Booking
  hotels: Hotel[]
  onChanged: () => void
}

export default function HotelSection({ bookingId, booking, hotels, onChanged }: Props) {
  const [hotelForm, setHotelForm] = useState({ hotelId: '', checkIn: '', checkOut: '', rooms: '1', roomType: 'double' })
  const [bookingHotel, setBookingHotel] = useState(false)
  const [hotelError, setHotelError] = useState<string | null>(null)
  const [roomForms, setRoomForms] = useState<Record<number, { roomType: string; maxOccupancy: string }>>({})
  const [assignForms, setAssignForms] = useState<Record<number, number[]>>({})

  async function handleBookHotel(e: FormEvent) {
    e.preventDefault()
    setBookingHotel(true)
    setHotelError(null)
    try {
      await api.post('/hotels/book', {
        hotel_id: hotelForm.hotelId,
        booking_id: bookingId,
        check_in_date: hotelForm.checkIn,
        check_out_date: hotelForm.checkOut,
        number_of_rooms: hotelForm.rooms,
        room_type: hotelForm.roomType,
      })
      setHotelForm({ hotelId: '', checkIn: '', checkOut: '', rooms: '1', roomType: 'double' })
      onChanged()
    } catch (err: any) {
      setHotelError(err.response?.data?.error || 'Could not book hotel.')
    } finally {
      setBookingHotel(false)
    }
  }

  async function handleAddRoom(hotelBookingId: number) {
    const f = roomForms[hotelBookingId] || { roomType: 'double', maxOccupancy: '2' }
    await api.post('/room-assignments', {
      hotel_booking_id: hotelBookingId,
      room_type: f.roomType,
      max_occupancy: f.maxOccupancy,
    })
    onChanged()
  }

  async function handleAssignTravelers(roomAssignmentId: number) {
    const travelerIds = assignForms[roomAssignmentId] || []
    try {
      await api.post(`/room-assignments/${roomAssignmentId}/travelers`, { booking_traveler_ids: travelerIds })
      onChanged()
    } catch (err: any) {
      alert(err.response?.data?.error || 'Could not assign travelers (check room capacity).')
    }
  }

  function toggleTravelerForRoom(roomAssignmentId: number, travelerId: number) {
    setAssignForms((prev) => {
      const current = prev[roomAssignmentId] || []
      const next = current.includes(travelerId)
        ? current.filter((t) => t !== travelerId)
        : [...current, travelerId]
      return { ...prev, [roomAssignmentId]: next }
    })
  }

  return (
      <Card className="p-5 mt-6">
        <h2 className="text-sm font-medium text-ink mb-4">Hotel stays &amp; room assignment</h2>
        {(!booking.hotelBookings || booking.hotelBookings.length === 0) ? (
          <EmptyState title="No hotel booked for this trip yet" />
        ) : (
          <div className="space-y-5 mb-5">
            {booking.hotelBookings.map((hb) => (
              <div key={hb.id} className="border border-line p-3">
                <p className="text-sm font-medium text-ink mb-1">
                  {hb.hotel.name} — {hb.hotel.city} ({hb.room_type}, {hb.number_of_rooms} room(s))
                </p>
                <p className="text-xs text-ink-soft mb-3">
                  {new Date(hb.check_in_date).toLocaleDateString()} to {new Date(hb.check_out_date).toLocaleDateString()}
                </p>

                {hb.roomAssignments.length > 0 && (
                  <ul className="space-y-2 mb-3">
                    {hb.roomAssignments.map((room) => (
                      <li key={room.id} className="text-xs border-t border-line pt-2">
                        <p className="text-ink font-medium mb-1">
                          Room {room.room_number || '(unassigned #)'} — {room.room_type}, max {room.max_occupancy}
                          {room.travelers.length > room.max_occupancy && (
                            <span className="text-danger ml-2">over capacity!</span>
                          )}
                        </p>
                        <div className="flex flex-wrap gap-2 mb-1">
                          {booking.travelers.map((t) => (
                            <label key={t.id} className="flex items-center gap-1 text-ink-soft">
                              <input
                                type="checkbox"
                                checked={(assignForms[room.id] || room.travelers.map((rt) => rt.id)).includes(t.id)}
                                onChange={() => toggleTravelerForRoom(room.id, t.id)}
                              />
                              {t.first_name}
                            </label>
                          ))}
                        </div>
                        <button onClick={() => handleAssignTravelers(room.id)} className="text-teal underline">
                          Save room occupants
                        </button>
                      </li>
                    ))}
                  </ul>
                )}

                <div className="flex items-end gap-2 border-t border-line pt-3">
                  <select
                    className="border border-line px-2 py-1.5 text-xs text-ink focus:border-teal"
                    value={roomForms[hb.id]?.roomType || 'double'}
                    onChange={(e) => setRoomForms((f) => ({ ...f, [hb.id]: { ...(f[hb.id] || { maxOccupancy: '2' }), roomType: e.target.value } }))}
                  >
                    <option value="single">Single</option>
                    <option value="double">Double</option>
                    <option value="triple">Triple</option>
                    <option value="quad">Quad</option>
                  </select>
                  <Input
                    type="number"
                    min={1}
                    className="w-20 text-xs"
                    value={roomForms[hb.id]?.maxOccupancy || '2'}
                    onChange={(e) => setRoomForms((f) => ({ ...f, [hb.id]: { ...(f[hb.id] || { roomType: 'double' }), maxOccupancy: e.target.value } }))}
                  />
                  <Button variant="secondary" onClick={() => handleAddRoom(hb.id)}>
                    Add room
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}

        <form onSubmit={handleBookHotel} className="grid grid-cols-2 gap-3 border-t border-line pt-4">
          <select
            required
            className="col-span-2 border border-line px-3 py-2 text-sm text-ink focus:border-teal"
            value={hotelForm.hotelId}
            onChange={(e) => setHotelForm((f) => ({ ...f, hotelId: e.target.value }))}
          >
            <option value="">Select hotel…</option>
            {hotels.map((h) => (
              <option key={h.id} value={h.id}>
                {h.name} — {h.city}
              </option>
            ))}
          </select>
          <Input type="date" required value={hotelForm.checkIn} onChange={(e) => setHotelForm((f) => ({ ...f, checkIn: e.target.value }))} />
          <Input type="date" required value={hotelForm.checkOut} onChange={(e) => setHotelForm((f) => ({ ...f, checkOut: e.target.value }))} />
          <Input
            type="number"
            min={1}
            placeholder="# rooms"
            required
            value={hotelForm.rooms}
            onChange={(e) => setHotelForm((f) => ({ ...f, rooms: e.target.value }))}
          />
          <select
            className="border border-line px-3 py-2 text-sm text-ink focus:border-teal"
            value={hotelForm.roomType}
            onChange={(e) => setHotelForm((f) => ({ ...f, roomType: e.target.value }))}
          >
            <option value="single">Single</option>
            <option value="double">Double</option>
            <option value="triple">Triple</option>
            <option value="quad">Quad</option>
          </select>
          <Button type="submit" disabled={bookingHotel} className="col-span-2">
            {bookingHotel ? 'Booking…' : 'Book hotel'}
          </Button>
          {hotelError && <p className="col-span-2 text-xs text-danger">{hotelError}</p>}
        </form>
      </Card>
  )
}
