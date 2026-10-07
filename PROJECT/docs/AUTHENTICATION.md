# 🔐 QUDRIX CRM API Authentication

Complete authentication guide for API integrations.

---

## Authentication Overview

The QUDRIX CRM API uses **API Key-based authentication** with two components:

1. **API Key** (Public identifier)
   - Format: `ak_` prefix + 32 random characters
   - Used in `Authorization` header
   - Can be safely logged/monitored

2. **API Secret** (Private credential)
   - Format: `sk_` prefix + 64 random characters
   - Used in `X-API-Secret` header
   - Never share, log, or commit to version control
   - Shown only once when created

---

## Creating API Keys

### Via Admin Panel

1. Navigate to: **Settings → Integrations → API Keys**
2. Click **"Create New API Key"**
3. Fill in the form:
   ```
   Name: Website Integration
   Permissions: packages:read, bookings:create, quotations:create
   Description: For QUDRIX website integration
   Expires In: 365 days
   ```
4. Click **"Create"**
5. Copy and securely store the key and secret

### Via API (Admin Only)

```http
POST /admin/api/api-keys
Authorization: Bearer {admin-jwt-token}

{
  "name": "Website Integration",
  "permissions": ["packages:read", "bookings:create", "quotations:create"],
  "description": "For QUDRIX website integration",
  "expires_in_days": 365
}
```

---

## Request Headers

Every API request **must** include these headers:

```http
Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json
```

### Header Details

| Header | Value | Required | Notes |
|--------|-------|----------|-------|
| `Authorization` | `Bearer {API_KEY}` | Yes | Must start with "Bearer " |
| `X-API-Secret` | `{API_SECRET}` | Yes | Full secret string |
| `Content-Type` | `application/json` | For POST/PUT | Only for requests with body |

---

## Request Examples

### cURL

```bash
curl -X GET "https://crm.yourdomain.com/api/v1/packages" \
  -H "Authorization: Bearer ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json"
```

### Python

```python
import requests

api_key = "ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
api_secret = "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
base_url = "https://crm.yourdomain.com/api/v1"

headers = {
    "Authorization": f"Bearer {api_key}",
    "X-API-Secret": api_secret,
    "Content-Type": "application/json",
}

response = requests.get(f"{base_url}/packages", headers=headers)
data = response.json()
print(data)
```

### JavaScript (Fetch)

```javascript
const apiKey = "ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
const apiSecret = "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
const baseUrl = "https://crm.yourdomain.com/api/v1";

async function fetchPackages() {
  const response = await fetch(`${baseUrl}/packages`, {
    method: "GET",
    headers: {
      "Authorization": `Bearer ${apiKey}`,
      "X-API-Secret": apiSecret,
      "Content-Type": "application/json",
    },
  });

  if (!response.ok) {
    throw new Error(`API Error: ${response.status}`);
  }

  return response.json();
}
```

### JavaScript (Axios)

```javascript
import axios from 'axios';

const apiKey = "ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
const apiSecret = "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";

const client = axios.create({
  baseURL: "https://crm.yourdomain.com/api/v1",
  headers: {
    "Authorization": `Bearer ${apiKey}`,
    "X-API-Secret": apiSecret,
    "Content-Type": "application/json",
  },
});

// Usage
client.get('/packages')
  .then(response => console.log(response.data))
  .catch(error => console.error('API Error:', error.response.data));
```

### PHP

```php
<?php

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
            "Authorization: Bearer " . $this->apiKey,
            "X-API-Secret: " . $this->apiSecret,
            "Content-Type: application/json",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

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

    public function getPackages($page = 1, $limit = 10) {
        return $this->request("/packages?page={$page}&limit={$limit}");
    }

    public function createBooking($data) {
        return $this->request("/bookings", "POST", $data);
    }
}

// Usage
$client = new QudrixCrmClient(
    getenv('QUDRIX_API_KEY'),
    getenv('QUDRIX_API_SECRET'),
    getenv('QUDRIX_API_URL')
);

$packages = $client->getPackages(1, 10);
```

### TypeScript (Complete Client)

```typescript
interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
}

class QudrixCrmClient {
  private baseUrl: string;
  private apiKey: string;
  private apiSecret: string;

  constructor(baseUrl: string, apiKey: string, apiSecret: string) {
    this.baseUrl = baseUrl;
    this.apiKey = apiKey;
    this.apiSecret = apiSecret;
  }

  private getHeaders(): HeadersInit {
    return {
      "Authorization": `Bearer ${this.apiKey}`,
      "X-API-Secret": this.apiSecret,
      "Content-Type": "application/json",
    };
  }

  async request<T>(
    endpoint: string,
    method: string = 'GET',
    body?: any
  ): Promise<ApiResponse<T>> {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      method,
      headers: this.getHeaders(),
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
      throw new Error(`API Error: ${response.status} ${response.statusText}`);
    }

    return response.json();
  }

  async getPackages(page: number = 1, limit: number = 10): Promise<ApiResponse<any>> {
    return this.request(`/packages?page=${page}&limit=${limit}`);
  }

  async getPackageDetails(id: number): Promise<ApiResponse<any>> {
    return this.request(`/packages/${id}`);
  }

  async createBooking(data: any): Promise<ApiResponse<any>> {
    return this.request('/bookings', 'POST', data);
  }

  async getBookingStatus(reference: string): Promise<ApiResponse<any>> {
    return this.request(`/bookings/${reference}`);
  }

  async requestQuotation(data: any): Promise<ApiResponse<any>> {
    return this.request('/quotations', 'POST', data);
  }

  async getQuotationDetails(number: string): Promise<ApiResponse<any>> {
    return this.request(`/quotations/${number}`);
  }
}

// Usage
const client = new QudrixCrmClient(
  process.env.QUDRIX_CRM_API_URL!,
  process.env.QUDRIX_CRM_API_KEY!,
  process.env.QUDRIX_CRM_API_SECRET!
);

// Fetch packages
const packagesResponse = await client.getPackages(1, 10);
if (packagesResponse.success) {
  console.log(packagesResponse.data);
}
```

