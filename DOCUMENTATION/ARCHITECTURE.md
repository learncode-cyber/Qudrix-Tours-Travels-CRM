# QUDRIX CRM - Phase 0 Architecture

## System Architecture Overview

### Technology Stack
- **Framework:** Laravel 11.x
- **Language:** PHP 8.2+
- **Database:** MySQL 8.0+ / MariaDB 10.5+
- **Authentication:** JWT (tymon/jwt-auth)
- **Authorization:** RBAC (Role-Based Access Control)
- **Hosting:** Hostinger Business Shared Hosting

### Architectural Principles

#### 1. Multi-Tenant Architecture
Every data entity includes `tenant_id` field ensuring complete data isolation between tenants.

```
Request → JWT Verification → Tenant Context → Query Scoping (WHERE tenant_id = X)
```

#### 2. Layered Architecture

```
┌─────────────────────────────────┐
│      HTTP Requests (API)        │
├─────────────────────────────────┤
│  Middleware Layer (Auth, RBAC)  │
├─────────────────────────────────┤
│     Controller Layer            │
├─────────────────────────────────┤
│     Service Layer               │
├─────────────────────────────────┤
│     Model Layer (Eloquent)      │
├─────────────────────────────────┤
│     Database Layer              │
└─────────────────────────────────┘
```

#### 3. Security Layers

1. **JWT Authentication** - Token validation on every protected request
2. **RBAC Authorization** - Role-based permission checking
3. **Tenant Isolation** - Automatic data scoping by tenant_id
4. **Audit Logging** - All modifications tracked and logged
5. **Input Validation** - Server-side validation via FormRequest classes
6. **SQL Injection Prevention** - Parameterized queries via Eloquent ORM

### Core Components

#### Models (9 Base Models)
- **Tenant** - Multi-tenant container
- **User** - System users with JWT support
- **Role** - Permission container with flexible permissions
- **Branch** - Physical or logical business locations
- **Customer** - Primary contact entity
- **Lead** - Prospect management
- **Package** - Travel package definitions
- **Booking** - Travel booking with multi-traveler support
- **Payment** - Payment tracking

#### Middleware Stack
1. **JwtAuth** - JWT token validation and user authentication
2. **TenantMiddleware** - Tenant context isolation
3. **RBACMiddleware** - Permission verification
4. **AuditMiddleware** - Action logging

#### Services
- **AuthService** - Registration, login, role seeding
- **AuditService** - Audit log management

### Database Design

#### Key Design Patterns
- **Soft Deletes** - Logical deletion with recovery capability
- **Timestamps** - Automatic created_at, updated_at tracking
- **Foreign Keys** - Referential integrity with cascade rules
- **Indexes** - Performance optimization on common queries
- **UTF8MB4** - Full Unicode support

#### Table Relationships

```
Tenant (1) ──┬─ Users (M)
             ├─ Roles (M)
             ├─ Branches (M)
             ├─ Customers (M)
             ├─ Leads (M)
             ├─ Packages (M)
             ├─ Bookings (M)
             ├─ Payments (M)
             └─ AuditLogs (M)

User (1) ──┬─ Roles (M) [pivot: role_user]
           ├─ Tasks (M)
           ├─ Communications (M)
           └─ AuditLogs (M)

Customer (1) ──┬─ Bookings (M)
               └─ Communications (M)

Booking (1) ──┬─ BookingTravelers (M)
              ├─ Payments (M)
              └─ Invoices (M)
```

### API Architecture

#### Request Flow
```
Client → API Endpoint
  ↓
1. Route Matching (routes/api.php)
  ↓
2. Middleware Chain Execution
   - JWT Validation (JwtAuth)
   - Tenant Scoping (TenantMiddleware)
   - Permission Check (RBACMiddleware)
   - Audit Logging (AuditMiddleware)
  ↓
3. Controller Processing
   - Input Validation
   - Service Call
   - Response Formatting
  ↓
4. Database Interaction (via Models)
   - Automatic tenant_id injection
   - Lazy/Eager loading
   - Relationship resolution
  ↓
5. Response Return
   - JSON Formatting
   - Status Code
   - Audit Logging
  ↓
Client receives response
```

### Configuration Files

#### key Laravel Configuration Files
- `config/app.php` - Application name, timezone, providers
- `config/database.php` - Database connection settings
- `config/jwt.php` - JWT expiration, algorithms
- `.env` - Environment variables (not in repo)

### Testing Strategy

- **Feature Tests** - HTTP requests, authentication, authorization
- **Unit Tests** - Model methods, service logic, utilities
- **Database Tests** - Transaction handling, data integrity

---

## Phase 0 Completion

✅ Database schema with 39 tables  
✅ Core models and relationships  
✅ Authentication system (JWT)  
✅ RBAC authorization  
✅ Audit logging  
✅ API routes and controllers  
✅ Middleware stack  
✅ Unit and feature tests  

---

## Future Phases

**Phase 1:** Customer 360, Lead Management, Communication  
**Phase 2:** Sales Pipeline, Quotations, Packages  
**Phase 3:** Booking Engine, Travelers, Groups  
**Phases 4-10:** Specialized modules, automation, AI integration

---

## Addendum: Frontend tier (Master Directive Phase 2)

The numbering above predates the current Master Development Directive
and doesn't match its phase numbers — see `PROJECT_STATUS.md` for the
directive's actual phase tracking. This addendum only documents an
architectural addition: a frontend tier now exists.

```
┌─────────────────────────────────┐
│   React + TypeScript SPA        │  /frontend
│   (Vite build, static hosting)  │
├─────────────────────────────────┤
│   Axios client (JWT bearer)     │  localStorage token,
│                                  │  Authorization header per request
└──────────────┬───────────────────┘
               │  HTTPS, CORS-restricted
               ▼
┌─────────────────────────────────┐
│   Laravel API (unchanged)       │  /PROJECT — everything above
└─────────────────────────────────┘  this addendum still applies
```

The frontend is a separate deployable (static files after `npm run
build`) and talks to the API purely over HTTP, same as any third-party
client would — no server-side rendering, no shared process, no direct
database access. `config/cors.php`'s `CORS_ALLOWED_ORIGINS` must list
the frontend's real origin or every request is rejected by design (see
`DOCUMENTATION/PHASE_2_REPORT.md` §8 for the bug this caused when it
was undocumented).
