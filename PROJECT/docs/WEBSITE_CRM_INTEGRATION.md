# 🔗 Website ↔ CRM Integration Guide

**QUDRIX Travel CRM API Integration for Website**

---

## 📋 Quick Start (5 Minutes)

### 1. Get API Credentials from CRM Admin Panel

**Path:** Admin Panel → Settings → Integrations → API Keys

1. Click "Create New API Key"
2. Fill in the form:
   - **Name:** "Website Integration" (or any name)
   - **Permissions:** Select required permissions
   - **Expires In:** Set expiration (recommended: 1 year)
3. Click "Create"
4. **IMPORTANT:** Copy and save the key and secret immediately
   - Key: `ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
   - Secret: `sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

### 2. Configure on Website

Create a `.env` file or environment variables on your website:

```env
QUDRIX_CRM_API_URL=https://crm.yourdomain.com/api/v1
QUDRIX_CRM_API_KEY=ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
QUDRIX_CRM_API_SECRET=sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### 3. Test Connection

Run the test endpoint to verify configuration:

```bash
curl -X POST https://crm.yourdomain.com/admin/api/test-connection \
  -H "Content-Type: application/json" \
  -d '{
    "key": "ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "secret": "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }'
```

Expected response:

```json
{
  "success": true,
  "message": "Connection test successful",
  "data": {
    "connected": true,
    "api_key_name": "Website Integration",
    "permissions": ["packages:read", "bookings:create", "quotations:create"],
    "status": "active",
    "crm_version": "1.0.0",
    "latency_ms": 45.23
  }
}
```

---

## 🔐 Authentication

Every API request requires:

1. **API Key** (in Authorization header)
2. **API Secret** (in X-API-Secret header)

### Header Format

```http
Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### cURL Example

```bash
curl -X GET https://crm.yourdomain.com/api/v1/packages \
  -H "Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

### JavaScript/TypeScript Example

```typescript
const apiKey = process.env.QUDRIX_CRM_API_KEY;
const apiSecret = process.env.QUDRIX_CRM_API_SECRET;
const baseUrl = process.env.QUDRIX_CRM_API_URL;

async function makeApiCall(endpoint: string, method: string = 'GET', body?: any) {
  const headers: HeadersInit = {
    'Authorization': `Bearer ${apiKey}`,
    'X-API-Secret': apiSecret,
    'Content-Type': 'application/json',
  };

  const response = await fetch(`${baseUrl}${endpoint}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    throw new Error(`API Error: ${response.status} ${response.statusText}`);
  }

  return response.json();
}
```

### PHP Example

```php
<?php

$apiKey = getenv('QUDRIX_CRM_API_KEY');
$apiSecret = getenv('QUDRIX_CRM_API_SECRET');
$baseUrl = getenv('QUDRIX_CRM_API_URL');

class QudrixCrmClient {
    private $apiKey;
    private $apiSecret;
    private $baseUrl;

    public function __construct($key, $secret, $url) {
        $this->apiKey = $key;
        $this->apiSecret = $secret;
        $this->baseUrl = $url;
    }

    public function request($endpoint, $method = 'GET', $body = null) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'X-API-Secret: ' . $this->apiSecret,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            throw new Exception("API Error: {$statusCode}");
        }

        return json_decode($response, true);
    }
}

$client = new QudrixCrmClient($apiKey, $apiSecret, $baseUrl);
```

---

## 📦 API Endpoints

### 1. **LIST PACKAGES** (Display Packages)

Fetch list of packages for homepage/catalog.

**Request:**

```http
GET /api/v1/packages?page=1&limit=10&search=&type=hajj&sort=created_at&order=desc
```

**Query Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `page` | int | Page number | 1 |
| `limit` | int | Items per page (max: 100) | 10 |
| `search` | string | Search by name/description | - |
| `type` | string | Filter: hajj, umrah, tour, egypt | - |
| `sort` | string | Sort by: created_at, price, duration_days | created_at |
| `order` | string | asc or desc | desc |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Hajj 2024 Premium",
      "type": "hajj",
      "description": "Complete Hajj package with luxury accommodations...",
      "price": 450000,
      "currency": "BDT",
      "duration_days": 20,
      "capacity": 50,
      "bookings_count": 35,
      "image_url": "https://...",
      "rating": 4.8,
      "is_featured": true,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 45,
    "total_pages": 5,
    "has_more": true
  }
}
```

---

### 2. **GET PACKAGE DETAILS** (Package Page)

Get detailed package information.

**Request:**

```http
GET /api/v1/packages/1
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Hajj 2024 Premium",
    "type": "hajj",
    "description": "Full description here...",
    "price": 450000,
    "currency": "BDT",
    "duration_days": 20,
    "capacity": 50,
    "available_seats": 15,
    "bookings_count": 35,
    "image_url": "https://...",
    "images": ["https://...", "https://..."],
    "itinerary": [
      {
        "day": 1,
        "title": "Arrival in Makkah",
        "description": "Flight and hotel check-in"
      }
    ],
    "inclusions": ["Flights", "Hotels", "Meals"],
    "exclusions": ["Travel Insurance"],
    "highlights": ["5-star hotels", "Professional guide"],
    "terms_conditions": "Full T&C here...",
    "cancellation_policy": "7-day cancellation policy...",
    "rating": 4.8,
    "reviews_count": 120,
    "is_featured": true,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

