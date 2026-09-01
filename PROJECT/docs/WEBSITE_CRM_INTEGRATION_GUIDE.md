# Website ↔ CRM Integration Guide

**For:** Website Developer  
**Purpose:** Integrate website with QUDRIX Travel CRM  
**No code changes required after initial setup**

---

## Overview

The QUDRIX CRM provides a REST API for website integrations. Your website connects to the CRM through:

1. **API Credentials** - Securely stored API keys
2. **REST Endpoints** - Standard HTTP endpoints
3. **Webhooks** - Real-time data sync from CRM to website
4. **Encryption** - All credentials encrypted at rest

No backend code changes needed. Just configure credentials and the API handles the rest.

---

## Architecture

```
Website Admin Panel
    ↓
[Settings → CRM Integration]
    ↓
Enter API Credentials (from CRM Admin)
    ↓
Test Connection
    ↓
Website Auto-Syncs with CRM
    ↓
Leads, Customers, Bookings flow through API
    ↓
Webhooks notify of changes
```

---

## CRM Setup (Admin does this)

1. **CRM Admin Panel** → Settings → Integrations → API Keys
2. **Create API Key** for your website
   - Name: "My Website Integration"
   - Permissions: `packages:read`, `bookings:create`, `customers:create`, `quotations:create`
   - Expiration: 365 days
3. **Copy credentials:**
   - API Key: `qd_xxxxx`
   - API Secret: `sk_xxxxx`

---

## Website Setup (Website Developer does this)

### Step 1: Enter CRM Credentials in Website Admin

Your website needs a Settings panel where admins enter CRM credentials:

```
Website Admin Panel → Settings → CRM Integration
  
  CRM API Key:        [qd_xxxxx]
  CRM API Secret:     [sk_xxxxx]
  CRM Base URL:       [https://yourdomain.com/api/v1]
  
  [Test Connection]
```

### Step 2: Store Credentials Securely

Website stores credentials **encrypted**:

```php
// Example: Laravel
$config = [
    'crm_api_key' => encrypt($apiKey),
    'crm_api_secret' => encrypt($apiSecret),
    'crm_base_url' => 'https://yourdomain.com/api/v1',
];
```

### Step 3: Test Connection

Call CRM health endpoint to verify:

```bash
curl -X GET https://yourdomain.com/api/v1/health \
  -H "Authorization: Bearer qd_xxxxx" \
  -H "X-API-Secret: sk_xxxxx"

# Response:
{
  "success": true,
  "message": "API is healthy",
  "api_version": "v1",
  "crm_status": "operational"
}
```

---

## API Endpoints

### Authentication

Every request requires headers:

```
Authorization: Bearer {API_KEY}
X-API-Secret: {API_SECRET}
Content-Type: application/json
```

### Public Endpoints

#### 1. Get Packages

```bash
GET /api/v1/packages

Query Parameters:
  ?page=1        - Page number
  ?limit=10      - Items per page
  ?status=active - Filter by status
  ?category=hajj - Filter by category

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Hajj 2025",
      "price": 2500,
      "capacity": 50,
      "bookings_count": 25,
      "description": "...",
      "is_active": true,
      "created_at": "2025-01-01T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 45,
    "total_pages": 5
  }
}
```

#### 2. Get Package Details

```bash
GET /api/v1/packages/{id}

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Hajj 2025",
    "price": 2500,
    "capacity": 50,
    "bookings_count": 25,
    "description": "...",
    "inclusions": ["Flight", "Hotel", "Meals"],
    "excludes": ["Travel Insurance"],
    "itinerary": [...],
    "is_active": true
  }
}
```

#### 3. Create Booking

```bash
POST /api/v1/bookings

Body:
{
  "package_id": 1,
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "address": "123 Main St"
  },
  "travelers": [
    {
      "name": "John Doe",
      "age": 30,
      "passport": "AB123456"
    },
    {
      "name": "Jane Doe",
      "age": 28,
      "passport": "AB654321"
    }
  ],
  "travel_date": "2025-08-15",
  "special_requests": "Non-vegetarian"
}

Response:
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 123,
    "booking_reference": "BK-XXXXX",
    "package_id": 1,
    "customer_id": 45,
    "travelers": [
      { "id": 1, "name": "John Doe" },
      { "id": 2, "name": "Jane Doe" }
    ],
    "total_price": 5000,
    "payment_status": "pending",
    "booking_status": "pending",
    "created_at": "2025-01-15T10:30:00Z"
  }
}
```

