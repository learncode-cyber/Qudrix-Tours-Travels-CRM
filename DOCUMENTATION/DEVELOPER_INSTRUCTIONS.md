# 🎯 DEVELOPER INSTRUCTIONS - BUILD WEBSITE

## ⚡ TL;DR (5 Lines)

```
1️⃣  CRM IS READY: Everything is built and deployed at https://yourdomain.com/api/v1
2️⃣  GET API KEY: Login to admin panel → API Keys → Create "Website" key
3️⃣  BUILD WEBSITE: Use Next.js/React to build frontend
4️⃣  CONNECT API: Use API key in requests: -H "X-API-Key: qd_..."
5️⃣  DONE: Each API call automatically handles CRM data sync
```

---

## 🎬 Quick Start (2 Minutes)

### Step 1: Get Credentials
```
CRM Admin URL:    https://yourdomain.com
Admin Email:      admin@example.com
Admin Password:   Password@123
```

### Step 2: Create API Key
1. Login to CRM admin panel
2. Go to: **Admin Panel → API Keys**
3. Click "Create New Key"
4. Name it: `Website`
5. Copy the key: `qd_xxxxxxxxxxxxx`

### Step 3: Use in Your Code
```typescript
const API_KEY = 'qd_xxxxxxxxxxxxx';
const API_URL = 'https://yourdomain.com/api/v1';

// Every request needs this header:
-H "X-API-Key: qd_xxxxxxxxxxxxx"
```

---

## 📋 What to Build

### 🏠 Pages to Create

```
1. Home Page
   ├── Hero section
   ├── Featured packages
   ├── Testimonials
   └── CTA buttons

2. Packages Page
   ├── List all packages from /api/v1/packages
   ├── Filters by type (hajj, umrah, tour)
   ├── Price range filter
   └── Search functionality

3. Package Details Page
   ├── Package info from /api/v1/packages/{id}
   ├── Itinerary timeline
   ├── Inclusions/Exclusions
   ├── Price breakdown
   └── "Book Now" button

4. Booking Form Page
   ├── Customer form (POST /api/v1/customers)
   ├── Travelers details
   ├── Payment method selection
   └── Confirm booking (POST /api/v1/bookings)

5. Contact Form
   ├── Name, email, phone, message
   ├── Submit to /api/v1/communications
   └── Success message

6. About, FAQ, Terms, etc. (Static pages)
```

---

## 🔌 API Endpoints You Need

### Get Data (Display)

```bash
# Get all packages
GET /api/v1/packages
Headers: X-API-Key: qd_...

# Get one package
GET /api/v1/packages/{id}
Headers: X-API-Key: qd_...
```

### Send Data (Forms)

```bash
# Create customer
POST /api/v1/customers
Headers: X-API-Key: qd_...
Body: {
  "first_name": "Abdullah",
  "last_name": "Raiyan",
  "email": "user@example.com",
  "phone": "+8801700000000"
}

# Create booking
POST /api/v1/bookings
Headers: X-API-Key: qd_...
Body: {
  "customer_id": 1,
  "package_id": 1,
  "travelers_count": 2,
  "travel_date": "2024-06-01"
}

# Send contact/inquiry
POST /api/v1/communications
Headers: X-API-Key: qd_...
Body: {
  "name": "Abdullah",
  "email": "user@example.com",
  "phone": "+8801700000000",
  "message": "I want more info..."
}

# Record payment
POST /api/v1/payments
Headers: X-API-Key: qd_...
Body: {
  "booking_id": 1,
  "amount": 450000,
  "payment_method": "bank_transfer"
}
```

---

## 💻 Code Example (Next.js)

### Setup API Client

```typescript
// lib/api.ts
import axios from 'axios';

const API_KEY = process.env.NEXT_PUBLIC_API_KEY;
const API_URL = process.env.NEXT_PUBLIC_API_URL;

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'X-API-Key': API_KEY,
    'Content-Type': 'application/json',
  },
});

export async function getPackages() {
  const res = await apiClient.get('/packages');
  return res.data.data;
}

export async function getPackage(id: number) {
  const res = await apiClient.get(`/packages/${id}`);
  return res.data.data;
}

export async function createBooking(data: any) {
  const res = await apiClient.post('/bookings', data);
  return res.data.data;
}

export async function sendContact(data: any) {
  const res = await apiClient.post('/communications', data);
  return res.data.data;
}
```