---

### 3. **CREATE BOOKING** (Booking Form)

Submit booking from website.

**Request:**

```http
POST /api/v1/bookings
Content-Type: application/json

{
  "package_id": 1,
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "1234567890",
    "address": "123 Main Street"
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
      "passport": "CD789012"
    }
  ],
  "travel_date": "2024-12-01",
  "special_requests": "Non-vegetarian meals"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 42,
    "booking_reference": "BK-XYWZ1234AB",
    "status": "pending",
    "payment_status": "pending",
    "total_price": 900000,
    "currency": "BDT",
    "travel_date": "2024-12-01T00:00:00Z",
    "created_at": "2024-08-17T10:30:00Z",
    "confirmation_email_sent": true
  },
  "meta": {
    "next_step": "Customer will receive confirmation email. Payment link will be sent shortly."
  }
}
```

**Error Response (Validation):**

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "package_id": ["Package not found"],
    "customer.email": ["Email is invalid"]
  }
}
```

---

### 4. **GET BOOKING STATUS** (Tracking)

Check booking status by reference.

**Request:**

```http
GET /api/v1/bookings/BK-XYWZ1234AB
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 42,
    "booking_reference": "BK-XYWZ1234AB",
    "package_name": "Hajj 2024 Premium",
    "customer_name": "John Doe",
    "status": "pending",
    "payment_status": "pending",
    "total_price": 900000,
    "currency": "BDT",
    "travel_date": "2024-12-01T00:00:00Z",
    "number_of_travelers": 2,
    "travelers": [
      { "name": "John Doe", "age": 30 },
      { "name": "Jane Doe", "age": 28 }
    ],
    "special_requests": "Non-vegetarian meals",
    "created_at": "2024-08-17T10:30:00Z"
  }
}
```

---

### 5. **REQUEST QUOTATION** (Custom Quote)

Request custom quotation for specific requirements.

**Request:**

```http
POST /api/v1/quotations
Content-Type: application/json