#### 4. Get Booking Status

```bash
GET /api/v1/bookings/{reference}

Response:
{
  "success": true,
  "data": {
    "id": 123,
    "booking_reference": "BK-XXXXX",
    "package_name": "Hajj 2025",
    "payment_status": "pending",
    "booking_status": "pending",
    "total_price": 5000,
    "created_at": "2025-01-15T10:30:00Z",
    "travelers": [
      { "name": "John Doe" },
      { "name": "Jane Doe" }
    ]
  }
}
```

#### 5. Request Quotation

```bash
POST /api/v1/quotations

Body:
{
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "+1234567890",
  "destination": "Egypt",
  "travel_date": "2025-06-01",
  "duration_days": 14,
  "number_of_travelers": 2,
  "budget_per_person": 3000,
  "special_requests": "Luxury hotels preferred",
  "source": "website"
}

Response:
{
  "success": true,
  "message": "Quotation request created",
  "data": {
    "id": 456,
    "quotation_number": "QT-XXXXX",
    "status": "pending",
    "created_at": "2025-01-15T10:35:00Z"
  }
}
```

#### 6. Get Quotation Details

```bash
GET /api/v1/quotations/{number}

Response:
{
  "success": true,
  "data": {
    "quotation_number": "QT-XXXXX",
    "status": "pending",
    "items": [
      { "description": "Flight", "quantity": 2, "price": 800 },
      { "description": "Hotel", "quantity": 14, "price": 150 }
    ],
    "subtotal": 5600,
    "tax": 300,
    "total": 5900,
    "valid_until": "2025-02-15",
    "created_at": "2025-01-15T10:35:00Z"
  }
}
```

---

## Error Handling

All errors follow this format:

```json
{
  "success": false,
  "message": "User-friendly error message",
  "code": "ERROR_CODE",
  "errors": {
    "field": ["Specific validation error"]
  }
}
```

HTTP Status Codes:

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (bad credentials) |
| 403 | Forbidden (no permission) |
| 404 | Not Found |
| 422 | Validation Failed |
| 429 | Rate Limited |
| 500 | Server Error |

---

## Rate Limiting

**Public API:** 60 requests/minute per API key  
**Admin API:** 30 requests/minute per API key

When rate limited, response includes:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1705331400
```

Retry after `X-RateLimit-Reset` timestamp.

---

## Webhooks (Receive Updates from CRM)

CRM can notify your website of changes in real-time.

### Setup

1. CRM Admin creates webhook → provides webhook secret
2. Website receives webhook at configured URL
3. Verify signature and process event

### Example: Booking Status Changed

```
POST {your_webhook_url}

Headers:
  X-Webhook-Signature: sha256=xxxxx
  X-Webhook-Timestamp: 1705331400
  X-Webhook-Event: booking.updated

Body:
{
  "event": "booking.updated",
  "timestamp": "2025-01-15T10:30:00Z",
  "data": {
    "id": 123,
    "booking_reference": "BK-XXXXX",
    "payment_status": "confirmed",
    "booking_status": "confirmed",
    "total_price": 5000
  }
}
```

### Verify Webhook Signature

```php
// Laravel
$signature = request()->header('X-Webhook-Signature');
$timestamp = request()->header('X-Webhook-Timestamp');
$body = file_get_contents('php://input');

