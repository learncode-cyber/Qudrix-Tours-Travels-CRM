import { useState, FormEvent } from 'react'
import { api } from '../../../lib/api'
import { Card, EmptyState, Button, Input } from '../../../components/ui'
import { ShieldCheck } from 'lucide-react'
import type { Booking, MahramRecord } from '../types'

interface Props {
  booking: Booking
  mahrams: MahramRecord[]
  onChanged: () => void
}

export default function TravelersMahramSection({ booking, mahrams, onChanged }: Props) {
  const [mahramForm, setMahramForm] = useState({ travelerId: '', name: '', phone: '', relationship: 'husband' })
  const [savingMahram, setSavingMahram] = useState(false)

  async function handleAddMahram(e: FormEvent) {
    e.preventDefault()
    setSavingMahram(true)
    try {
      await api.post('/mahrams', {
        booking_traveler_id: mahramForm.travelerId,
        mahram_name: mahramForm.name,
        mahram_phone: mahramForm.phone,
        relationship_type: mahramForm.relationship,
      })
      setMahramForm({ travelerId: '', name: '', phone: '', relationship: 'husband' })
      onChanged()
    } finally {
      setSavingMahram(false)
    }
  }

  async function handleVerifyMahram(mahramId: number) {
    await api.post(`/mahrams/${mahramId}/verify`)
    onChanged()
  }

  return (
      <Card className="p-5 mb-6">
        <h2 className="text-sm font-medium text-ink mb-4">Travelers &amp; Mahram</h2>
        {booking.travelers.length === 0 ? (
          <EmptyState title="No travelers added yet" />
        ) : (
          <ul className="space-y-3 mb-5">
            {booking.travelers.map((t) => {
              const mahram = mahrams.find((m) => m.booking_traveler_id === t.id)
              return (
                <li key={t.id} className="flex items-center justify-between text-sm border-b border-line last:border-0 pb-3 last:pb-0">
                  <div>
                    <p className="font-medium text-ink">
                      {t.first_name} {t.last_name} <span className="text-ink-soft capitalize">({t.gender})</span>
                    </p>
                    {mahram ? (
                      <p className="text-ink-soft text-xs mt-0.5 flex items-center gap-1">
                        Mahram: {mahram.mahram_name} ({mahram.relationship_type})
                        {mahram.is_verified ? (
                          <span className="text-success flex items-center gap-0.5"><ShieldCheck size={12} /> verified</span>
                        ) : (
                          <button onClick={() => handleVerifyMahram(mahram.id)} className="text-teal underline">
                            verify
                          </button>
                        )}
                      </p>
                    ) : t.gender === 'female' ? (
                      <p className="text-xs text-warn mt-0.5">No mahram on file</p>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        )}

        <form onSubmit={handleAddMahram} className="grid grid-cols-2 gap-3 border-t border-line pt-4">
          <select
            required
            className="col-span-2 border border-line px-3 py-2 text-sm text-ink focus:border-teal"
            value={mahramForm.travelerId}
            onChange={(e) => setMahramForm((f) => ({ ...f, travelerId: e.target.value }))}
          >
            <option value="">Select traveler…</option>
            {booking.travelers.map((t) => (
              <option key={t.id} value={t.id}>
                {t.first_name} {t.last_name}
              </option>
            ))}
          </select>
          <Input
            placeholder="Mahram name"
            required
            value={mahramForm.name}
            onChange={(e) => setMahramForm((f) => ({ ...f, name: e.target.value }))}
          />
          <Input
            placeholder="Mahram phone"
            value={mahramForm.phone}
            onChange={(e) => setMahramForm((f) => ({ ...f, phone: e.target.value }))}
          />
          <select
            className="border border-line px-3 py-2 text-sm text-ink focus:border-teal"
            value={mahramForm.relationship}
            onChange={(e) => setMahramForm((f) => ({ ...f, relationship: e.target.value }))}
          >
            <option value="husband">Husband</option>
            <option value="father">Father</option>
            <option value="son">Son</option>
            <option value="brother">Brother</option>
            <option value="grandfather">Grandfather</option>
            <option value="uncle">Uncle</option>
            <option value="other">Other</option>
          </select>
          <Button type="submit" disabled={savingMahram}>
            {savingMahram ? 'Saving…' : 'Add mahram'}
          </Button>
        </form>
      </Card>
  )
}