{
  "package_id": 1,
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "1234567890"
  },
  "number_of_travelers": 5,
  "travel_date": "2024-12-01",
  "special_requirements": "Need 5-star hotels only",
  "budget": "600000"
}
```

**Response:**

```json
{
  "success": true,
  "message": "Quotation request received successfully",
  "data": {
    "id": 15,
    "quotation_number": "QT-ABC1234XYZ",
    "status": "pending_review",
    "base_price": 2250000,
    "discount_amount": 112500,
    "total_price": 2137500,
    "currency": "BDT",
    "number_of_travelers": 5,
    "valid_until": "2024-08-24T10:30:00Z",
    "created_at": "2024-08-17T10:30:00Z"
  },
  "meta": {
    "next_step": "Our team will review and send detailed quotation within 24 hours"
  }
}
```

---

### 6. **GET QUOTATION** (Quote Details)

Retrieve quotation details by reference.

**Request:**

```http
GET /api/v1/quotations/QT-ABC1234XYZ
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 15,
    "quotation_number": "QT-ABC1234XYZ",
    "package_name": "Hajj 2024 Premium",
    "customer_name": "John Doe",
    "status": "pending_review",
    "travel_date": "2024-12-01T00:00:00Z",
    "number_of_travelers": 5,
    "base_price": 2250000,
    "discount_amount": 112500,
    "discount_percentage": 5,
    "total_price": 2137500,
    "currency": "BDT",
    "price_per_person": 427500,
    "valid_until": "2024-08-24T10:30:00Z",
    "special_requirements": "Need 5-star hotels only",
    "created_at": "2024-08-17T10:30:00Z"
  }
}
```

---

## ⚠️ Error Handling

### Error Response Format

```json
{
  "success": false,
  "message": "Human-readable error message",
  "code": "ERROR_CODE",
  "errors": {
    "field_name": ["Validation error 1", "Validation error 2"]
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `MISSING_API_KEY` | 401 | API key not provided |
| `MISSING_API_SECRET` | 401 | API secret not provided |
| `INVALID_CREDENTIALS` | 401 | API key/secret is invalid |
| `KEY_EXPIRED` | 403 | API key has expired |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `CREATION_ERROR` | 500 | Server error during creation |

---

## 🔄 Rate Limiting

- **Read requests:** 60 requests per minute
- **Write requests:** 30 requests per minute
- **Response headers:**

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1692266460
```

---

## 🧪 Testing with cURL

### List Packages

```bash
curl -X GET "https://crm.yourdomain.com/api/v1/packages?limit=5" \
  -H "Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
```

### Create Booking

```bash
curl -X POST "https://crm.yourdomain.com/api/v1/bookings" \
  -H "Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "package_id": 1,
    "customer": {
      "name": "Test User",
      "email": "test@example.com",
      "phone": "1234567890"
    },
    "travelers": [{"name": "Test User", "age": 30}],
    "travel_date": "2024-12-01"
  }'
```

---

## 📚 Code Examples

### React Component Example

```typescript
import { useState, useEffect } from 'react';

const PackageList = () => {
  const [packages, setPackages] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPackages = async () => {
      try {
        const response = await fetch(
          `${process.env.REACT_APP_CRM_API_URL}/packages`,
          {
            headers: {
              'Authorization': `Bearer ${process.env.REACT_APP_CRM_API_KEY}`,
              'X-API-Secret': process.env.REACT_APP_CRM_API_SECRET,
            },
          }
        );
        const data = await response.json();
        setPackages(data.data);
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
      {packages.map((pkg) => (
        <div key={pkg.id}>
          <h3>{pkg.name}</h3>
          <p>৳ {pkg.price}</p>
          <p>{pkg.duration_days} days</p>
        </div>
      ))}
    </div>
  );
};

export default PackageList;
```

### Next.js API Route Example

```typescript
// pages/api/packages.ts
import type { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const response = await fetch(
    `${process.env.QUDRIX_CRM_API_URL}/packages`,
    {
      headers: {
        'Authorization': `Bearer ${process.env.QUDRIX_CRM_API_KEY}`,
        'X-API-Secret': process.env.QUDRIX_CRM_API_SECRET,
      },
    }
  );

  const data = await response.json();
  res.status(200).json(data);
}
```

---

## 🔒 Security Best Practices

1. **Never commit API credentials** to version control
2. **Use environment variables** for all sensitive data
3. **Rotate API keys** regularly (every 90 days)
4. **Monitor API usage** in CRM admin panel
5. **Use HTTPS only** for all API calls
6. **Validate all inputs** on website before sending
7. **Don't log API secrets** in client console or logs
8. **Revoke keys** immediately if compromised

---

## 📞 Support

For integration issues:

1. Check API documentation: `https://crm.yourdomain.com/api/docs`
2. Review error codes and messages
3. Test with cURL first before implementing in code
4. Check rate limits and usage stats in admin panel
5. Contact CRM admin if authentication fails

---

**Last Updated:** August 17, 2024  
**API Version:** 1.0.0  
**Status:** Production Ready ✅
