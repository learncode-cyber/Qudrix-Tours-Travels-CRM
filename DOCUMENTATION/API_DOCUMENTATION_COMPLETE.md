# 🚀 QUDRIX CRM - COMPLETE API DOCUMENTATION

**Version:** 1.0.0  
**Status:** Production Ready ✅  
**Last Updated:** 2026-08-16

---

## 📋 TABLE OF CONTENTS

1. [Authentication](#authentication)
2. [Base URLs](#base-urls)
3. [Rate Limiting](#rate-limiting)
4. [Error Handling](#error-handling)
5. [Public API Endpoints](#public-api-endpoints)
6. [Management API Endpoints](#management-api-endpoints)
7. [Webhooks](#webhooks)
8. [API Key Management](#api-key-management)
9. [TypeScript Integration](#typescript-integration)
10. [Complete Integration Examples](#complete-integration-examples)

---

## 🔐 AUTHENTICATION

### Two Authentication Methods

#### 1. API Key Authentication (For Public API)
```bash
curl -H "X-API-Key: qd_xxxxxxxxxxxxx" \
  https://yourdomain.com/api/v1/packages
```

#### 2. JWT Bearer Token (For Management API)
```bash
curl -H "Authorization: Bearer eyJhbGc..." \
  https://yourdomain.com/api/v1/api-keys
```

### Get API Key from Admin Dashboard
```
Login → Admin Panel → API Keys → Create New Key
```

### Initial Admin Credentials
```
Email: admin@example.com
Password: Password@123
```

---

## 🌐 BASE URLS

```
Development:  http://localhost:8000/api
Staging:      https://staging.yourdomain.com/api
Production:   https://yourdomain.com/api

Current Version: /v1
```

---

## ⚙️ RATE LIMITING

**Default Limit:** 100 requests per minute per API key

**Response Headers:**
```
X-RateLimit-Limit:     100
X-RateLimit-Remaining: 95
X-RateLimit-Reset:     1692374460
```

**When Limit Exceeded:**
```json
{
  "success": false,
  "message": "Rate limit exceeded",
  "code": "RATE_LIMIT_EXCEEDED"
}
```

Status Code: `429 Too Many Requests`

---

## ❌ ERROR HANDLING

### Standard Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE",
  "data": {}
}
```

### Error Status Codes

| Code | Status | Description |
|------|--------|-------------|
| 400 | Bad Request | Invalid request data |
| 401 | Unauthorized | Missing/invalid auth |
| 403 | Forbidden | Permission denied |
| 404 | Not Found | Resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal server error |

### Common Error Codes

```
MISSING_API_KEY         - API key header missing
INVALID_KEY             - API key invalid or revoked
KEY_EXPIRED             - API key expired
IP_NOT_ALLOWED          - IP address not whitelisted
RATE_LIMIT_EXCEEDED     - Too many requests
VALIDATION_ERROR        - Request data invalid
RESOURCE_NOT_FOUND      - Record not found
UNAUTHORIZED            - Not authenticated
```

---

## 📡 PUBLIC API ENDPOINTS

### 1. Health Check

**Check API Status**
```
GET /v1/health
```

**Response:**
```json
{
  "status": "ok"
}
```

---

### 2. Packages API

#### List All Packages
```
GET /v1/packages
```

**Query Parameters:**
```
?page=1&limit=10&search=hajj&sort=created_at
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Hajj 2024 - Premium",
      "type": "hajj",
      "description": "5-star Hajj package",
      "price": 450000,
      "currency": "BDT",
      "duration_days": 20,
      "capacity": 50,
      "bookings_count": 35,
      "is_active": true,
      "created_at": "2026-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_records": 45
  }
}
```

#### Get Package Details
```
GET /v1/packages/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Hajj 2024 - Premium",
    "type": "hajj",
    "description": "5-star Hajj package with full services",
    "itinerary": [
      {
        "day": 1,
        "location": "Medina",
        "activities": ["Umrah", "City tour"]
      }
    ],
    "inclusions": ["Flight", "Hotel", "Visa", "Food", "Guide"],
    "exclusions": ["Travel insurance"],
    "price": 450000,
    "currency": "BDT",
    "duration_days": 20,
    "capacity": 50,
    "image_urls": ["https://..."],
    "is_active": true,
    "created_at": "2026-01-15T10:30:00Z"
  }
}
```

**curl Example:**
```bash
curl -X GET \
  -H "X-API-Key: qd_xxxxxxxxxxxxx" \
  https://yourdomain.com/api/v1/packages/1
```

---

### 3. Customers API

#### Create Customer
```
POST /v1/customers
```

**Request Body:**
```json
{
  "first_name": "Abdullah",
  "last_name": "Raiyan",
  "email": "abdul@example.com",
  "phone": "+8801700000000",
  "country": "Bangladesh",
  "city": "Khulna",
  "address": "123 Main St",
  "passport_number": "AB1234567",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "nid_number": "1234567890123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Customer created successfully",
  "data": {
    "id": 101,
    "customer_id": "CUST-001",
    "first_name": "Abdullah",
    "last_name": "Raiyan",
    "email": "abdul@example.com",
    "phone": "+8801700000000",
    "status": "active",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

**curl Example:**
```bash
curl -X POST \
  -H "X-API-Key: qd_xxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Abdullah",
    "last_name": "Raiyan",
    "email": "abdul@example.com",
    "phone": "+8801700000000"
  }' \
  https://yourdomain.com/api/v1/customers
```

#### Get Customer
```
GET /v1/customers/{email}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "customer_id": "CUST-001",
    "first_name": "Abdullah",
    "last_name": "Raiyan",
    "email": "abdul@example.com",
    "phone": "+8801700000000",
    "total_bookings": 3,
    "total_spent": 1350000,
    "status": "active",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

---

### 4. Bookings API

#### Create Booking
```
POST /v1/bookings
```

**Request Body:**
```json
{
  "customer_id": 101,
  "package_id": 1,
  "travelers_count": 2,
  "travelers": [
    {
      "first_name": "Abdullah",
      "last_name": "Raiyan",
      "passport_number": "AB1234567",
      "date_of_birth": "1990-01-15",
      "gender": "male"
    },
    {
      "first_name": "Fatima",
      "last_name": "Raiyan",
      "passport_number": "AB7654321",
      "date_of_birth": "1992-05-20",
      "gender": "female"
    }
  ],
  "travel_date": "2024-06-01",
  "special_requests": "Window seats preferred",
  "payment_method": "bank_transfer"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 501,
    "booking_reference": "BK-2026-001",
    "customer_id": 101,
    "package_id": 1,
    "travelers_count": 2,
    "total_amount": 900000,
    "currency": "BDT",
    "status": "pending",
    "travel_date": "2024-06-01",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

**curl Example:**
```bash
curl -X POST \
  -H "X-API-Key: qd_xxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 101,
    "package_id": 1,
    "travelers_count": 2,
    "travel_date": "2024-06-01"
  }' \
  https://yourdomain.com/api/v1/bookings
```

#### Get Booking
```
GET /v1/bookings/{reference}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 501,
    "booking_reference": "BK-2026-001",
    "customer": {
      "id": 101,
      "name": "Abdullah Raiyan",
      "email": "abdul@example.com"
    },
    "package": {
      "id": 1,
      "name": "Hajj 2024 - Premium"
    },
    "travelers": [...],
    "total_amount": 900000,
    "status": "confirmed",
    "status_history": [...],
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

---

### 5. Quotations API

#### Create Quotation
```
POST /v1/quotations
```

**Request Body:**
```json
{
  "customer_id": 101,
  "package_id": 1,
  "travelers_count": 2,
  "special_requests": "Group discount needed",
  "valid_until": "2026-09-15"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Quotation created successfully",
  "data": {
    "id": 301,
    "quotation_reference": "QT-2026-001",
    "customer_id": 101,
    "total_amount": 850000,
    "discount": 50000,
    "final_amount": 800000,
    "status": "pending",
    "valid_until": "2026-09-15",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

#### Get Quotation
```
GET /v1/quotations/{reference}
```

---

### 6. Communications API

#### Send Inquiry/Contact
```
POST /v1/communications
```

**Request Body:**
```json
{
  "name": "Abdullah Raiyan",
  "email": "abdul@example.com",
  "phone": "+8801700000000",
  "subject": "Hajj Package Inquiry",
  "message": "I'm interested in the Hajj 2024 Premium package",
  "type": "inquiry",
  "package_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Communication saved successfully",
  "data": {
    "id": 701,
    "reference": "COM-2026-001",
    "name": "Abdullah Raiyan",
    "email": "abdul@example.com",
    "type": "inquiry",
    "status": "new",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

**Alternative: /inquiries endpoint**
```
POST /v1/inquiries
```

Same request/response format as communications.

---

### 7. Payments API

#### Create Payment
```
POST /v1/payments
```

**Request Body:**
```json
{
  "booking_id": 501,
  "amount": 450000,
  "currency": "BDT",
  "payment_method": "bank_transfer",
  "transaction_id": "TXN-12345678",
  "bank_name": "Dutch Bangla Bank",
  "notes": "Down payment for Hajj package"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment recorded successfully",
  "data": {
    "id": 601,
    "payment_reference": "PAY-2026-001",
    "booking_id": 501,
    "amount": 450000,
    "status": "verified",
    "payment_method": "bank_transfer",
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

#### Get Payment
```
GET /v1/payments/{reference}
```

---

## 🔑 API KEY MANAGEMENT

### Create API Key (Admin Only)

**Endpoint:**
```
POST /v1/api-keys
```

**Auth:** JWT Bearer Token

**Request:**
```json
{
  "name": "Website API Key",
  "description": "For public website integration",
  "rate_limit": 1000,
  "expires_at": "2027-08-16",
  "allowed_ips": ["203.0.113.0", "203.0.113.1"],
  "permissions": ["packages.read", "customers.create", "bookings.create"]
}
```

**Response:**
```json
{
  "success": true,
  "message": "API key created successfully",
  "data": {
    "id": 1,
    "name": "Website API Key",
    "key": "qd_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "secret": "sec_xxxxxxxxxxxxx",
    "rate_limit": 1000,
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

### List All API Keys

```
GET /v1/api-keys
```

**Auth:** JWT Bearer Token

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Website API Key",
      "key": "qd_a1b2c3d4...",
      "rate_limit": 1000,
      "is_active": true,
      "used_count": 12543,
      "last_used_at": "2026-08-16T15:22:00Z",
      "created_at": "2026-08-16T10:30:00Z"
    }
  ]
}
```

### Get API Key Details

```
GET /v1/api-keys/{id}
```

### Update API Key

```
PATCH /v1/api-keys/{id}
```

### Revoke API Key

```
POST /v1/api-keys/{id}/revoke
```

### Delete API Key

```
DELETE /v1/api-keys/{id}
```

### Get API Logs

```
GET /v1/api-keys/logs?limit=100
```

### Get API Statistics

```
GET /v1/api-keys/stats?period=7%20days
```

---

## 🪝 WEBHOOKS

### Create Webhook

```
POST /v1/webhooks
```

**Request:**
```json
{
  "url": "https://yourdomain.com/webhook/bookings",
  "event": "booking.created",
  "events": ["booking.created", "booking.confirmed", "payment.received"],
  "headers": {
    "X-Custom-Header": "value"
  },
  "retry_count": 3,
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "url": "https://yourdomain.com/webhook/bookings",
    "event": "booking.created",
    "is_active": true,
    "created_at": "2026-08-16T10:30:00Z"
  }
}
```

### Webhook Events

```
booking.created       - New booking created
booking.confirmed     - Booking confirmed
booking.cancelled     - Booking cancelled
payment.received      - Payment received
quotation.created     - New quotation
customer.created      - New customer
communication.new     - New inquiry/message
```

### Webhook Payload Example

```json
{
  "event": "booking.created",
  "timestamp": "2026-08-16T10:30:00Z",
  "data": {
    "id": 501,
    "booking_reference": "BK-2026-001",
    "customer_id": 101,
    "package_id": 1,
    "total_amount": 900000,
    "status": "pending"
  }
}
```

---

## 🔄 MANAGEMENT API ENDPOINTS

### API Keys Management

```
GET     /v1/api-keys              - List all API keys
POST    /v1/api-keys              - Create new API key
GET     /v1/api-keys/{id}         - Get API key details
PATCH   /v1/api-keys/{id}         - Update API key
POST    /v1/api-keys/{id}/revoke  - Revoke API key
DELETE  /v1/api-keys/{id}         - Delete API key
GET     /v1/api-keys/logs         - Get API logs
GET     /v1/api-keys/stats        - Get API statistics
```

### Webhooks Management

```
GET     /v1/webhooks              - List all webhooks
POST    /v1/webhooks              - Create webhook
GET     /v1/webhooks/{id}         - Get webhook details
PATCH   /v1/webhooks/{id}         - Update webhook
DELETE  /v1/webhooks/{id}         - Delete webhook
GET     /v1/webhooks/{id}/logs    - Get webhook logs
```

---

## 📦 TYPESCRIPT INTEGRATION

### Install Package

```bash
npm install axios
```

### TypeScript Types

```typescript
// Types for API responses
interface Package {
  id: number;
  name: string;
  type: 'hajj' | 'umrah' | 'tour' | 'egypt';
  price: number;
  currency: string;
  duration_days: number;
  capacity: number;
  is_active: boolean;
  created_at: string;
}

interface Customer {
  id: number;
  customer_id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  country: string;
  status: 'active' | 'inactive' | 'blocked';
  created_at: string;
}

interface Booking {
  id: number;
  booking_reference: string;
  customer_id: number;
  package_id: number;
  travelers_count: number;
  total_amount: number;
  currency: string;
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed';
  travel_date: string;
  created_at: string;
}

interface ApiResponse<T> {
  success: boolean;
  message?: string;
  code?: string;
  data?: T | T[];
  pagination?: {
    current_page: number;
    total_pages: number;
    total_records: number;
  };
}
```

### API Client Setup

```typescript
import axios, { AxiosInstance } from 'axios';

class QudrixApiClient {
  private client: AxiosInstance;
  private apiKey: string;

  constructor(baseUrl: string, apiKey: string) {
    this.apiKey = apiKey;
    this.client = axios.create({
      baseURL: baseUrl,
      headers: {
        'X-API-Key': apiKey,
        'Content-Type': 'application/json',
      },
    });

    // Add response interceptor for rate limiting
    this.client.interceptors.response.use(
      (response) => {
        const remaining = response.headers['x-ratelimit-remaining'];
        const limit = response.headers['x-ratelimit-limit'];
        console.log(`API Calls: ${limit - remaining}/${limit}`);
        return response;
      },
      (error) => {
        if (error.response?.status === 429) {
          console.error('Rate limit exceeded. Please retry later.');
        }
        return Promise.reject(error);
      }
    );
  }

  async getPackages(page = 1, limit = 10): Promise<ApiResponse<Package[]>> {
    const response = await this.client.get(`/v1/packages`, {
      params: { page, limit },
    });
    return response.data;
  }

  async getPackage(id: number): Promise<ApiResponse<Package>> {
    const response = await this.client.get(`/v1/packages/${id}`);
    return response.data;
  }

  async createCustomer(data: Partial<Customer>): Promise<ApiResponse<Customer>> {
    const response = await this.client.post(`/v1/customers`, data);
    return response.data;
  }

  async createBooking(data: any): Promise<ApiResponse<Booking>> {
    const response = await this.client.post(`/v1/bookings`, data);
    return response.data;
  }

  async getBooking(reference: string): Promise<ApiResponse<Booking>> {
    const response = await this.client.get(`/v1/bookings/${reference}`);
    return response.data;
  }

  async sendInquiry(data: {
    name: string;
    email: string;
    phone: string;
    message: string;
  }): Promise<ApiResponse<any>> {
    const response = await this.client.post(`/v1/communications`, data);
    return response.data;
  }

  async recordPayment(data: any): Promise<ApiResponse<any>> {
    const response = await this.client.post(`/v1/payments`, data);
    return response.data;
  }
}

export { QudrixApiClient, type Package, type Customer, type Booking };
```

---

## 💻 COMPLETE INTEGRATION EXAMPLES

### Example 1: Next.js Page - Packages List

```typescript
// pages/packages.tsx
import { useEffect, useState } from 'react';
import { QudrixApiClient, Package } from '@/lib/api';

const api = new QudrixApiClient(
  process.env.NEXT_PUBLIC_API_URL,
  process.env.NEXT_PUBLIC_API_KEY
);

export default function PackagesPage() {
  const [packages, setPackages] = useState<Package[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPackages = async () => {
      try {
        const response = await api.getPackages();
        if (response.success) {
          setPackages(response.data as Package[]);
        }
      } catch (error) {
        console.error('Failed to fetch packages:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchPackages();
  }, []);

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <h1>Our Packages</h1>
      <div className="grid grid-cols-3 gap-4">
        {packages.map((pkg) => (
          <div key={pkg.id} className="border rounded p-4">
            <h2>{pkg.name}</h2>
            <p>Price: {pkg.price} {pkg.currency}</p>
            <p>Duration: {pkg.duration_days} days</p>
            <button onClick={() => bookPackage(pkg.id)}>
              Book Now
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
```

### Example 2: React Component - Booking Form

```typescript
// components/BookingForm.tsx
import { useState } from 'react';
import { QudrixApiClient } from '@/lib/api';

const api = new QudrixApiClient(
  process.env.REACT_APP_API_URL,
  process.env.REACT_APP_API_KEY
);

export default function BookingForm({ packageId }) {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    travelers_count: 1,
  });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      // Step 1: Create customer
      const customerResponse = await api.createCustomer({
        first_name: formData.first_name,
        last_name: formData.last_name,
        email: formData.email,
        phone: formData.phone,
      });

      if (!customerResponse.success) {
        throw new Error(customerResponse.message);
      }

      // Step 2: Create booking
      const bookingResponse = await api.createBooking({
        customer_id: customerResponse.data.id,
        package_id: packageId,
        travelers_count: formData.travelers_count,
        travel_date: new Date().toISOString().split('T')[0],
      });

      if (bookingResponse.success) {
        setMessage(
          `Booking successful! Reference: ${bookingResponse.data.booking_reference}`
        );
      } else {
        throw new Error(bookingResponse.message);
      }
    } catch (error) {
      setMessage(`Error: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        placeholder="First Name"
        value={formData.first_name}
        onChange={(e) =>
          setFormData({ ...formData, first_name: e.target.value })
        }
        required
      />
      <input
        type="text"
        placeholder="Last Name"
        value={formData.last_name}
        onChange={(e) =>
          setFormData({ ...formData, last_name: e.target.value })
        }
        required
      />
      <input
        type="email"
        placeholder="Email"
        value={formData.email}
        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
        required
      />
      <input
        type="tel"
        placeholder="Phone"
        value={formData.phone}
        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
        required
      />
      <button type="submit" disabled={loading}>
        {loading ? 'Processing...' : 'Book Now'}
      </button>
      {message && <p>{message}</p>}
    </form>
  );
}
```

### Example 3: Complete Booking Flow

```typescript
// services/bookingService.ts
import { QudrixApiClient } from '@/lib/api';

const api = new QudrixApiClient(
  process.env.API_URL,
  process.env.API_KEY
);

export async function completeBookingFlow(bookingData: any) {
  try {
    // Step 1: Create or get customer
    console.log('Step 1: Creating customer...');
    const customerResponse = await api.createCustomer({
      first_name: bookingData.firstName,
      last_name: bookingData.lastName,
      email: bookingData.email,
      phone: bookingData.phone,
      country: bookingData.country,
    });

    if (!customerResponse.success) {
      throw new Error(`Customer creation failed: ${customerResponse.message}`);
    }

    const customerId = customerResponse.data.id;
    console.log(`✓ Customer created: ${customerId}`);

    // Step 2: Create booking
    console.log('Step 2: Creating booking...');
    const bookingResponse = await api.createBooking({
      customer_id: customerId,
      package_id: bookingData.packageId,
      travelers_count: bookingData.travelersCount,
      travel_date: bookingData.travelDate,
      special_requests: bookingData.specialRequests,
      payment_method: bookingData.paymentMethod,
    });

    if (!bookingResponse.success) {
      throw new Error(`Booking creation failed: ${bookingResponse.message}`);
    }

    const booking = bookingResponse.data;
    console.log(`✓ Booking created: ${booking.booking_reference}`);

    // Step 3: Record payment (if applicable)
    if (bookingData.paymentAmount > 0) {
      console.log('Step 3: Recording payment...');
      const paymentResponse = await api.recordPayment({
        booking_id: booking.id,
        amount: bookingData.paymentAmount,
        currency: 'BDT',
        payment_method: bookingData.paymentMethod,
        transaction_id: bookingData.transactionId,
      });

      if (paymentResponse.success) {
        console.log(`✓ Payment recorded: ${paymentResponse.data.payment_reference}`);
      }
    }

    // Step 4: Send confirmation inquiry
    console.log('Step 4: Sending confirmation...');
    await api.sendInquiry({
      name: bookingData.firstName,
      email: bookingData.email,
      phone: bookingData.phone,
      message: `Booking confirmation for ${booking.booking_reference}`,
    });

    console.log('✓ Confirmation sent');

    return {
      success: true,
      booking: booking,
      message: `Booking completed successfully! Reference: ${booking.booking_reference}`,
    };
  } catch (error) {
    console.error('Booking flow error:', error);
    return {
      success: false,
      error: error.message,
    };
  }
}
```

---

## 📝 NOTES

1. **API Key Security**
   - Keep your API key secret
   - Rotate keys regularly
   - Use environment variables
   - Never commit to version control

2. **CORS Configuration**
   - Ensure frontend domain is whitelisted
   - Contact support to add domains

3. **Testing**
   - Use Postman for API testing
   - Sandbox environment available for testing
   - Create test API keys with limited permissions

4. **Support**
   - Email: support@qudrix.com
   - Documentation: https://api.qudrix.com/docs
   - Status Page: https://status.qudrix.com

---

## ✅ DEPLOYMENT CHECKLIST

- [ ] API keys created and stored securely
- [ ] CORS configured
- [ ] Rate limiting configured
- [ ] Webhooks configured
- [ ] Error handling implemented
- [ ] Rate limit headers monitored
- [ ] API logs reviewed
- [ ] Load testing completed
- [ ] Security audit passed
- [ ] Production deployment ready

---

## MASTER DIRECTIVE PHASE 2 ADDENDUM — Complete CRM (appended, not a rewrite)

All routes below require `Authorization: Bearer <jwt>` and `Accept: application/json`
(the standard `app.jwt` + `tenant` + `audit` protected-route middleware group). Live-verified
over real HTTP against a seeded tenant — see `DOCUMENTATION/PHASE_2_REPORT.md` for the
verification transcript and honest UNVERIFIED items.

### Leads (now full CRUD)
- `GET/PUT/DELETE /api/v1/leads/{id}` — `update`/`destroy` added; previously only
  `index`/`store`/`show` existed.

### Deals (new)
- `GET /api/v1/deals` — paginated list, filters: `stage`, `customer_id`, `owner_id`
- `POST /api/v1/deals` — `{title, customer_id?, lead_id?, owner_id?, amount, currency, probability?, expected_close_date?, notes?}`
- `GET /api/v1/deals/{id}` — includes `stage_history`
- `PUT /api/v1/deals/{id}` — general fields; rejects a `stage` key (422) to force using the dedicated endpoint below so stage history stays accurate
- `DELETE /api/v1/deals/{id}`
- `PUT /api/v1/deals/{id}/stage` — `{stage: new|qualified|proposal|negotiation|won|lost}`, records a `DealStageTransition` row and closes the previous one
- `GET /api/v1/deals/pipeline` — Kanban-style: deals grouped by stage with per-stage count/value

### Customer 360
- `GET /api/v1/customers/{id}/360` — `{customer, leads, deals, bookings, quotations, communications, notes, tags, timeline}`

### CRM Dashboard
- `GET /api/v1/crm/dashboard` — `{total_leads, new_leads_this_month, conversion_rate, pipeline_value_by_stage, deals_won, deals_lost, tasks_due_today, upcoming_follow_ups}`
- `GET /api/v1/crm/conversion-funnel` — `{stages: [{status, count}], total_leads, won, conversion_rate}`
- `GET /api/v1/crm/follow-ups/calendar?from=&to=` — merges reminders + lead follow-up dates + task due dates into one dated event feed (defaults to today..+30 days)

### Sales Activity
- `GET /api/v1/pipeline/sales-activities` — paginated read of the `SalesActivity` log (filters: `lead_id`, `activity_type`); the write path (`POST /api/v1/pipeline/activity`) already existed.

**Phase 2 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 3 ADDENDUM — Sales + Quotation (appended, not a rewrite)

Same auth requirements as the Phase 2 addendum above (JWT bearer, `Accept: application/json`).

### Lead → Customer → Booking/Invoice integration (no duplicate records)
- Winning a lead (`PUT /api/v1/leads/{id}/status {status: won}` or
  `PUT /api/v1/pipeline/stage {lead_id, new_stage: won}`) reuses an
  existing customer in the tenant matched by email, then phone, before
  creating a new one; also backfills `customer_id` onto any of that
  lead's existing quotations.
- `POST /api/v1/quotations` now auto-populates `customer_id` from the
  lead if the lead already has one and the caller didn't pass one.
- `POST /api/v1/quotations/{id}/convert-to-booking` — only from an
  `accepted` quotation. Body: `{package_id? (required if no item on the
  quotation has one), booking_type, travel_date, return_date,
  number_of_travelers, visa_required?, special_requests?}`. Reuses the
  quotation's (or its lead's) linked customer — 422 if none exists yet.
- `POST /api/v1/quotations/{id}/generate-invoice` — Body:
  `{due_date? (Net-14 default), booking_id?}`. Pre-populates every
  invoice item from the quotation's items. 422 if no customer is linked
  yet.

### Invoices
- `GET /api/v1/invoices/{id}/pdf` — binary PDF download, same pattern
  as the existing quotation PDF endpoint.
- `POST /api/v1/invoices/{id}/record-payment` — alias of the existing
  `POST /api/v1/invoices/{id}/payments` (`{amount}`).

### Sales Dashboard
- `GET /api/v1/sales/dashboard` — `{revenue_this_month,
  quotation_conversion_rate, invoice_collection_rate,
  outstanding_amount, top_packages: [{package_id, name, count, revenue}]}`

### Customer Quotation History
- `GET /api/v1/customers/{id}/quotations` — `{data: [...quotations]}`

**Phase 3 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 4 ADDENDUM — Travel Operations (appended, not a rewrite)

Same auth requirements as the Phase 2/3 addenda above.

### Bookings (calendar)
- `GET /api/v1/bookings/calendar?from=&to=` — bookings whose `[travel_date, return_date]` overlaps the given range (defaults to the current month). Returns the same shape as a booking list row, with `customer`/`package` eager-loaded.

### Embassies (new)
- `GET/POST /api/v1/embassies`, `GET/PUT/DELETE /api/v1/embassies/{id}` — `{name, country, city?, address?, contact_email?, contact_phone?, website?, average_processing_days?, notes?}`
- `VisaApplication` gained a nullable `embassy_id` FK to this table (the old free-text `embassy` string column is unchanged, for backward compatibility with existing data).

### Room Blocks (new)
- `GET/POST /api/v1/room-blocks`, `GET/DELETE /api/v1/room-blocks/{id}` — group inventory holds, distinct from `POST /api/v1/hotels/book`'s per-guest flow. `{hotel_id, hotel_room_type_id, group_booking_id?, name?, blocked_rooms, start_date, end_date, notes?}`
- `POST /api/v1/room-blocks/{id}/release` — `{rooms}`, releases N rooms back; `status` becomes `partially_released` or `released`. **Does not** mutate `HotelRoomType.available_rooms` — this is a separate allotment ledger staff manage explicitly, not wired into the existing booking-inventory decrement logic.

### Visa/Passport Expiry Reminders (new)
- `POST /api/v1/visas/check-expiry-reminders` — `{days?: 90}`, on-demand trigger of the same sweep the daily schedule (`routes/console.php`, `php artisan reminders:check-expiry`) runs. Scans `visa_applications.expiry_date` (status=approved) and `booking_travelers.passport_expiry`, creates a `Reminder` for anything expiring within the window. Idempotent — never creates a second pending reminder for the same record.

### Documents on Flight/Hotel Bookings
- `POST /api/v1/documents` now accepts `documentable_type: flight_booking` and `documentable_type: hotel_booking` in addition to the types already supported (lead, customer, booking, visa_application, support_ticket, student_visa_application, pilgrim, agent).

### Packages (new — basic CRUD)
- `GET/POST /api/v1/packages`, `GET/PUT/DELETE /api/v1/packages/{id}` — `{name, code?, type?, description?, days?, nights?, destination?, base_price?, inclusions?, exclusions?, is_active?, status?}`. Had no endpoint at all before this phase despite `Booking.package_id` and `QuotationItem.package_id` depending on it — discovered while live-testing the booking creation UI, whose package picker had nothing to populate it with. Distinct from `PackageBuilderController`/`AiPackageBuilderController` (which construct a package from a quotation or an AI-assisted flow) — this is the plain staff CRUD path.

### Fixed: 8 previously-broken CRUD routes across this app
A systematic scan (every `Route::apiResource(...)` registration checked against its controller's actual public methods) found 8 registered routes pointing at controller methods that didn't exist, or existed under the wrong name — every one of them 500'd with "method does not exist" the moment anything actually called them, invisible to `php -l` and never caught before live end-to-end testing:
- `DELETE /api/v1/customers/{id}`, `DELETE /api/v1/tasks/{id}` — method was named `delete()` instead of `destroy()`
- `DELETE /api/v1/bookings/{id}` — didn't exist at all
- `PUT/DELETE /api/v1/hotels/{id}` — neither existed
- `GET/PUT/DELETE /api/v1/transports/{id}` — none existed
- `DELETE /api/v1/flights/{id}`, `DELETE /api/v1/destinations/{id}` — didn't exist
- `PUT/DELETE /api/v1/suppliers/{id}` — neither existed
- `PUT/DELETE /api/v1/visas/{id}` — neither existed (also: `embassy_id` was missing from `store()`'s validation whitelist, so it was silently dropped on create even though the column and model support it — fixed alongside)

**Phase 4 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 5 ADDENDUM — Hajj & Umrah + Student Visa (appended, not a rewrite)

Same auth requirements as the Phase 2/3/4 addenda above. The backend for this
phase was already built in a prior session (see `PROJECT_STATUS.md`'s
"backend complete" definition) — this phase's own work was live-testing it
for the first time end-to-end, fixing the one real bug it exposed, writing
its automated test coverage, and building its frontend.

### Hajj Packages
- `GET/POST/PUT /api/v1/hajj`, `GET /api/v1/hajj/{id}` — `{name, description?, duration_days, price, currency?, max_capacity, rituals_included?: string[], accommodations?: object, status?: active|inactive|discontinued}`. No `destroy` route exists.

### Umrah Packages
- `GET/POST /api/v1/umrah`, `GET /api/v1/umrah/{id}` — same shape as Hajj minus `accommodations` on create. **No update or destroy route exists** — list/create/show only.

### Hajj/Umrah Groups (departure management)
- `GET/POST/PUT /api/v1/hajj-umrah-groups`, `GET /api/v1/hajj-umrah-groups/{id}` — `{package_type: hajj|umrah, package_id, name, departure_date, return_date, group_leader_id?, agent_id?, capacity, status?}`. `show` returns the group merged with `seats_available` (capacity minus registered pilgrims) and the resolved `package` object. No destroy route exists.
- `GET /api/v1/hajj-umrah-groups/{id}/report` — `{group, total_pilgrims, seats_available, by_status, total_amount_due, total_amount_paid, total_balance, unassigned_rooms}`.

### Pilgrims
- `GET/POST/PUT /api/v1/pilgrims`, `GET /api/v1/pilgrims/{id}` — `{hajj_umrah_group_id, booking_id?, customer_id?, name, passport_number?, passport_expiry?, gender?, date_of_birth?, mahram_name?, amount_due?}`. `index` accepts `hajj_umrah_group_id`/`status` filters. `store` returns `400 {"error": "Group is at full capacity"}` when the target group has no seats left. No destroy route exists.
- `PUT /api/v1/pilgrims/{id}/room` — `{room_number, hotel_id?}`
- `PUT /api/v1/pilgrims/{id}/transport` — `{transport_assignment}`
- `POST /api/v1/pilgrims/{id}/payments` — `{amount}`, increments `amount_paid` and flips `payment_status` to `paid`/`partial` based on the resulting balance.

### Student Visa Applications
- `GET/POST/PUT /api/v1/student-visa-applications`, `GET /api/v1/student-visa-applications/{id}` — `{lead_id?, customer_id?, student_name, date_of_birth?, destination_country (2-letter), university?, course?, intake?, assigned_counsellor_id?, service_fee?, service_fee_currency?}`. `index` accepts `application_status`/`assigned_counsellor_id`/`destination_country` filters. No destroy route exists.
- `PUT /api/v1/student-visa-applications/{id}/status` — `{application_status}` (one of `inquiry, documents_pending, applied, offer_received, visa_appointment_scheduled, visa_submitted, visa_approved, visa_rejected, enrolled`)
- `POST /api/v1/student-visa-applications/{id}/offer-letter` — `{offer_letter_date}`, sets `offer_letter_received=true` and `application_status=offer_received`.
- `POST /api/v1/student-visa-applications/{id}/embassy-appointment` — `{embassy_appointment_date}`, sets `application_status=visa_appointment_scheduled`.
- `PUT /api/v1/student-visa-applications/{id}/visa-status` — `{visa_status: not_applied|submitted|approved|rejected}`, also advances `application_status` to match (`submitted`→`visa_submitted`, etc.)
- `POST /api/v1/student-visa-applications/{id}/assign-counsellor` — `{assigned_counsellor_id}`

### Fixed: Hajj package status validation didn't match its database schema
`HajjController::update()`'s validation allowed `status: sold_out`, but the
`hajj_packages.status` column is a DB-level enum of `active, inactive,
discontinued` (defined back when the phase 0/1 migrations were written) —
`sold_out` isn't one of them. Any attempt to set that status crashed with a
SQL check-constraint violation instead of a clean validation error. Caught
by this phase's own feature test, not by static checking. Fixed by aligning
the validation to the real enum (`active, inactive, discontinued`) rather
than altering the shipped database schema.

### Known inconsistency (not a bug, flagged for awareness)
Unlike some Phase 4 list endpoints (`packages`, `bookings`), the Phase 5 list
endpoints (`hajj`, `umrah`, `hajj-umrah-groups`, `pilgrims`,
`student-visa-applications`) return `{"data": [...]}` with no `pagination`
key — they use `paginate()` server-side but only ever return `.items()`.
The frontend for this phase does not build pagination controls against
these lists as a result.

**Phase 5 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 6 ADDENDUM — Custom Package Builder + Pricing Engine (appended, not a rewrite)

Same auth requirements as the Phase 2–5 addenda above. Like Phase 5, this
module's backend already existed from a prior session; this phase's work
was live-testing it end to end for the first time, writing its first
automated test coverage, and building its frontend. Unlike Phase 4/5, no
bugs were found in this module's backend — every endpoint behaved exactly
as its code describes on first live execution.

### Pricing Rules
- `GET/POST/PUT/DELETE /api/v1/pricing-rules` — `{name, factor: season|demand|group_size|customer_segment|booking_timing, season_start?, season_end?, min_group_size?, max_group_size?, booking_days_before_travel_min?, booking_days_before_travel_max?, customer_segment_id?, adjustment_type: percentage|fixed, adjustment_value, priority?}`. `update` only accepts `{name?, adjustment_type?, adjustment_value?, priority?, is_active?}` — the condition fields (season/group-size/booking-timing bounds) are set at creation and not editable afterward through this endpoint.
- `POST /api/v1/pricing-rules/preview` — `{base_cost, travel_date?, group_size?, booking_days_before_travel?, customer_segment_id?}` → `{base_cost, applied_rules: [{rule_id, name, factor, adjustment_type, adjustment_value, amount, price_after}], final_price, calculation_log_id}`. Every preview call is logged to `pricing_calculation_logs` (auditable, no side-effect-free "dry run" exists by design). Rules apply in ascending `priority` order (ties broken by rule id), each adjustment compounding on the running price, not the original base cost — verified: `1000 → +10% → 1100 → +50 fixed → 1150`.

### Package Builder
- `POST /api/v1/package-builder/build` — `{lead_id?, customer_id?, destination, travel_date, group_size, components: [{type: hotel|flight|transport, reference_id, quantity}], save_as_package?, create_quotation?}`. Every component's `reference_id` is resolved against real, tenant-owned inventory (`HotelRoomType`/`Flight`/`Transport`) by `InventoryResolver` — a reference to another tenant's inventory or to a component with insufficient capacity/seats returns `422` with a `components` validation error, never a fabricated price. The resolved base cost is then run through the same `PricingEngine` used by the preview endpoint.
  - `save_as_package: true` persists the build as a real `Package` row (`type: custom`, `is_custom_built: true`, the raw `components` array preserved for reference).
  - `create_quotation: true` requires `lead_id` (422 without it) and creates a real `Quotation` + `QuotationItem` rows — one item per resolved component plus, when the pricing engine's markup is non-zero, one additional "Pricing adjustment" line referencing the `pricing_calculation_logs` row it came from.

**Phase 6 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 7 ADDENDUM — Telegram + Notification System (appended, not a rewrite)

Same auth requirements as the Phase 2–6 addenda above. As with Phase 5/6,
this module's backend (`NotificationController`, `NotificationService`,
`TelegramNotificationService`, `ConversationController`) already existed
from a prior session; this phase live-tested it end to end for the first
time, found and fixed one real bug, wrote its first automated test
coverage, and built its frontend.

### In-app Notifications
- `GET /api/v1/notifications` — `?unread_only=1` filters to unread. User- and tenant-scoped (a user only ever sees their own).
- `PUT /api/v1/notifications/{id}/read`, `PUT /api/v1/notifications/read-all`
- `GET /api/v1/notifications/unread-count`
- `NotificationService::send()` (internal, called by other controllers e.g. `LeadController`, `BookingController` on assignment) always writes a real `Notification` row, then attempts each requested delivery channel (`telegram`, `email`) honestly — never marks a channel `sent` unless the transport actually accepted it. A user with no `telegram_chat_id` set gets `{"sent": false, "reason": "User has no telegram_chat_id configured"}`, not a silent no-op.

### Telegram
- `TelegramNotificationService` is a real client for Telegram's public Bot API (`https://api.telegram.org/bot{token}/sendMessage`), gated entirely on `config('services.telegram.bot_token')` (server-side only, never exposed to the frontend). With no token configured — the case in this sandbox, no outbound network — every send attempt returns `{"sent": false, "reason": "CONTRACT REQUIRED: TELEGRAM_BOT_TOKEN is not configured"}` rather than fabricating success. **UNVERIFIED**: real message delivery against a live bot token, since this sandbox has no outbound network — see Known Limitations.
- `PUT /api/v1/profile` accepts `telegram_chat_id` (nullable string) — this is how a user's Telegram delivery target gets configured; there is no separate Telegram-specific endpoint.

### Conversations (unified inbox)
- `GET/POST /api/v1/conversations`, `GET /api/v1/conversations/{id}` — channels: `website_chat, email, whatsapp, telegram, sms, internal`. `store` requires a `customer_id` or `lead_id` (422 without either) and now also accepts `external_thread_id` (see Fixed below) — for `telegram` this is the chat id replies get sent to.
- `POST /api/v1/conversations/{id}/inbound` — records an inbound message (from a webhook or manual entry), bumps `unread_count`, reopens a `closed` conversation.
- `POST /api/v1/conversations/{id}/reply` — `{body, is_internal_note?}`. An internal note is never sent anywhere (`delivery_status: null`). A real reply attempts delivery per the conversation's `channel`: `email` through Laravel's configured mailer, `telegram` through `TelegramNotificationService`, `internal` is always `not_attempted`, any other channel (`whatsapp`/`sms`/`website_chat`) requires an active `ApiConnector` for that category with a mapped `send` endpoint — absent one, the message is honestly recorded `not_attempted` with a `CONTRACT REQUIRED` reason, never fabricated as sent.
- `PUT /api/v1/conversations/{id}/assign`, `PUT /api/v1/conversations/{id}/status`
- `GET /api/v1/conversations/{id}` clears the thread's `unread_count` and marks its inbound messages read as a side effect of opening it.

### Fixed: `POST /conversations` silently dropped `external_thread_id`
The validation whitelist in `ConversationController::store()` didn't
include `external_thread_id`, even though the column has existed on
`conversations` since its migration and `attemptDelivery()` depends on it
entirely for `telegram` (and, implicitly, any connector-based channel that
uses it as the send target). The practical effect: there was no way,
through the API, to create a Telegram (or WhatsApp/SMS) conversation with
a real target chat id — every reply on such a conversation was
permanently `not_attempted` with "No Telegram chat id stored on this
conversation," regardless of intent. Caught live-testing the exact
workflow a real support agent would use (create a conversation for an
existing Telegram contact, then reply). Fixed by adding
`external_thread_id => nullable|string|max:255` to the validation.

**Phase 7 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 8 ADDENDUM — CRM External API Integration (appended, not a rewrite)

Same auth requirements as the Phase 2–7 addenda above. This phase is
architecture-only per the master directive's own rule: no external
provider contract was ever supplied, so nothing here is wired to a real
third party — it is a generic, operator-configurable connector engine
that only ever calls whatever contract an admin supplies, and refuses to
pretend otherwise. Backend already existed from a prior session; this
phase live-tested it end to end (against a local mock, never a real
third party — see Verification below), wrote its first automated
coverage, and built its frontend.

### API Connectors (Integration Manager)
- `GET/POST/PUT/DELETE /api/v1/api-connectors` — `{name, category: flight|hotel|visa|payment|sms|whatsapp|email|ai|analytics|crm|other, provider_name?, base_url, auth_type: none|bearer|api_key_header|api_key_query|basic|custom_headers, auth_key_name?, credentials?, default_headers?, timeout_seconds?}`. Every response includes `contract_required: bool` — true whenever the connector has no active endpoint mapped.
- `PUT /api-connectors/{id}` refuses `is_active: true` with a `422 CONTRACT REQUIRED` error unless at least one active endpoint is mapped — a connector can never be switched on and left to fail silently at call time.
- `PUT /api-connectors/{id}/credentials` — the only way to write credentials; `credentials` is `encrypted:array`-cast and `$hidden` on the model, so it is never present in any read response (`index`, `show`, or the update response), including this endpoint's own response.
- `POST /api-connectors/{id}/endpoints` (upsert by `operation`) — `{operation, http_method, path, request_template?, query_template?, response_mapping?, response_collection_path?, is_active?}`. Templates use `{{param}}` / `{{credential.KEY}}` placeholders, substituted server-side only at call time.
- `DELETE /api-connectors/{id}/endpoints/{endpointId}`
- `POST /api-connectors/{id}/test-connection` — calls the connector's own `status` operation if mapped, otherwise a bare `GET` on `base_url`. Always records the real outcome (`connected`/`failed` + `last_test_error`) on the connector — never fabricates success.
- `POST /api-connectors/{id}/execute` — `{operation, params?}`. Runs the mapped endpoint against the real `base_url`, substitutes real credentials into the outgoing request only (a separately-rendered, credential-free copy is what gets logged), applies the operator's `response_mapping`, and returns `{raw, mapped, duration_ms, status}`. An unmapped operation returns `502 CONTRACT REQUIRED: connector '...' has no active '...' endpoint mapped`; an inactive connector returns `502 ... is not active`; a non-2xx provider response returns `502 Provider returned HTTP <status>` — every one of these is also written to `api_connector_call_logs` with the real outcome, never silently dropped.
- `GET /api-connectors/{id}/call-logs` — full audit trail of every execute attempt (URL, method, redacted request payload, response status/body truncated to `CONNECTOR_MAX_LOGGED_RESPONSE_BYTES`, duration, success/failure, error).
- **SSRF guard**: unless `ALLOW_PRIVATE_NETWORK_CONNECTORS=true` is explicitly set, any connector URL that resolves to a private/reserved/loopback address is refused before any HTTP call is made — a tenant admin cannot point a connector at `127.0.0.1` or a cloud metadata endpoint to use the CRM as an internal-network proxy.

### Fixed
Nothing — no bugs were found in this module either, matching Phase 6.

### Verification note
Since no real external provider contract exists to integrate against (per
the directive's own rule for this phase), live verification exercised the
connector *engine* itself — CRUD, the activation guard, credential
hiding, the SSRF guard, and (with `ALLOW_PRIVATE_NETWORK_CONNECTORS=true`
set only for this local verification, never in production) a full
execute round-trip against a throwaway local mock HTTP server standing in
for "some real provider," confirming placeholder substitution, response
mapping, and call logging all work correctly. This is testing the CRM's
own code, not fabricating a third-party integration — no data from any
real external system is claimed to have been exchanged.

**Phase 8 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 9 ADDENDUM — AI Provider Management (appended, not a rewrite)

Same auth requirements as the Phase 2–8 addenda above. Backend
(`AiProviderController`, `AiGateway`, provider adapters for Anthropic/
OpenAI/Gemini) already existed from a prior session; this phase live-
tested it end to end for the first time, wrote its first automated
coverage, and built its frontend. Zero bugs found — a third phase this
happened, after Phase 6 and Phase 8.

### AI Providers
- `GET/POST/PUT/DELETE /api/v1/ai-providers` — `{provider: openai|anthropic|gemini, model, base_url?, credentials?, is_default?, priority?, monthly_cost_limit_usd?, input_cost_per_million?, output_cost_per_million?, max_output_tokens?}`. `index`/`show` add `credentials_configured`/`cost_rates_configured` boolean flags — the actual `credentials` value is never present in any response (`encrypted:array` cast + `$hidden` on the model).
- `PUT /ai-providers/{id}` refuses `is_active: true` with a `422` unless credentials are already configured — never lets a provider be switched on and fail on first real use.
- Setting `is_default: true` on create or update automatically clears the flag on every other provider for that tenant (at most one default at a time).
- `PUT /ai-providers/{id}/credentials` — the only way to write an API key; requires `credentials.api_key`.
- `POST /ai-providers/{id}/test` — issues a real minimal completion ("Reply with the single word: OK") against the provider and records the actual outcome (`last_test_at`, `last_test_error`) on the provider — never a fabricated "connected". Returns `502` on failure with the real provider error message.
- `GET /ai-usage?since=` — aggregates real `AiUsageLog` rows by provider/feature/status (calls, tokens, cost, avg latency) since the given date (default: start of current month). `providers_without_cost_rates` lists provider ids whose cost figures are unknown (no configured per-token rates) rather than silently reported as zero.

### The Gateway (`AiGateway`, used internally by every AI feature)
- Application code never names a vendor — it calls `AiGateway::complete($tenantId, $feature, $messages, ...)` and the gateway resolves an eligible provider (active, not over its spend limit), tries it, and **fails over to the next eligible provider on any error**, logging both the failure and the eventual success (or, if every provider fails, throwing with every failure reason concatenated).
- Cost is computed from real logged token counts against the operator's configured per-million rates — a provider with no rates configured logs `cost_usd: 0` and is flagged in `/ai-usage`, never estimated.
- A tenant-level `monthly_cost_limit_usd` on a provider, and a global `AI_GLOBAL_MONTHLY_COST_CEILING_USD` ceiling (env, default $500), both computed from real summed usage this calendar month — a provider over its limit is skipped in failover order, not silently allowed through.

### Fixed
Nothing — no bugs were found in this module.

### Verification note
`api.anthropic.com` is reachable from this sandbox (allowlisted), so this
phase's live curl testing against a real Anthropic provider with a
deliberately invalid key produced a genuine `401` from Anthropic's own
API, exercised end-to-end through the real adapter and honestly recorded
on the provider — this is real verified connectivity, not a mock. `api.openai.com`
is **not** reachable from this sandbox (connection blocked at the network
boundary) — that failure was also honestly reported by the adapter with
no crash. Neither a successful real completion (no valid API key was
used) nor Gemini connectivity were exercised live; the gateway's
failover/cost-limit/logging logic itself was verified via `Http::fake`
in the automated test suite instead, which requires no real network and
is deterministic.

**Phase 9 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 10 ADDENDUM — AI Sales Agent + AI Package Builder (appended, not a rewrite)

Same auth requirements as the Phase 2–9 addenda above. Backend
(`AiSalesAgentController`/`AiSalesAgentService`,
`AiPackageBuilderController`/`AiPackageBuilderService`) already existed
from a prior session and is built entirely on the Phase 9 gateway and the
Phase 6 pricing/inventory engine — no new AI infrastructure, only new
prompts and grounding contracts. Live-tested end to end for the first
time (including a real round trip to Anthropic's live API), wrote its
first automated coverage, and built its frontend. Zero bugs found.

Every response from this module is a suggestion or draft for a human to
act on — nothing is sent, booked, priced by the model, or applied
automatically. This is enforced structurally (grounding + verification),
not just by prompt wording.

### AI Sales Agent (per-lead)
- `POST /api/v1/ai/leads/{leadId}/qualify` — the model sees only real rows (the lead itself, its real `Communication`/`Quotation`/`Booking` history) and returns a score/buying-intent/reasoning/next-action JSON object. The score is **persisted as an `ai_suggested` `LeadScore` row** a human can see and override — it never overwrites or is treated as a human score.
- `POST /api/v1/ai/leads/{leadId}/summarize` — summarizes real communications. **Short-circuits before ever calling the AI gateway** if the lead has no recorded communications (`{"summary": null, "message": "This lead has no recorded communications to summarize."}`) — verified live to make zero HTTP calls in that case.
- `POST /api/v1/ai/leads/{leadId}/suggest-reply` — `{rep_intent?}` → a reply draft for a rep to review and send themselves; response includes `is_draft: true, sent: false`. The prompt forbids stating any price, availability, or booking confirmation — those must appear as an explicit `[CONFIRM ...]` placeholder instead.
- All three return `502` with the real provider/gateway error message on AI failure — never a fabricated result.

### AI Package Builder (AI-assisted version of the Phase 6 builder)
- `POST /api/v1/ai/package-builder/interpret` — `{text}` → structured requirements (`destination`, `travel_date`, `group_size`, `needs`, `missing_information`, ...) extracted from free text. No inventory or pricing claim at this step.
- `POST /api/v1/ai/package-builder/propose` — `{requirements: {destination?, travel_date?, group_size?}}`. Shows the model **only real, currently-available inventory** matching the requirements (flights with enough seats, hotel room types with rooms available, transport with enough capacity) and lets it choose among what it was shown. **Every component the model names is then re-resolved against real inventory by the same `InventoryResolver` from Phase 6** — a hallucinated `reference_id` (one that doesn't exist, or lacks capacity) causes the whole request to fail with `422 {"error": "The proposed package failed inventory verification.", "details": {...}}` rather than being silently accepted. Real components are priced by the same deterministic `PricingEngine` from Phase 6 — the model never sets a price. If no inventory matches the requirements at all, the endpoint returns `200` with `proposal: null` and a plain message, **without ever calling the AI gateway** (verified live to make zero HTTP calls in that case). Every successful response includes `requires_human_approval: true`.

### Fixed
Nothing — no bugs were found in this module.

### Verification note
As with Phase 9, `POST /ai/leads/{leadId}/qualify` and
`POST /ai/package-builder/propose` were both exercised **live against
Anthropic's real API** (reachable from this sandbox) with a deliberately
invalid key — both correctly reached the real provider, received a
genuine `401`, and propagated it honestly through the full stack
(adapter → gateway → service → controller → JSON response). The
success path (grounding, score persistence, hallucination rejection,
short-circuit-without-a-network-call behavior) was verified
deterministically via `Http::fake` in the automated suite, since no
valid API key was available to exercise a real successful completion.

**Phase 10 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 11 ADDENDUM — Sales Strategies + Customer Memory + AI Copilot (appended, not a rewrite)

Same auth requirements as the Phase 2–10 addenda above. Backend
(`SalesStrategyController`, `CustomerMemoryController`,
`AiCopilotController`/`AiCopilotService`) already existed from a prior
session, built on the same Phase 9 gateway. Live-tested end to end for
the first time (including real round trips to Anthropic's live API),
found and fixed one real bug, wrote its first automated coverage, and
built its frontend.

### Sales Strategies
- `GET/POST/PUT/DELETE /api/v1/sales-strategies` — `{key: consultative|spin|solution|value|relationship|challenger|sandler, name, description?, prompt_guidance, tone?, priority?, customer_segment_id?}`. `index` also returns `available_keys` (the full `key` enum) for building a picker. `prompt_guidance` is fed directly to the AI Copilot as its methodology instructions — it is genuinely admin-editable, not a fixed template per methodology.

### Customer Memory
- `GET/POST/PUT/DELETE /api/v1/customer-memories` — `{customer_id?, lead_id?, category, key, value, source?: human|ai_extracted, confidence?, is_sensitive?}`. `index` requires `customer_id` or `lead_id` (422 without either) and returns `categories` (the full category enum). Every write passes through the standard `audit` middleware, so who created/changed a memory entry is recorded (Directive S9).
- Entries marked `is_sensitive: true` are withheld from every AI prompt by default (`CustomerMemory::scopeSafeForAi()`) — verified this phase by inspecting the actual outgoing HTTP request body in a test and confirming a sensitive value never appears in it while a non-sensitive one does.

### AI Copilot
- `POST /api/v1/ai/leads/{leadId}/copilot` — `{latest_customer_message?}`. Builds its prompt from the lead's highest-priority active `SalesStrategy` (preferring one bound to the lead's customer segment, verified live), the lead's non-sensitive memory, and its recent real communications. Returns `strategy_used`, `is_suggestion: true`, `human_in_control: true` alongside the suggestion fields. The prompt structurally forbids stating prices/availability/bookings, same rule as the Phase 10 Sales Agent.
- `POST /api/v1/ai/leads/{leadId}/extract-memory` — returns `candidates` (category/key/value/confidence/evidence/`possibly_sensitive`) for a human to review, `requires_human_confirmation: true, stored: false` — **nothing is written to `customer_memories` automatically**; a human confirms each candidate via the normal `POST /customer-memories` endpoint. **Short-circuits with zero AI-gateway calls** when the lead has no communications to extract from — verified live to make zero HTTP calls in that case.

### Fixed: `PUT /leads/{id}` silently dropped `customer_id`
`LeadController::update()`'s validation whitelist didn't include
`customer_id`, even though `Lead::$fillable` has included it since
Phase 2 specifically so "a customer's originating leads can be found
without guessing." Practical effect: there was no way through the API
to link an existing lead to a customer after creation — a request with
`customer_id` in the body silently no-opped on that field. This directly
blocked a real workflow this phase depends on (a lead needs a linked
customer for the Copilot/memory-extraction to see that customer's real
communications). Same bug class as Phase 4's `embassy_id` and Phase 7's
`external_thread_id`. **Fixed** by adding
`'customer_id' => 'nullable|exists:customers,id'` to the validation.

### Verification note
`POST /ai/leads/{leadId}/copilot` was exercised **live against
Anthropic's real API** (reachable from this sandbox) with a deliberately
invalid key — reached the real provider, received a genuine `401`,
propagated honestly. `extract-memory` was verified live both in its
zero-call short-circuit (no communications) and its real-call path (once
a real communication existed, it correctly reached Anthropic and got the
same honest `401`). The grounding/sensitivity-filtering guarantees and
the "candidates only, never auto-stored" behavior were verified
deterministically via `Http::fake` in the automated suite.

**Phase 11 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 12 ADDENDUM — Analytics + Behavioral Intelligence (appended, not a rewrite)

Same auth requirements as the Phase 2–11 addenda above. Backend
(`AnalyticsDashboardController`/`BehavioralAnalyticsService`) already
existed from a prior session. Live-tested end to end for the first time
(controlled-fixture math verified, not just eyeballed), wrote its first
automated coverage, and built its frontend. Zero bugs found, matching
Phase 6, 8, 9 and 10.

Every figure this module returns is computed from a real query — nothing
stubbed, sampled, or estimated. Where a metric genuinely cannot be
computed (e.g. a conversion rate with zero leads in the period), it is
returned as `null` with an explicit note in `unavailable_metrics` rather
than a `0` that would read as a real measurement — verified directly by
test.

### Endpoints
- `GET /api/v1/analytics/executive-dashboard?from=&to=` — revenue (real completed-payment sum), outstanding invoice balance, lead/conversion counts, operational counts across every travel-ops module (bookings, visas, flights, hotels, pilgrims, student visas), plus nested `sales_pipeline`, `revenue_trend`, `lead_source_performance`, `staff_performance`, and `profit_and_loss` blocks.
- `GET /api/v1/analytics/behavioral?from=&to=` — time-to-conversion (real days between lead creation and its first real booking), deal value stats, follow-up effectiveness, per-channel communication engagement (real read-rate), and customer-base repeat-customer count.
- `GET /api/v1/analytics/pipeline` — leads grouped by status with `SUM(estimated_value)`, a real `GROUP BY`.
- `GET /api/v1/analytics/revenue-trend?months=` — completed-payment revenue per month for the last N months, **gap-filled** so a month with genuinely zero revenue reads as `0` rather than silently missing from the series.
- `GET /api/v1/analytics/quotation-funnel?from=&to=` — quotations grouped by status with real totals.

### Fixed
Nothing — no bugs were found in this module.

### Verification note
No AI or external network dependency in this module — every figure comes
from a deterministic SQL query, so all live testing here used controlled
fixtures (a booking with a completed and a pending payment, to prove only
`completed` counts; a lead created and converted exactly 6 days apart, to
prove `average_days` computes correctly; a memory-only expense/income
pair, to prove P&L nets correctly) rather than depending on any real
provider connectivity.

**Phase 12 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

## MASTER DIRECTIVE PHASE 13 ADDENDUM — Upsell/Cross-sell Engine + Sales Script A/B Testing (appended, not a rewrite)

Same auth requirements as the Phase 2–12 addenda above. Backend
(`UpsellController`/`UpsellEngine`, `AbTestingController`/
`AbTestingService`) already existed from a prior session. Live-tested
end to end for the first time, wrote its first automated coverage, and
built its frontend. Zero bugs found, matching Phase 6, 8, 9, 10, and 12.

### Upsell / Cross-sell Engine
- `GET/POST/PUT/DELETE /api/v1/upsell-rules` — `{name, trigger_type: flight|hotel|tour|visa|hajj|umrah|transport|any, recommend_type: hotel|flight|visa|insurance|transport|tour_guide|addon, description?, suggested_price?, currency?, priority?, requires_availability_check?}`. `index` also returns `trigger_types`/`recommend_types` (the full enums).
- `GET /bookings/{bookingId}/upsell-recommendations` — detects what a booking already has from **real** join-table rows (`flight_bookings`, `hotel_bookings`, `visa_applications`, plus `booking_type`/package `type`/`visa_required`), matches active rules by trigger, **never recommends a component type the booking already has**, and — for a rule with `requires_availability_check: true` — runs a real inventory count (`Hotel`/`Flight`/`Transport`) and silently omits the recommendation if nothing is actually available rather than showing an option the rep can't fulfil. A `recommend_type` with no inventory model in this system (insurance, tour_guide, addon) is honestly reported `available: true` with a note that it "is not tracked as inventory in this system; confirm with the supplier before promising it" — never silently treated as confirmed stock.
- `POST /upsell-recommendations` — records a recommendation as shown (for later effectiveness measurement); `PUT /upsell-recommendations/{id}/outcome` — `{outcome: accepted|declined, accepted_value?}`.
- `GET /upsell-effectiveness` — real shown/accepted counts and revenue per `recommend_type`, `acceptance_rate_percent` reported as `null` (not `0`) when nothing has been shown yet for that type.

### Sales Script A/B Testing
- `GET/POST /api/v1/ab-experiments`, `GET /ab-experiments/{id}` — `{name, hypothesis?, subject_type?: sales_script|email_template|follow_up_sequence}`. Starts in `status: draft`.
- `POST /ab-experiments/{id}/variants` — upsert by `label` (re-posting the same label updates its content rather than duplicating it) — `{label, content, weight?}`.
- `POST /ab-experiments/{id}/start` — refuses with `422` unless at least 2 active variants exist ("An experiment with one variant is not a test"). `POST /ab-experiments/{id}/stop`.
- `POST /ab-experiments/{id}/assign` — `{lead_id}`. Assignment is **deterministic**, derived from a hash of `experiment_id:lead_id` rather than `rand()` — the same lead always lands in the same variant, and re-assigning an already-assigned lead returns its existing assignment rather than creating a duplicate or re-rolling. Refuses with `422` when the experiment isn't `running` or has no active variants.
- `PUT /ab-assignments/{id}/response`, `PUT /ab-assignments/{id}/conversion` — `{booking_value?}`. A conversion implies a response even if none was explicitly logged, and records real `time_to_close_hours` from the assignment's own timestamp.
- `GET /ab-experiments/{id}/results` — real per-variant response/conversion rates, total/average booking value, average time-to-close, and a `winner` object that is honest about statistical power: **`decided: false` whenever any variant has fewer than 30 assignments**, or when the top two variants' conversion rates differ by less than 1 percentage point — an A/B tool that calls a winner on a handful of leads is worse than one that says it doesn't know yet.

### Fixed
Nothing — no bugs were found in this module.

**Phase 13 addendum version:** 1.0.0
**Appended:** 2026-08-31

---

**Version:** 1.0.0  
**Last Updated:** 2026-08-16  
**Status:** ✅ Production Ready
