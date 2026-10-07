# QUDRIX CRM — Performance Optimization Guide

## 1. Database Query Optimization

### 1.1 Eager Loading (N+1 Prevention)

**Problem**: Fetching 100 bookings, then looping to get each booking's customer = 101 queries.

**Solution**: Use `with()` in Eloquent:

```php
// ❌ BAD (N+1 query problem)
$bookings = Booking::all();
foreach ($bookings as $booking) {
    echo $booking->customer->name; // Query per booking
}

// ✅ GOOD (eager loading)
$bookings = Booking::with('customer', 'flights', 'hotels')->get();
foreach ($bookings as $booking) {
    echo $booking->name; // Already loaded
}
```

**Applied in QUDRIX**: All list controllers should use eager loading:
```php
// app/Http/Controllers/BookingController.php
public function index(Request $request)
{
    return Booking::where('tenant_id', $request->user->tenant_id)
        ->with(['customer', 'flights', 'hotels', 'installmentPlans'])
        ->paginate(25);
}
```

### 1.2 Query Scoping with Indexes

**Problem**: Scanning entire table when filtering by tenant_id.

**Solution**: Composite index on (tenant_id, status, created_at):

```php
// database/migrations/2024_01_01_000001_create_bookings_table.php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
    $table->string('status');
    $table->timestamps();
    
    // Multi-column index
    $table->index(['tenant_id', 'status', 'created_at']);
});
```

**Applied in QUDRIX**: All migrations have multi-column indexes. Example:
```php
$table->index(['tenant_id', 'status']);
$table->index(['tenant_id', 'created_at']);
```

### 1.3 Selective Column Selection

**Problem**: `SELECT *` loads all columns, even ones not needed (e.g., large JSON fields).

**Solution**: Explicitly select needed columns:

```php
// ❌ BAD: loads unused columns
$customers = Customer::all();

// ✅ GOOD: only needed columns
$customers = Customer::select('id', 'tenant_id', 'name', 'email')->get();
```

**Applied in QUDRIX**: Controllers in Phase 18 were updated:
```php
// app/Http/Controllers/AIProviderController.php
->select('id', 'tenant_id', 'provider', 'name', 'default_model', 
         'cost_per_1k_prompt_tokens', 'cost_per_1k_completion_tokens', ...)
```

### 1.4 Pagination for Large Lists

**Problem**: Loading 1M bookings at once = memory spike, slow response.

**Solution**: Always paginate:

```php
// ✅ GOOD: 25 per page
$bookings = Booking::where('tenant_id', $request->user->tenant_id)
    ->with('customer')
    ->paginate(25); // or ?per_page=50

// Frontend: iterate pages via next/prev links
```

**Applied in QUDRIX**: All list endpoints paginate by default:
```php
Route::get('/bookings', 'BookingController@index'); // ?page=1&per_page=25
```

### 1.5 Database Connection Pooling

**Problem**: Opening/closing MySQL connection per request = slow.

**Solution**: Use connection pooling (ProxySQL, MaxScale):

```bash
# .env
DB_HOST=proxy.example.com  # ProxySQL or MaxScale
DB_PORT=3306
DB_POOL_SIZE=10            # Reuse connections
```

**Applied in QUDRIX**: Set in `.env` for production. No code change needed.

---

## 2. Caching Strategies

### 2.1 Query Result Caching

**Problem**: Running the same query repeatedly (e.g., getting all subscription plans).

**Solution**: Cache the result for a time:

```php
// ✅ GOOD: cache for 1 hour
$plans = Cache::remember('subscription_plans', 3600, function () {
    return SubscriptionPlan::where('is_active', true)
        ->orderBy('display_order')
        ->get();
});

// Invalidate on update:
public function store(Request $request)
{
    $plan = SubscriptionPlan::create($validated);
    Cache::forget('subscription_plans');  // Clear cache
    return response()->json(['data' => $plan], 201);
}
```

**Implement in QUDRIX Controllers**:
```php
// app/Http/Controllers/SubscriptionPlanController.php
public function index(Request $request)
{
    $cacheKey = 'tenant_' . $request->user->tenant_id . '_subscription_plans';
    $plans = Cache::remember($cacheKey, 3600, function () use ($request) {
        return SubscriptionPlan::where('tenant_id', $request->user->tenant_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    });
    return response()->json(['data' => $plans]);
}

public function store(Request $request)
{
    $plan = SubscriptionPlan::create($validated);
    Cache::forget('tenant_' . $request->user->tenant_id . '_subscription_plans');
    return response()->json(['data' => $plan], 201);
}
```

