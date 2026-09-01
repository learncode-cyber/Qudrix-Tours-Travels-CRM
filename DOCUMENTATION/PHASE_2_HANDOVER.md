# 📦 PHASE 2: WEBHOOKS & SECURITY - HANDOVER GUIDE

**Complete Deployment & Implementation Guide**

---

## 🚀 QUICK START

### For Deployment Teams

```bash
# 1. Extract the ZIP
unzip QUDRIX_CRM_API_INTEGRATION_PHASE_2.zip
cd qudrix-phase-0

# 2. Install dependencies
composer install --no-dev

# 3. Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 4. Configure database (edit .env)
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=qudrix_crm
DB_USERNAME=your-user
DB_PASSWORD=your-password

# 5. Run migrations
php artisan migrate

# 6. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Run tests (must pass)
php artisan test tests/Api/WebhookTest.php

# 8. Deploy to production
# Use your deployment tool (Deployer, Capistrano, etc.)

# 9. Verify health
curl https://yourdomain.com/api/v1/health
```

---

## 📋 WHAT'S NEW IN PHASE 2

### New Features

**Webhook System**
- Send events to external URLs when certain actions happen
- 8 different event types supported
- Automatic retry with exponential backoff
- HMAC signature verification
- Complete delivery tracking

**Event Types**
```
✅ lead.created     - When a new lead is created
✅ lead.updated     - When a lead is updated
✅ booking.created  - When a booking is created
✅ booking.updated  - When a booking is updated
✅ booking.confirmed - When a booking is confirmed
✅ booking.cancelled - When a booking is cancelled
✅ payment.updated  - When a payment status changes
✅ package.updated  - When a package is updated
```

**Admin Features**
- Create and manage webhooks
- Select which events to listen for
- Monitor delivery status
- Retry failed deliveries
- View complete delivery logs
- Test webhooks
- Rotate webhook secrets

**Security**
- IDOR vulnerability fixed
- Tenant isolation enforced
- Injection attacks prevented
- Rate limiting active
- HTTPS enforced
- Secrets hashed and secure

---

## 🔧 HOW TO USE WEBHOOKS

### 1. Create a Webhook in Admin Panel

```bash
curl -X POST https://yourdomain.com/admin/api/webhooks \
  -H "Authorization: Bearer [jwt-token]" \
  -H "Content-Type: application/json" \
  -d '{
    "api_key_id": 1,
    "url": "https://yourapp.com/webhook",
    "events": ["booking.created", "booking.confirmed"],
    "is_active": true,
    "retry_limit": 5
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "url": "https://yourapp.com/webhook",
    "events": ["booking.created", "booking.confirmed"],
    "is_active": true,
    "secret": "sk_abcdef1234567890..."
  }
}
```

### 2. Verify Webhook Signature

When you receive a webhook, verify the signature:

**PHP Example:**
```php
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$payload = file_get_contents('php://input');
$secret = 'sk_abcdef1234567890...'; // From response

$expected = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}

$data = json_decode($payload, true);
echo 'Webhook verified! Event: ' . $data['event'];
```

**JavaScript/Node.js Example:**
```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
    const expected = crypto
        .createHmac('sha256', secret)
        .update(JSON.stringify(payload))
        .digest('hex');
    
    return crypto.timingSafeEqual(
        Buffer.from(expected),
        Buffer.from(signature)
    );
}

app.post('/webhook', (req, res) => {
    const signature = req.headers['x-webhook-signature'];
    
    if (!verifyWebhook(req.body, signature, 'sk_xxx')) {
        return res.status(401).json({ error: 'Invalid signature' });
    }
    
    console.log('Event:', req.body.event);
    console.log('Data:', req.body.data);
    
    res.json({ success: true });
});
```

### 3. Handle Webhook Events

**Example: Handle booking.created event:**
```php
if ($data['event'] === 'booking.created') {
    $booking = $data['data'];
    
    // Do something with the booking
    Log::info('New booking created', $booking);
    
    // Send confirmation email
    Mail::send('booking-created', $booking, function ($m) use ($booking) {
        $m->to($booking['customer_email']);
    });
}
```

### 4. Respond to Webhooks

**Important:** Always respond with HTTP 2xx status to indicate successful processing:

```php
// Good - Webhook processed successfully
http_response_code(200);
json_encode(['success' => true]);

// Good - Webhook received but queued for processing
http_response_code(202);
json_encode(['success' => true, 'message' => 'Processing']);

// Bad - Will trigger automatic retry
http_response_code(500);
json_encode(['error' => 'Internal error']);
```

---

## 📊 WEBHOOK PAYLOAD FORMAT

### Payload Structure
```json
{
  "event": "booking.created",
  "timestamp": "2026-08-17T12:00:00Z",
  "data": {
    "id": 123,
    "reference": "BK-2026-00123",
    "customer_id": 456,
    "package_id": 789,
    "total_price": 50000,
    "status": "pending",
    "created_at": "2026-08-17T12:00:00Z"
  },
  "api_version": "v1"
}
```

### Webhook Headers
```
X-Webhook-Signature: a1b2c3d4e5f6... (HMAC-SHA256)
X-Webhook-ID: 550e8400-e29b-41d4-a716-446655440000 (UUID)
X-Webhook-Event: booking.created
User-Agent: QUDRIX-Webhook/1.0
Content-Type: application/json
```

---

## 🔄 RETRY LOGIC

### Automatic Retries

When a webhook delivery fails, it automatically retries with exponential backoff:

| Attempt | Delay | Status |
|---------|-------|--------|
| 1 | Immediate | Failed |
| 2 | 5 seconds | Retry |
| 3 | 25 seconds | Retry |
| 4 | 125 seconds | Retry |
| 5 | 625 seconds | Retry |
| 6 | 3125 seconds (52 min) | Retry |

After 5 retries (configurable), the webhook is marked as failed and requires manual retry.

### Manual Retry

```bash
curl -X POST https://yourdomain.com/admin/api/webhooks/1/retry \
  -H "Authorization: Bearer [jwt-token]" \
  -d '{"delivery_id": 1}'
```

---

## 📈 MONITORING WEBHOOKS

### Check Webhook Status

```bash
curl https://yourdomain.com/admin/api/webhooks/1 \
  -H "Authorization: Bearer [jwt-token]"
```

### View Delivery History

```bash
curl https://yourdomain.com/admin/api/webhooks/1/deliveries \
  -H "Authorization: Bearer [jwt-token]"
```

### View Statistics

```bash
curl https://yourdomain.com/admin/api/webhooks/1/statistics \
  -H "Authorization: Bearer [jwt-token]"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_deliveries": 1250,
    "successful": 1240,
    "failed": 10,
    "pending": 0,
    "success_rate": 99.2,
    "last_triggered": "2026-08-17T12:30:00Z",
    "last_status": "success"
  }
}
```

---

## 🧪 TESTING WEBHOOKS

### Test a Webhook

```bash
curl -X POST https://yourdomain.com/admin/api/webhooks/1/test \
  -H "Authorization: Bearer [jwt-token]"
```

This sends a test webhook to verify:
- ✅ URL is reachable
- ✅ Signature verification works
- ✅ Your endpoint responds correctly

### Webhook Testing Tools

**Using ngrok (local testing):**
```bash
# Install ngrok
brew install ngrok  # macOS
apt-get install ngrok  # Ubuntu

# Expose local server
ngrok http 8000

# Use ngrok URL in webhook
https://random123.ngrok.io/webhook

# View requests in ngrok dashboard
# http://localhost:4040
```

**Using webhook.site (debugging):**
```
1. Visit https://webhook.site
2. Copy your unique URL
3. Use it in webhook configuration
4. See all webhook deliveries in real-time
```

---

## 🔐 SECURITY BEST PRACTICES

### 1. Always Verify Signatures

```php
// ✅ GOOD - Always verify
if (!verifyWebhookSignature($request)) {
    abort(401);
}

// ❌ BAD - Skipping verification
// Trust any request to webhook endpoint
```

### 2. Use HTTPS for Webhook URLs

```php
// ✅ GOOD
'https://yourapp.com/webhook'

// ❌ BAD
'http://yourapp.com/webhook'  // Insecure!
```

### 3. Implement Webhook Authentication

```php
// ✅ Also include your own authentication
$headers = [
    'Authorization' => 'Bearer ' . $apiKey,
    'X-Custom-Auth' => hash('sha256', $timestamp . $secret),
];
```

