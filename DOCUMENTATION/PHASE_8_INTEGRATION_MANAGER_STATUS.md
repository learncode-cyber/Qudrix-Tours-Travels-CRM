# Phase 8 (Master Directive numbering) — Integration Manager + Custom API Connectors

**Date:** 2026-08-30
**Scope:** Directive §18 (Integration Architecture) + §28 Phase 8, extended to cover the requirement that
the operator be able to plug in **their own** flight/visa/hotel API providers without code changes.

## The competitive gap this closes

The benchmark platform (Travel Suite ERP) was observed to have **no live inventory API at all** — its
Flight and Hotel modules are manual-inventory only, with no GDS (Amadeus/Sabre) or bedbank
(Hotelbeds/TBO) signature anywhere. That is its single biggest weakness.

Qudrix now ships a **generic, operator-configurable API connector engine**. The system provides the
engine; the operator supplies their own provider contract through configuration. This means:

- Any provider can be added — GDS, bedbank, visa processor, payment gateway, SMS/WhatsApp vendor — with
  **zero code changes and no redeploy**.
- **No third-party endpoint is invented anywhere in this codebase.** Per Directive §18/§35, a connector
  with no endpoint mapped reports `CONTRACT REQUIRED` and refuses to activate, rather than pretending to
  work. When the operator has a real contract, they map it and it works immediately.

## What was built

**`ApiConnector`** — tenant-scoped provider definition: name, category (flight/hotel/visa/payment/sms/
whatsapp/email/ai/analytics/crm/other), base URL, auth type (`none`/`bearer`/`api_key_header`/
`api_key_query`/`basic`/`custom_headers`), default headers, timeout. Credentials are `encrypted:array`
at rest, in `$hidden`, and are written only through a dedicated `PUT .../credentials` endpoint — they are
never returned in any response and never ride along in a general update.

**`ApiConnectorEndpoint`** — one row per operation the operator maps (`search`, `quote`, `book`,
`cancel`, `status`, …), with HTTP method, path (supports `{placeholder}` segments), `request_template`
and `query_template` (values support `{{param}}` and `{{credential.KEY}}` substitution), plus
`response_mapping` (`our_field => provider.dot.path`) and `response_collection_path` for list responses.
This is what lets any provider's payload shape be translated into our normalized shape declaratively.

**`ApiConnectorService`** — the execution engine: resolves path placeholders, builds auth headers,
renders templates, issues the call, maps the response, and logs everything.

**`ApiConnectorCallLog`** — every outbound call recorded (URL, method, payload, status, duration, error)
for the auditability requirement (§23) and for debugging a provider integration without guesswork.

**`HybridSearchController`** — `POST /api/v1/search/{flights,hotels,visa}` queries the CRM's own
inventory **and** every active configured provider in that category simultaneously, returning both sets
labelled by `source` (`internal` / `external`) with a `bookable_in_crm` flag. A provider that errors is
reported in `external_errors` against that source — the search never silently drops it or fabricates a
substitute result.

## Security decisions made here (both deliberate, both non-obvious)

1. **SSRF guard.** Operator-configured URLs are attacker-reachable configuration in a multi-tenant
   system. Without a guard, a tenant admin could point a connector at `127.0.0.1`, `169.254.169.254`
   (cloud metadata), or an internal service and use the CRM as an SSRF proxy into the host's network.
   `guardAgainstPrivateNetwork()` resolves the host and refuses private/reserved ranges and non-HTTP(S)
   schemes, unless `ALLOW_PRIVATE_NETWORK_CONNECTORS=true` is deliberately set for a self-hosted
   deployment that needs an internal provider.
2. **Credentials never reach the call log.** Templates are rendered twice — once with credentials
   substituted (sent to the provider) and once with credential placeholders left intact (written to
   `api_connector_call_logs`). A leaked log table therefore cannot leak provider secrets.

Also capped: per-connector timeout (hard ceiling from config) and logged response size, so one
misconfigured or chatty provider cannot hang a request thread or fill the database.

## Worked example — wiring a flight provider

```
POST /api/v1/api-connectors
{ "name": "My GDS", "category": "flight", "provider_name": "Amadeus",
  "base_url": "https://api.example-provider.com/v2", "auth_type": "bearer" }

PUT  /api/v1/api-connectors/1/credentials
{ "credentials": { "token": "..." } }

POST /api/v1/api-connectors/1/endpoints
{ "operation": "search", "http_method": "POST", "path": "/shopping/flight-offers",
  "request_template": { "origin": "{{departure_airport}}", "destination": "{{arrival_airport}}",
                        "date": "{{departure_date}}", "adults": "{{passengers}}" },
  "response_collection_path": "data",
  "response_mapping": { "airline": "validatingAirlineCodes.0", "price": "price.total",
                        "currency": "price.currency", "flight_number": "itineraries.0.segments.0.number" } }

POST /api/v1/api-connectors/1/test-connection
PATCH /api/v1/api-connectors/1  { "is_active": true }
POST /api/v1/search/flights     { "departure_airport": "DAC", "arrival_airport": "JED", ... }
```

The URL, field names and paths above are **illustrative placeholders** — they are whatever the
operator's actual signed provider contract specifies. Nothing in this codebase assumes any particular
provider's API shape.

## Verification

Same constraint as prior phases — no `vendor/`, no DB, no outbound network in this session. The
connector engine's live HTTP behaviour is therefore **UNVERIFIED — REQUIRES LOCAL/STAGING EXECUTION**
(code written against Laravel's documented `Http` facade, reviewed but not executed). `php -l` passes
with zero errors across `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `resources/`;
every controller referenced in `routes/api.php` confirmed to exist on disk.