$secret = decrypt(config('crm.webhook_secret'));
$payload = $timestamp . '.' . $body;
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (!hash_equals($signature, $expected)) {
    abort(401, 'Invalid signature');
}
```

---

## Best Practices

### 1. Secure Credential Storage
- Always encrypt API keys and secrets
- Never log credentials
- Rotate credentials annually
- Revoke compromised keys immediately

### 2. Error Handling
- Catch all HTTP exceptions
- Log errors with context
- Notify admins of repeated failures
- Don't expose error details to end users

### 3. Rate Limiting
- Implement exponential backoff
- Cache frequently accessed data
- Batch requests when possible
- Monitor rate limit headers

### 4. Data Validation
- Validate all input before sending to CRM
- Use the exact field names from API docs
- Handle required vs optional fields
- Provide helpful validation messages

### 5. Monitoring
- Log all API requests and responses
- Track success/failure rates
- Alert on connection failures
- Monitor response times

---

## Troubleshooting

### Connection Failed

**Problem:** "Connection test failed"

**Solutions:**
1. Verify CRM Base URL is correct
2. Check API Key and Secret are correct (copy-paste errors)
3. Verify CRM server is accessible from website
4. Check firewall/network policies
5. Verify API Key hasn't expired

### Booking Creation Failed

**Problem:** "Package not found" or "No capacity"

**Solutions:**
1. Verify package_id is correct
2. Check package capacity isn't full
3. Verify package is active
4. Confirm all required fields present

### Rate Limited

**Problem:** "429 Too Many Requests"

**Solutions:**
1. Reduce request frequency
2. Implement caching
3. Request increased rate limit from CRM admin
4. Implement exponential backoff

### Webhook Not Received

**Problem:** Webhooks not arriving

**Solutions:**
1. Verify webhook URL is publicly accessible
2. Check firewall allows inbound HTTPS
3. Verify webhook secret matches
4. Check server logs for errors
5. Contact CRM admin to check webhook status

---

## Code Examples

### Node.js / JavaScript

```javascript
const axios = require('axios');

class CRMClient {
  constructor(baseUrl, apiKey, apiSecret) {
    this.baseUrl = baseUrl;
    this.apiKey = apiKey;
    this.apiSecret = apiSecret;
  }

  async request(method, endpoint, data = null) {
    const url = `${this.baseUrl}${endpoint}`;
    
    const config = {
      method,
      url,
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'X-API-Secret': this.apiSecret,
        'Content-Type': 'application/json',
      },
    };

    if (data) {
      config.data = data;
    }

    try {
      const response = await axios(config);
      return response.data;
    } catch (error) {
      console.error('API Error:', error.response?.data || error.message);
      throw error;
    }
  }

  async getPackages(page = 1, limit = 10) {
    return this.request('GET', `/packages?page=${page}&limit=${limit}`);
  }

  async createBooking(data) {
    return this.request('POST', '/bookings', data);
  }

  async testConnection() {
    return this.request('GET', '/health');
  }
}

// Usage
const crm = new CRMClient(
  'https://yourdomain.com/api/v1',
  'qd_xxxxx',
  'sk_xxxxx'
);

crm.testConnection()
  .then(() => console.log('✅ Connected'))
  .catch(err => console.error('❌ Connection failed:', err));
```

### Python / Flask

```python
import requests
import json
from functools import wraps

class CRMClient:
    def __init__(self, base_url, api_key, api_secret):
        self.base_url = base_url
        self.api_key = api_key
        self.api_secret = api_secret
        self.session = requests.Session()
        self._setup_headers()

    def _setup_headers(self):
        self.session.headers.update({
            'Authorization': f'Bearer {self.api_key}',
            'X-API-Secret': self.api_secret,
            'Content-Type': 'application/json',
        })

    def request(self, method, endpoint, data=None):
        url = f"{self.base_url}{endpoint}"
        try:
            if method == 'GET':
                response = self.session.get(url)
            elif method == 'POST':
                response = self.session.post(url, json=data)
            
            response.raise_for_status()
            return response.json()
        except requests.RequestException as e:
            print(f"API Error: {e}")
            raise

    def test_connection(self):
        return self.request('GET', '/health')

    def get_packages(self, page=1, limit=10):
        return self.request('GET', f'/packages?page={page}&limit={limit}')

    def create_booking(self, booking_data):
        return self.request('POST', '/bookings', booking_data)

# Usage
crm = CRMClient(
    'https://yourdomain.com/api/v1',
    'qd_xxxxx',
    'sk_xxxxx'
)

response = crm.test_connection()
print("✅ Connected" if response.get('success') else "❌ Failed")
```

---

## Support

- **CRM Documentation:** /docs (on CRM server)
- **API Reference:** /api/docs (on CRM server)
- **Contact:** support@yourdomain.com

---

**Last Updated:** August 2026  
**API Version:** v1  
**Status:** Production Ready