### 4. Process Webhooks Asynchronously

```php
// ✅ GOOD - Queue the webhook for processing
Queue::push(new ProcessWebhook($data));
response()->json(['success' => true]); // Return immediately

// ❌ BAD - Process synchronously
// Slow response, risk of timeout
processWebhook($data);
```

### 5. Handle Duplicate Deliveries

```php
// ✅ Use webhook ID to prevent duplicates
$webhookId = $request->header('X-Webhook-ID');
if (WebhookLog::where('webhook_id', $webhookId)->exists()) {
    return response()->json(['success' => true]); // Already processed
}
```

---

## 📚 FILE STRUCTURE

### New/Modified Files

```
app/
├── Services/Webhook/
│   ├── WebhookService.php
│   ├── WebhookDeliveryService.php
│   ├── WebhookEventDispatcher.php
│   └── HmacSignatureService.php
├── Http/Controllers/Admin/
│   └── AdminWebhookController.php
└── Models/
    ├── Webhook.php (updated)
    ├── WebhookDelivery.php
    └── WebhookLog.php (updated)

database/migrations/
├── 2024_08_17_000012_create_webhooks_table.php
├── 2024_08_17_000013_create_webhook_deliveries_table.php
└── 2024_08_17_000014_create_webhook_logs_table.php

tests/Api/
└── WebhookTest.php

routes/
└── api-public.php (13 new webhook routes)

docs/
├── SECURITY_AUDIT_PHASE_2.md
└── LOAD_TESTING_PHASE_2.md
```

---

## ✅ DEPLOYMENT VERIFICATION

After deployment, verify everything works:

```bash
# 1. Health check
curl https://yourdomain.com/api/v1/health
# Expected: { "success": true, "message": "API is healthy" }

# 2. Available events
curl https://yourdomain.com/admin/api/webhooks/events \
  -H "Authorization: Bearer [token]"
# Expected: List of 8 events

# 3. Create test webhook
curl -X POST https://yourdomain.com/admin/api/webhooks \
  -H "Authorization: Bearer [token]" \
  -d '{"api_key_id": 1, "url": "https://example.com", "events": ["booking.created"]}'
# Expected: 201 Created

# 4. Test webhook
curl -X POST https://yourdomain.com/admin/api/webhooks/1/test \
  -H "Authorization: Bearer [token]"
# Expected: { "success": true }

# 5. View statistics
curl https://yourdomain.com/admin/api/webhooks/1/statistics \
  -H "Authorization: Bearer [token]"
# Expected: Statistics JSON
```

---

## 🆘 TROUBLESHOOTING

### Webhook Not Triggering

**Issue:** Webhook not being called when event happens

**Solutions:**
1. Check webhook is active: `is_active = true`
2. Verify event is selected
3. Check webhook URL is correct
4. Test webhook manually
5. Check firewall/network issues

### Webhook Failing to Deliver

**Issue:** Webhook fails to deliver, keeps retrying

**Solutions:**
1. Verify webhook URL is reachable
2. Check webhook returns HTTP 200-299
3. Verify signature verification code
4. Check server logs for errors
5. Test locally with webhook.site

### Missing Headers in Request

**Issue:** Custom headers not received

**Solutions:**
1. Verify server accepts custom headers
2. Check nginx/Apache configuration allows headers
3. Verify firewall not stripping headers
4. Check Content-Type is application/json

---

## 📞 SUPPORT & DOCUMENTATION

**For help:**
- ✅ `docs/SECURITY_AUDIT_PHASE_2.md` - Security details
- ✅ `docs/LOAD_TESTING_PHASE_2.md` - Performance info
- ✅ `tests/Api/WebhookTest.php` - Test examples
- ✅ `PHASE_2_STATUS.md` - Completion status

---

## 📝 NEXT STEPS

After Phase 2 deployment:

1. **Test webhooks** in staging
2. **Monitor deliveries** with statistics
3. **Set up alerts** for failed webhooks
4. **Document endpoints** for integrations
5. **Plan Phase 3** (analytics, webhooks batching)

---

**Phase 2 Handover Complete** ✅  
**Ready for Production** ✅  
**Date:** August 17, 2026

---

*For questions or issues, check the documentation or contact support.*
