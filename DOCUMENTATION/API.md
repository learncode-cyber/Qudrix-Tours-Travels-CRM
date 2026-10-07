# QUDRIX CRM - Phase 0 API Documentation

**API Version:** v1  
**Base URL:** `https://yourdomain.com/api/v1`  
**Authentication:** JWT Bearer Token

---

## Authentication Endpoints

### 1. Register New Account

**Endpoint:** `POST /register`

**Request Body:**
```json
{
  "tenant_name": "My Company",
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123"
}
```

**Response (201 Created):**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "tenant_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "is_active": true
  },
  "tenant": {
    "id": 1,
    "name": "My Company",
    "slug": "my-company",
    "is_active": true
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

---

### 2. Login

**Endpoint:** `POST /login`

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123"
}
```

**Response (200 OK):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Error (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Invalid credentials"]
  }
}
```

---

### 3. Logout

**Endpoint:** `POST /logout`  
**Authentication:** Required (Bearer Token)

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Logged out successfully"
}
```

---

### 4. Get Current User Profile

**Endpoint:** `GET /profile`  
**Authentication:** Required (Bearer Token)

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "tenant_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
      {
        "id": 1,
        "name": "super-admin",
        "display_name": "Super Admin"
      }
    ]
  },
  "permissions": ["*"]
}
```

---

## Health Check Endpoint

### System Health

**Endpoint:** `GET /health`  
**Authentication:** Not required

**Response (200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00Z",
  "database": "connected",
  "version": "1.0.0"
}
```

**Error Response (503 Service Unavailable):**
```json
{
  "status": "unhealthy",
  "error": "Database connection failed"
}
```

---

## Error Handling

### Common HTTP Status Codes

| Status | Meaning | Example |
|--------|---------|---------|
| 200 | OK | Successful request |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input format |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 422 | Validation Error | Field validation failed |
| 503 | Service Unavailable | Database error |

### Error Response Format

```json
{
  "message": "Error message",
  "errors": {
    "field_name": ["Error description"]
  }
}
```

---

## Authentication

### JWT Token Usage

All protected endpoints require JWT token in Authorization header:

```
Authorization: Bearer <token>
```

### Token Structure

JWT tokens contain:
- **Header:** Token type and algorithm
- **Payload:** User ID, tenant ID, email, expiration
- **Signature:** Secure signature

### Token Expiration

Default expiration: **3600 seconds (1 hour)**

Set in `.env`:
```
JWT_EXPIRY=3600
```

---

## Rate Limiting (Phase 0)

Current implementation:
- No rate limiting in Phase 0
- Will be added in Phase 2

---

## Pagination (Future)

Not implemented in Phase 0. Future endpoints will support:

```
GET /api/v1/customers?page=1&per_page=20&sort=-created_at
```

---

## Versioning

API endpoints follow versioning pattern: `/api/v1/`

Current version: **v1**

Future versions (Phase 2+): `/api/v2/`, `/api/v3/`, etc.

---

## Response Formats

All responses are in **JSON** format.

### Success Response
```json
{
  "data": { /* actual data */ },
  "message": "Operation successful"
}
```

### Error Response
```json
{
  "message": "Error description",
  "errors": { /* detailed errors */ }
}
```

---

## Testing API Endpoints

### Using cURL

```bash
# Health check
curl -X GET http://localhost:8000/api/v1/health

# Register
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "Test Org",
    "name": "Test User",
    "email": "test@example.com",
    "password": "Password@123"
  }'

# Login
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Password@123"
  }'

# Get Profile (with token)
curl -X GET http://localhost:8000/api/v1/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Using Postman

1. Create POST request to `/register`
2. Copy `token` from response
3. In next requests, set Authorization header:
   - Type: Bearer Token
   - Token: (paste your token)

---

## Future API Endpoints (Phase 1+)

Phase 1 will add endpoints for:
- Customer CRUD
- Lead Management
- Communication tracking
- Task Management

See PROJECT_STATUS.md for roadmap.