### Use in Component

```typescript
// pages/packages.tsx
import { getPackages } from '@/lib/api';

export default function Packages({ packages }) {
  return (
    <div>
      <h1>Our Packages</h1>
      {packages.map((pkg) => (
        <div key={pkg.id}>
          <h2>{pkg.name}</h2>
          <p>Price: {pkg.price} BDT</p>
          <a href={`/packages/${pkg.id}`}>View Details</a>
        </div>
      ))}
    </div>
  );
}

export async function getStaticProps() {
  const packages = await getPackages();
  return {
    props: { packages },
    revalidate: 3600,
  };
}
```

---

## ⚙️ Environment Variables

```bash
# .env.local
NEXT_PUBLIC_API_URL=https://yourdomain.com/api/v1
NEXT_PUBLIC_API_KEY=qd_xxxxxxxxxxxxx
```

---

## 📊 API Response Format

**Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error description",
  "code": "ERROR_CODE"
}
```

---

## ⏱️ Rate Limiting

- **Limit:** 100 requests/minute per API key
- **Headers in Response:**
  - `X-RateLimit-Limit: 100`
  - `X-RateLimit-Remaining: 87`
  - `X-RateLimit-Reset: 1692374460`

If you exceed limit, you get: `429 Too Many Requests`

---

## 🐛 Debugging

### Test API Connection

```bash
curl -H "X-API-Key: qd_..." \
  https://yourdomain.com/api/v1/health
```

Expected Response:
```json
{ "status": "ok" }
```

### Check API Logs
```
Login to CRM Admin → API Keys → View Logs
```

### Common Errors

| Error | Solution |
|-------|----------|
| Missing API key | Add header: `-H "X-API-Key: qd_..."` |
| Invalid key | Check API key in admin panel |
| Rate limit exceeded | Wait 1 minute before next request |
| 404 Package not found | Verify package ID in /packages endpoint |

---

## 📝 Checklist

- [ ] Got API key from admin panel
- [ ] Tested API with curl (health check)
- [ ] Created Next.js/React project
- [ ] Set environment variables
- [ ] Built packages list page
- [ ] Built package details page
- [ ] Built booking form
- [ ] Built contact form
- [ ] Tested all endpoints
- [ ] Deployed website

---

## 🚀 Deployment

### Deploy to Vercel

```bash
# Install Vercel CLI
npm i -g vercel

# Login
vercel login

# Deploy
vercel

# Add environment variables in Vercel dashboard
```

### Set Environment Variables in Vercel
```
NEXT_PUBLIC_API_KEY=qd_...
NEXT_PUBLIC_API_URL=https://yourdomain.com/api/v1
```

---

## ❓ Need Help?

### Check Documentation
- Full API docs: `API_DOCUMENTATION_COMPLETE.md` in ZIP
- TypeScript examples included
- Complete integration examples provided

### Test Everything First
```bash
# Test packages endpoint
curl -H "X-API-Key: qd_..." \
  https://yourdomain.com/api/v1/packages

# Test creating customer
curl -X POST \
  -H "X-API-Key: qd_..." \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Test","email":"test@example.com"}' \
  https://yourdomain.com/api/v1/customers
```

---

## 📦 What's Included in ZIP

```
QUDRIX_CRM_PRODUCTION_READY.zip
├── Complete CRM System (Production ready)
├── Database setup script
├── API Documentation (Complete)
├── TypeScript types & examples
├── Deployment guide
├── This developer guide
└── API client examples
```

---

## ✅ That's It!

**Just:**
1. Get API key ✓
2. Build website pages ✓
3. Call API endpoints ✓
4. Deploy ✓

**The CRM handles everything else:**
- Database storage
- Validations
- Calculations
- Reports
- Automation
- Webhooks
- Analytics

---

**CRM Admin:** admin@example.com / Password@123  
**API Key Format:** qd_xxxxxxxxxxxxx  
**Rate Limit:** 100 req/min  
**Status:** ✅ Production Ready