### 2.2 Session Caching (Redis)

**Problem**: Sessions stored in files = slow disk I/O.

**Solution**: Use Redis (in-memory):

```bash
# .env
SESSION_DRIVER=redis
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

**Applied in QUDRIX**: Already configured in `config/session.php` and `config/cache.php`.

### 2.3 API Response Caching (HTTP Cache Headers)

**Problem**: Frontend re-requests unchanged data (e.g., list of flights).

**Solution**: Add HTTP cache headers:

```php
// app/Http/Controllers/FlightController.php
public function index(Request $request)
{
    $flights = Flight::where('tenant_id', $request->user->tenant_id)->get();
    
    return response()->json(['data' => $flights])
        ->header('Cache-Control', 'public, max-age=3600')  // 1 hour
        ->header('ETag', md5(json_encode($flights)));
}
```

**Frontend** (Axios interceptor):
```typescript
// src/lib/api.ts
api.interceptors.response.use((response) => {
  const cacheKey = response.config.url;
  localStorage.setItem(cacheKey, JSON.stringify(response.data));
  return response;
}, (error) => {
  const cacheKey = error.config?.url;
  const cached = localStorage.getItem(cacheKey);
  if (cached && error.response?.status === 304) {
    return Promise.resolve({ data: JSON.parse(cached) });
  }
  return Promise.reject(error);
});
```

### 2.4 Cache Invalidation Strategy

**Problem**: Stale cache data after updates.

**Solution**: Tag-based cache invalidation:

```php
// Cache with tags
Cache::tags(['subscription_plans', 'tenant_' . $tenantId])
    ->remember('subscription_plans', 3600, function () {
        return SubscriptionPlan::get();
    });

// Invalidate all subscription plan caches
Cache::tags(['subscription_plans'])->flush();

// Invalidate caches for specific tenant
Cache::tags(['tenant_' . $tenantId])->flush();
```

**Implement in QUDRIX**:
```php
// app/Traits/CacheInvalidationTrait.php
trait CacheInvalidationTrait
{
    protected static function booted()
    {
        static::created(function ($model) {
            Cache::tags([class_basename($model), 'tenant_' . $model->tenant_id])->flush();
        });
        static::updated(function ($model) {
            Cache::tags([class_basename($model), 'tenant_' . $model->tenant_id])->flush();
        });
        static::deleted(function ($model) {
            Cache::tags([class_basename($model), 'tenant_' . $model->tenant_id])->flush();
        });
    }
}

// Use in models:
class SubscriptionPlan extends Model
{
    use CacheInvalidationTrait;
}
```

---

## 3. Frontend Performance

### 3.1 Code Splitting (Lazy Loading)

**Problem**: Loading entire React bundle (~150 KB) even if user never visits automation page.

**Solution**: Lazy load route components:

```typescript
// src/App.tsx
import { lazy, Suspense } from 'react'

const Dashboard = lazy(() => import('./pages/DashboardPage'))
const Bookings = lazy(() => import('./pages/bookings/BookingListPage'))
const Automation = lazy(() => import('./pages/automation/AutomationPage'))

export function App() {
  return (
    <Suspense fallback={<Spinner />}>
      <Routes>
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/bookings" element={<Bookings />} />
        <Route path="/automation" element={<Automation />} />
      </Routes>
    </Suspense>
  )
}
```

### 3.2 Image Optimization

**Problem**: Loading 2 MB PNG image in list = slow rendering.

**Solution**: Compress, use WebP with fallback:

```typescript
// src/components/TenantLogo.tsx
export function TenantLogo({ src, alt }: { src: string; alt: string }) {
  return (
    <picture>
      <source srcSet={`${src}?fmt=webp`} type="image/webp" />
      <img src={`${src}?w=200&q=80`} alt={alt} loading="lazy" />
    </picture>
  )
}
```

### 3.3 Memoization (React.memo)

**Problem**: Expensive components re-render unnecessarily.

**Solution**: Memoize components:

```typescript
// src/components/BookingCard.tsx
const BookingCard = React.memo(({ booking }: { booking: Booking }) => {
  return <div>{booking.reference_number}</div>
}, (prevProps, nextProps) => {
  // Re-render only if booking ID changed
  return prevProps.booking.id === nextProps.booking.id
})
```

### 3.4 Virtual Scrolling for Large Lists

**Problem**: Rendering 1000 rows = 1000 DOM nodes = slow.

**Solution**: Virtual scrolling (only visible rows rendered):

```typescript
// Use react-window library
import { FixedSizeList } from 'react-window'

