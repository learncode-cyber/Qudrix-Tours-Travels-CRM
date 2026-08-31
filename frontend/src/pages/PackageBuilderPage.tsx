import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import {
  buildPackage,
  listFlights,
  listHotelRoomTypes,
  listHotels,
  listTransports,
} from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading } from '../components/ui'
import type {
  Flight,
  Hotel,
  HotelRoomType,
  PackageBuilderComponentInput,
  PackageBuilderComponentType,
  PackageBuilderResult,
  Transport,
} from '../types'
import { formatCurrency, getErrorMessage } from '../utils/format'

type ComponentRow = {
  key: number
  type: PackageBuilderComponentType
  hotel_id: string
  room_type_id: string
  roomTypesForHotel: HotelRoomType[]
  roomTypesLoading: boolean
  reference_id: string
  quantity: string
}

let rowKeySeq = 0
function newRow(): ComponentRow {
  return {
    key: ++rowKeySeq,
    type: 'hotel',
    hotel_id: '',
    room_type_id: '',
    roomTypesForHotel: [],
    roomTypesLoading: false,
    reference_id: '',
    quantity: '1',
  }
}

export default function PackageBuilderPage() {
  const [hotels, setHotels] = useState<Hotel[]>([])
  const [flights, setFlights] = useState<Flight[]>([])
  const [transports, setTransports] = useState<Transport[]>([])
  const [inventoryLoading, setInventoryLoading] = useState(true)
  const [inventoryError, setInventoryError] = useState<string | null>(null)

  const [destination, setDestination] = useState('')
  const [travelDate, setTravelDate] = useState('')
  const [groupSize, setGroupSize] = useState('1')
  const [leadId, setLeadId] = useState('')
  const [customerId, setCustomerId] = useState('')
  const [saveAsPackage, setSaveAsPackage] = useState(false)
  const [createQuotation, setCreateQuotation] = useState(false)
  const [rows, setRows] = useState<ComponentRow[]>([newRow()])

  const [building, setBuilding] = useState(false)
  const [buildError, setBuildError] = useState<string | null>(null)
  const [result, setResult] = useState<PackageBuilderResult | null>(null)

  useEffect(() => {
    setInventoryLoading(true)
    Promise.all([listHotels(), listFlights(), listTransports()])
      .then(([hotelsRes, flightsRes, transportsRes]) => {
        setHotels(hotelsRes.data.data ?? [])
        setFlights(flightsRes.data.data ?? [])
        setTransports(transportsRes.data.data ?? [])
      })
      .catch((err) => setInventoryError(getErrorMessage(err, 'Unable to load inventory.')))
      .finally(() => setInventoryLoading(false))
  }, [])

  function updateRow(key: number, patch: Partial<ComponentRow>) {
    setRows((prev) => prev.map((r) => (r.key === key ? { ...r, ...patch } : r)))
  }

  function changeType(key: number, type: PackageBuilderComponentType) {
    updateRow(key, { type, hotel_id: '', room_type_id: '', roomTypesForHotel: [], reference_id: '' })
  }

  function changeHotel(key: number, hotelId: string) {
    updateRow(key, { hotel_id: hotelId, room_type_id: '', reference_id: '', roomTypesForHotel: [], roomTypesLoading: true })
    if (!hotelId) {
      updateRow(key, { roomTypesLoading: false })
      return
    }
    listHotelRoomTypes(hotelId)
      .then((res) => updateRow(key, { roomTypesForHotel: res.data.data ?? [], roomTypesLoading: false }))
      .catch(() => updateRow(key, { roomTypesForHotel: [], roomTypesLoading: false }))
  }

  function addRow() {
    setRows((prev) => [...prev, newRow()])
  }

  function removeRow(key: number) {
    setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.key !== key) : prev))
  }

  async function handleBuild(e: FormEvent) {
    e.preventDefault()
    setBuilding(true)
    setBuildError(null)
    setResult(null)
    try {
      const components: PackageBuilderComponentInput[] = rows.map((r) => ({
        type: r.type,
        reference_id: Number(r.type === 'hotel' ? r.room_type_id : r.reference_id),
        quantity: Number(r.quantity),
      }))
      const res = await buildPackage({
        lead_id: leadId ? Number(leadId) : undefined,
        customer_id: customerId ? Number(customerId) : undefined,
        destination,
        travel_date: travelDate,
        group_size: Number(groupSize),
        components,
        save_as_package: saveAsPackage,
        create_quotation: createQuotation,
      })
      setResult(res.data.data)
    } catch (err) {
      setBuildError(getErrorMessage(err, 'Unable to build this package.'))
    } finally {
      setBuilding(false)
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Package Builder</h1>
      </div>
      <p className="field-hint">
        Every component below is resolved against real inventory and priced with real unit
        prices — nothing here is invented. The final price runs through the active pricing
        rules (see the Pricing Rules page) for a fully auditable markup.
      </p>

      {inventoryLoading ? (
        <Loading label="Loading inventory..." />
      ) : inventoryError ? (
        <ErrorBanner message={inventoryError} />
      ) : (
        <form onSubmit={handleBuild} className="stacked-form">
          {buildError ? <div className="state-block error">{buildError}</div> : null}
          <label className="field">
            <span>Destination</span>
            <input required value={destination} onChange={(e) => setDestination(e.target.value)} />
          </label>
          <label className="field">
            <span>Travel Date</span>
            <input required type="date" value={travelDate} onChange={(e) => setTravelDate(e.target.value)} />
          </label>
          <label className="field">
            <span>Group Size</span>
            <input
              required
              type="number"
              min={1}
              value={groupSize}
              onChange={(e) => setGroupSize(e.target.value)}
            />
          </label>

          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Component</th>
                  <th>Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.key}>
                    <td>
                      <select
                        value={row.type}
                        onChange={(e) => changeType(row.key, e.target.value as PackageBuilderComponentType)}
                      >
                        <option value="hotel">Hotel</option>
                        <option value="flight">Flight</option>
                        <option value="transport">Transport</option>
                      </select>
                    </td>
                    <td>
                      {row.type === 'hotel' ? (
                        <div className="field-inline">
                          <select value={row.hotel_id} onChange={(e) => changeHotel(row.key, e.target.value)}>
                            <option value="">Select a hotel...</option>
                            {hotels.map((h) => (
                              <option key={h.id} value={h.id}>
                                {h.name}
                              </option>
                            ))}
                          </select>
                          <select
                            value={row.room_type_id}
                            disabled={!row.hotel_id || row.roomTypesLoading}
                            onChange={(e) => updateRow(row.key, { room_type_id: e.target.value })}
                          >
                            <option value="">
                              {row.roomTypesLoading ? 'Loading room types...' : 'Select a room type...'}
                            </option>
                            {row.roomTypesForHotel.map((rt) => (
                              <option key={rt.id} value={rt.id}>
                                {rt.name} ({formatCurrency(rt.price_per_night)}/night)
                              </option>
                            ))}
                          </select>
                        </div>
                      ) : row.type === 'flight' ? (
                        <select
                          value={row.reference_id}
                          onChange={(e) => updateRow(row.key, { reference_id: e.target.value })}
                        >
                          <option value="">Select a flight...</option>
                          {flights.map((f) => (
                            <option key={f.id} value={f.id}>
                              {f.airline_code} {f.flight_number} ({f.departure_airport}-{f.arrival_airport}) —{' '}
                              {formatCurrency(f.price_per_seat)}/seat
                            </option>
                          ))}
                        </select>
                      ) : (
                        <select
                          value={row.reference_id}
                          onChange={(e) => updateRow(row.key, { reference_id: e.target.value })}
                        >
                          <option value="">Select a transport...</option>
                          {transports.map((t) => (
                            <option key={t.id} value={t.id}>
                              {t.vehicle_name} ({t.pickup_location} &rarr; {t.dropoff_location}) —{' '}
                              {formatCurrency(t.price_per_seat)}/seat
                            </option>
                          ))}
                        </select>
                      )}
                    </td>
                    <td>
                      <input
                        type="number"
                        min={1}
                        value={row.quantity}
                        onChange={(e) => updateRow(row.key, { quantity: e.target.value })}
                      />
                    </td>
                    <td>
                      <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        disabled={rows.length === 1}
                        onClick={() => removeRow(row.key)}
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <button type="button" className="btn btn-ghost" onClick={addRow}>
            + Add Component
          </button>

          <label className="field field-inline">
            <input type="checkbox" checked={saveAsPackage} onChange={(e) => setSaveAsPackage(e.target.checked)} />
            <span>Save as a reusable package</span>
          </label>
          <label className="field field-inline">
            <input
              type="checkbox"
              checked={createQuotation}
              onChange={(e) => setCreateQuotation(e.target.checked)}
            />
            <span>Create a quotation from this build</span>
          </label>
          {createQuotation ? (
            <label className="field">
              <span>Lead ID</span>
              <input required value={leadId} onChange={(e) => setLeadId(e.target.value)} />
              <p className="field-hint">
                There is no lead picker wired into this frontend yet — enter the lead's ID
                directly (find it on the Leads page). Required by the backend to create a
                quotation.
              </p>
            </label>
          ) : null}
          <label className="field">
            <span>Customer ID</span>
            <input value={customerId} onChange={(e) => setCustomerId(e.target.value)} />
            <p className="field-hint">Optional — links the saved package/quotation to a specific customer.</p>
          </label>

          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={building}>
              {building ? 'Building...' : 'Build Package'}
            </button>
          </div>
        </form>
      )}

      {result ? (
        <div className="table-wrap">
          <h2>Result</h2>
          <table className="data-table">
            <thead>
              <tr>
                <th>Component</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Line Total</th>
              </tr>
            </thead>
            <tbody>
              {result.components.length === 0 ? (
                <tr>
                  <td colSpan={4}>
                    <EmptyState message="No components resolved." />
                  </td>
                </tr>
              ) : (
                result.components.map((c, i) => (
                  <tr key={i}>
                    <td>{c.description}</td>
                    <td>{c.quantity}</td>
                    <td>{formatCurrency(c.unit_price)}</td>
                    <td>{formatCurrency(c.line_total)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>

          <p>
            Base cost {formatCurrency(result.pricing.base_cost)}
            {result.pricing.applied_rules.length > 0 ? (
              <> &rarr; {result.pricing.applied_rules.length} pricing rule(s) applied</>
            ) : null}{' '}
            &rarr; final price <strong>{formatCurrency(result.pricing.final_price)}</strong>
          </p>

          {result.package ? (
            <div className="state-block success">
              Saved as package <strong>{result.package.name}</strong> ({result.package.code}).
            </div>
          ) : null}
          {result.quotation ? (
            <div className="state-block success">
              Created quotation <strong>{String(result.quotation.quotation_number ?? result.quotation.id)}</strong> (
              {result.quotation.status}).
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
