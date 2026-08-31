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

**Version:** 1.0.0  
**Last Updated:** 2026-08-16  
**Status:** ✅ Production Ready