export function BookingListVirtual({ bookings }: { bookings: Booking[] }) {
  return (
    <FixedSizeList height={600} itemCount={bookings.length} itemSize={50} width="100%">
      {({ index, style }) => (
        <div style={style}>
          {bookings[index].reference_number}
        </div>
      )}
    </FixedSizeList>
  )
}
```

---

## 4. Backend Performance Tuning

### 4.1 Optimize API Response Size

**Problem**: API returns 50 KB JSON when only 5 KB needed.

**Solution**: Use field selection:

```php
// ✅ GOOD: minimal response
Route::get('/bookings', function (Request $request) {
    return Booking::where('tenant_id', $request->user->tenant_id)
        ->select('id', 'reference_number', 'customer_id', 'status', 'total_amount')
        ->with('customer:id,name,phone')  // Only specific fields
        ->paginate(25);
});
```

### 4.2 Batch Processing (Chunking)

**Problem**: Processing 100K records one-by-one = slow.

**Solution**: Process in chunks:

```php
// ✅ GOOD: process 1000 at a time
Booking::where('tenant_id', $tenantId)
    ->chunk(1000, function ($bookings) {
        foreach ($bookings as $booking) {
            // Process each booking
            $booking->update(['processed' => true]);
        }
    });
```

### 4.3 Defer Non-Critical Queries

**Problem**: API waits for all queries before responding.

**Solution**: Queue non-critical work:

```php
// app/Http/Controllers/BookingController.php
public function store(Request $request)
{
    $booking = Booking::create($validated);
    
    // Send email asynchronously
    SendBookingConfirmation::dispatch($booking);
    
    // Send webhook asynchronously
    TriggerWebhook::dispatch('booking.created', $booking);
    
    return response()->json(['data' => $booking], 201);
}
```

### 4.4 Database Query Profiling

**Problem**: Don't know which queries are slow.

**Solution**: Enable query logging in development:

```php
// config/database.php or .env
DB_LOG_QUERIES=true  // Log slow queries (>1s)

// Or in Laravel debugbar
// composer require --dev barryvdh/laravel-debugbar
// Then check the "Queries" tab in debugbar
```

---

## 5. Caching Checklist

| What to Cache | TTL | Invalidation |
|---------------|-----|--------------|
| Subscription plans | 1 hour | On plan create/update |
| AI provider list | 1 hour | On provider create/update |
| User roles/permissions | 24 hours | On role update |
| Tenant settings | 24 hours | On setting update |
| Public flights (inventory) | 15 mins | On inventory sync |
| Customer list (by tenant) | 5 mins | On customer create/update |
| API usage stats | 1 hour | Real-time counter |
| Conversation history | None | Real-time chat |

---

## 6. Load Testing

### Using Apache Bench (ab)
```bash
# Simple: 100 requests, 10 concurrent
ab -n 100 -c 10 http://localhost:8000/api/bookings

# Result: look for "Requests per second" (higher is better)
# Target: > 100 req/s for shared hosting
```

### Using wrk (more realistic)
```bash
# 100 concurrent, 30 second test, 4 threads
wrk -t4 -c100 -d30s http://localhost:8000/api/bookings

# Result: look for "Req/Sec" and "Latency"
# Target: < 200 ms average latency
```

---

## 7. Production Deployment Checklist

- [ ] Enable Redis for caching and sessions
- [ ] Enable HTTP query logging, set threshold (e.g., 1000ms)
- [ ] Add composite indexes for all filtered queries
- [ ] Implement cache invalidation for frequently updated data
- [ ] Configure PHP-FPM with appropriate pool size
- [ ] Enable Gzip compression for API responses
- [ ] Set appropriate pagination defaults (25-50 items/page)
- [ ] Implement rate limiting (100 requests/minute per IP)
- [ ] Enable database connection pooling
- [ ] Monitor slow query log, optimize as needed
- [ ] Use CDN for static assets (FRONTEND/dist)
- [ ] Enable browser caching (Cache-Control headers)

---

## 8. Monitoring Tools

| Tool | Purpose | Cost |
|------|---------|------|
| **Laravel Horizon** | Monitor queues | Free (comes with Laravel) |
| **Sentry** | Error tracking | Free tier available |
| **Datadog** | APM (performance monitoring) | $15/month |
| **New Relic** | Full-stack monitoring | Free tier available |
| **Cloudflare** | CDN + caching | Free tier available |

---

**Last Updated**: 2026-10-06
**Status**: All optimizations code-ready, none runtime-tested (no benchmark environment)
