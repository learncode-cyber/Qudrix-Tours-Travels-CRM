export interface Traveler {
  id: number
  first_name: string
  last_name: string
  gender: string
  passport_number: string
}

export interface Booking {
  id: number
  booking_number: string
  status: string
  travel_date: string | null
  number_of_travelers: number
  total_amount: number
  currency: string
  travelers: Traveler[]
  hotelBookings: HotelBookingRecord[]
  flightBookings: FlightBookingRecord[]
  transportBookings: TransportBookingRecord[]
}

export interface RoomAssignmentRecord {
  id: number
  room_number: string | null
  room_type: string
  max_occupancy: number
  travelers: Traveler[]
}

export interface HotelBookingRecord {
  id: number
  hotel: { id: number; name: string; city: string }
  check_in_date: string
  check_out_date: string
  number_of_rooms: number
  room_type: string
  roomAssignments: RoomAssignmentRecord[]
}

export interface Hotel {
  id: number
  name: string
  city: string
}

export interface FlightBookingRecord {
  id: number
  flight: { id: number; airline_code: string; flight_number: string; departure_airport: string; arrival_airport: string; departure_date: string }
  traveler: Traveler
  seat_number: string
  status: string
}

export interface Flight {
  id: number
  airline_code: string
  flight_number: string
  departure_airport: string
  arrival_airport: string
  departure_date: string
  available_seats: number
}

export interface TransportBookingRecord {
  id: number
  transport: { id: number; vehicle_name: string; transport_type: string }
  traveler: Traveler
  status: string
}

export interface TransportOption {
  id: number
  vehicle_name: string
  transport_type: string
  pickup_location: string
  dropoff_location: string
}

export interface MahramRecord {
  id: number
  booking_traveler_id: number
  mahram_name: string | null
  relationship_type: string
  is_verified: boolean
}

export interface Installment {
  id: number
  sequence_number: number
  due_date: string
  amount: string
  status: 'pending' | 'paid' | 'overdue' | 'waived'
}

export interface InstallmentPlan {
  id: number
  total_amount: string
  number_of_installments: number
  status: string
  installments: Installment[]
}