---

## Authentication Errors

### Missing API Key

```http
Status: 401 Unauthorized

{
  "success": false,
  "message": "Missing or invalid API key",
  "code": "MISSING_API_KEY"
}
```

**Solution:** Ensure `Authorization` header includes the full Bearer token.

### Missing API Secret

```http
Status: 401 Unauthorized

{
  "success": false,
  "message": "Missing API secret",
  "code": "MISSING_API_SECRET"
}
```

**Solution:** Ensure `X-API-Secret` header is included with the full secret.

### Invalid Credentials

```http
Status: 401 Unauthorized

{
  "success": false,
  "message": "Invalid API credentials",
  "code": "INVALID_CREDENTIALS"
}
```

**Solution:** Verify API key and secret are correct. Check if key is active in admin panel.

### Key Expired

```http
Status: 403 Forbidden

{
  "success": false,
  "message": "API key has expired",
  "code": "KEY_EXPIRED"
}
```

**Solution:** Create a new API key in admin panel → Settings → Integrations → API Keys.

### Key Revoked

```http
Status: 403 Forbidden

{
  "success": false,
  "message": "API key is revoked",
  "code": "KEY_NOT_ACTIVE"
}
```

**Solution:** Contact CRM administrator to restore or create a new key.

---

## Permissions

Each API key has a set of permissions that control what it can access.

### Available Permissions

| Permission | Description |
|------------|-------------|
| `packages:read` | Read package list and details |
| `packages:create` | Create new packages |
| `packages:update` | Update existing packages |
| `packages:delete` | Delete packages |
| `bookings:create` | Create bookings |
| `bookings:read` | Read booking details |
| `bookings:update` | Update bookings |
| `bookings:cancel` | Cancel bookings |
| `quotations:create` | Create quotation requests |
| `quotations:read` | Read quotation details |
| `quotations:update` | Update quotations |
| `customers:create` | Create customers |
| `customers:read` | Read customer details |
| `customers:update` | Update customers |
| `payments:read` | Read payment information |
| `payments:create` | Create payments |
| `analytics:read` | Access analytics data |

### Checking Permissions

When you test a connection, you'll receive the permissions assigned to your key:

```json
{
  "success": true,
  "message": "Connection test successful",
  "data": {
    "connected": true,
    "permissions": ["packages:read", "bookings:create", "quotations:create"],
    "status": "active"
  }
}
```

---

## Key Rotation

Rotate your API key every 90 days for security:

### Via Admin Panel

1. Go to **Settings → Integrations → API Keys**
2. Click the key you want to rotate
3. Click **"Rotate Key"**
4. A new key and secret will be generated
5. Update your applications immediately

### Via API

```http
POST /admin/api/api-keys/{id}/rotate
Authorization: Bearer {admin-jwt-token}

Response:
{
  "success": true,
  "message": "API key rotated successfully",
  "data": {
    "id": 42,
    "key": "ak_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "secret": "sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }
}
```

---

## Security Best Practices

1. **Store secrets safely**
   - Use environment variables, not hardcoded
   - Never commit to version control
   - Use `.env` files (add to `.gitignore`)

2. **Rotate regularly**
   - Change API keys every 90 days
   - Rotate immediately if compromised
   - Keep old keys for short grace period

3. **Monitor usage**
   - Check API logs in admin panel
   - Monitor rate limit usage
   - Alert on unusual activity

4. **Use HTTPS only**
   - All requests must use HTTPS
   - Never send credentials over HTTP
   - Verify SSL certificates

5. **Limit permissions**
   - Grant only required permissions
   - Create separate keys for different services
   - Revoke unused keys

6. **Never expose secrets**
   - Don't log full secrets
   - Don't show in error messages
   - Don't send in URLs or query params
   - Don't log in browser console

---

## Troubleshooting

### Connection Test Failing

1. Verify API key and secret are correct
2. Check if key is active (not revoked/expired)
3. Ensure URL is correct: `https://crm.yourdomain.com`
4. Check network connectivity
5. Verify headers are formatted correctly

### 401 Unauthorized

1. Check if API key/secret is included in headers
2. Verify Bearer token format: `Bearer ak_...`
3. Confirm secrets match exactly (copy-paste)
4. Check if key has expired
5. Verify key status in admin panel

### 403 Forbidden

1. Key may be revoked or expired
2. Check key status in admin panel
3. Try creating a new key
4. Verify key permissions include required operation

### Rate Limit (429)

1. Reduce request frequency
2. Implement request queuing
3. Check rate limit headers in response
4. Review usage stats in admin panel
5. Contact CRM admin if limits need adjustment

---

**Last Updated:** August 17, 2024  
**API Version:** 1.0.0
