# NT Nexa — Lead Management REST API

A small, production-style **Lead Management REST API** built with **Laravel 13** and **Laravel Sanctum**.

It provides:

- Token authentication (register, login, logout, current user) with Sanctum
- Full CRUD for leads (`name`, `phone`, `email`, `source`, `status`) with pagination and filtering
- A signed **webhook** for external lead sources that stores the lead and triggers a **mock WhatsApp/SMS notification**
- Form Request validation, API Resources, a consistent JSON response envelope and centralised error handling
- Feature tests, a Postman collection and a short design note ([DESIGN.md](DESIGN.md))

---

## Table of contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Authentication](#authentication)
4. [Response format](#response-format)
5. [API documentation](#api-documentation)
6. [Webhook](#webhook)
7. [Testing](#testing)
8. [Postman](#postman)
9. [Project structure](#project-structure)
10. [Design decisions](#design-decisions)
11. [Future improvements](#future-improvements)

---

## Requirements

| Tool      | Version                                      |
|-----------|----------------------------------------------|
| PHP       | 8.3 or newer (extensions: `pdo_sqlite` or `pdo_mysql`, `mbstring`, `openssl`) |
| Composer  | 2.x                                          |
| Database  | SQLite 3 (default, zero config) **or** MySQL 8 / MariaDB 10.6+ |
| Laravel   | 13.x (installed via Composer)                |

---

## Installation

```bash
git clone https://github.com/<your-username>/ntnexa-lead-api.git
cd ntnexa-lead-api

composer install
cp .env.example .env          # Windows (cmd): copy .env.example .env
php artisan key:generate
```

### Database setup

**Option A — SQLite (default, recommended for review)**

```bash
# macOS / Linux / Git Bash
touch database/database.sqlite
# Windows PowerShell
New-Item database/database.sqlite -ItemType File
```

`.env.example` already contains `DB_CONNECTION=sqlite`, nothing else to change.

**Option B — MySQL**

Create an empty database (e.g. `ntnexa_leads`) and update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ntnexa_leads
DB_USERNAME=root
DB_PASSWORD=
```

### Migrate, seed (optional) and serve

```bash
php artisan migrate
php artisan db:seed           # optional: demo user + 25 sample leads
php artisan serve
```

The API is now available at **http://127.0.0.1:8000/api**.

Seeded demo account (only if you ran `db:seed`): `demo@example.com` / `password`.

### Environment variables specific to this project

| Variable              | Purpose                                                              |
|-----------------------|----------------------------------------------------------------------|
| `LEAD_WEBHOOK_SECRET` | Shared secret used to verify the webhook HMAC signature. `.env.example` ships a local demo value (`local-dev-webhook-secret`) that matches the Postman collection. **Use a long random value in production** (`php -r "echo bin2hex(random_bytes(32));"`). |

---

## Authentication

Authentication uses **Laravel Sanctum personal access tokens**.

1. `POST /api/register` or `POST /api/login` → the response contains `data.token`.
2. Send the token on every protected request:

   ```http
   Authorization: Bearer 1|q8vN0p...
   Accept: application/json
   ```

3. `POST /api/logout` revokes the token used for that request.

Passwords are hashed with Laravel's default hasher (bcrypt) via the `hashed` model cast; password hashes and remember tokens are never returned (responses go through `UserResource`). Login and register are rate limited to 10 requests/minute per IP.

---

## Response format

Every endpoint returns the same envelope.

**Success**

```json
{
  "success": true,
  "message": "Lead created successfully.",
  "data": { "...": "..." }
}
```

List endpoints add a `meta` object with pagination information.

**Error**

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "status": ["The status must be one of: new, contacted, qualified, closed."]
  }
}
```

`errors` is only present for validation failures (422).

| Status | Meaning                                                   |
|--------|-----------------------------------------------------------|
| 200    | OK                                                        |
| 201    | Resource created                                          |
| 401    | Missing/invalid token, invalid credentials, or missing webhook signature |
| 403    | Invalid webhook signature                                 |
| 404    | Lead / route not found                                    |
| 405    | HTTP method not allowed                                   |
| 422    | Validation failed                                         |
| 429    | Too many requests (rate limit)                            |
| 500    | Unexpected server error (details hidden when `APP_DEBUG=false`) |
| 503    | Webhook secret not configured on the server               |

> Delete returns **200** with a JSON message (instead of an empty 204) so every response follows the same envelope.

---

## API documentation

Base URL: `http://127.0.0.1:8000/api`
All requests should send `Accept: application/json` (the API also forces JSON responses if it is omitted).

### Endpoint summary

| Method       | URL                     | Auth              | Description                  |
|--------------|-------------------------|-------------------|------------------------------|
| POST         | `/register`             | —                 | Register and get a token     |
| POST         | `/login`                | —                 | Login and get a token        |
| POST         | `/logout`               | Bearer            | Revoke current token         |
| GET          | `/user`                 | Bearer            | Current user                 |
| GET          | `/leads`                | Bearer            | List leads (paginated, filterable) |
| POST         | `/leads`                | Bearer            | Create lead                  |
| GET          | `/leads/{id}`           | Bearer            | Show lead                    |
| PUT / PATCH  | `/leads/{id}`           | Bearer            | Update lead                  |
| DELETE       | `/leads/{id}`           | Bearer            | Delete lead                  |
| POST         | `/webhooks/leads`       | HMAC signature    | Receive lead from external system |

### Lead fields & validation

| Field    | Rules                                                                 |
|----------|-----------------------------------------------------------------------|
| `name`   | required, string, max 150                                             |
| `phone`  | required, string, max 20, phone format (digits, optional leading `+`, spaces, `-`, `()`), e.g. `+923001234567` |
| `email`  | optional, valid email, max 255                                        |
| `source` | required, string, max 50 (e.g. `website`, `facebook`, `referral`)    |
| `status` | required, one of `new`, `contacted`, `qualified`, `closed`            |

---

### POST `/api/register`

**Auth:** none

```json
{
  "name": "Ali Khan",
  "email": "ali@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**201 Created**

```json
{
  "success": true,
  "message": "User registered successfully.",
  "data": {
    "user": { "id": 1, "name": "Ali Khan", "email": "ali@example.com", "created_at": "2026-09-28T10:00:00+00:00" },
    "token": "1|q8vN0pX...",
    "token_type": "Bearer"
  }
}
```

**Status codes:** 201, 422 (e.g. email already taken, password too short / not confirmed), 429

---

### POST `/api/login`

**Auth:** none

```json
{
  "email": "ali@example.com",
  "password": "password123",
  "device_name": "postman"
}
```

`device_name` is optional (used as the token name).

**200 OK** — same `data` shape as register, message `"Login successful."`

**401 Unauthorized**

```json
{ "success": false, "message": "Invalid credentials." }
```

**Status codes:** 200, 401, 422, 429

---

### POST `/api/logout`

**Auth:** Bearer token — **Body:** none

**200 OK**

```json
{ "success": true, "message": "Logged out successfully.", "data": null }
```

**Status codes:** 200, 401

---

### GET `/api/user`

**Auth:** Bearer token

**200 OK**

```json
{
  "success": true,
  "message": "Authenticated user retrieved.",
  "data": { "id": 1, "name": "Ali Khan", "email": "ali@example.com", "created_at": "2026-09-28T10:00:00+00:00" }
}
```

**401 Unauthorized**

```json
{ "success": false, "message": "Unauthenticated." }
```

---

### GET `/api/leads`

**Auth:** Bearer token

| Query param | Description                                         |
|-------------|-----------------------------------------------------|
| `status`    | optional — `new`, `contacted`, `qualified`, `closed` |
| `source`    | optional — exact match, e.g. `website`              |
| `per_page`  | optional — 1–100, default 15                         |
| `page`      | optional — page number                               |

Examples: `/api/leads?status=new`, `/api/leads?source=website`, `/api/leads?status=new&source=website&per_page=10`

Results are ordered newest first.

**200 OK**

```json
{
  "success": true,
  "message": "Leads retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Ali Khan",
      "phone": "+923001234567",
      "email": "ali@example.com",
      "source": "website",
      "status": "new",
      "created_at": "2026-09-28T10:00:00+00:00",
      "updated_at": "2026-09-28T10:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1,
    "from": 1,
    "to": 1,
    "next_page_url": null,
    "prev_page_url": null
  }
}
```

**Status codes:** 200, 401, 422 (invalid `status` filter or `per_page`)

---

### POST `/api/leads`

**Auth:** Bearer token

```json
{
  "name": "Ali Khan",
  "phone": "+923001234567",
  "email": "ali@example.com",
  "source": "website",
  "status": "new"
}
```

**201 Created**

```json
{
  "success": true,
  "message": "Lead created successfully.",
  "data": {
    "id": 1,
    "name": "Ali Khan",
    "phone": "+923001234567",
    "email": "ali@example.com",
    "source": "website",
    "status": "new",
    "created_at": "2026-09-28T10:00:00+00:00",
    "updated_at": "2026-09-28T10:00:00+00:00"
  }
}
```

**422 Unprocessable Entity**

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "phone": ["The phone field is required."],
    "status": ["The status must be one of: new, contacted, qualified, closed."]
  }
}
```

**Status codes:** 201, 401, 422

---

### GET `/api/leads/{id}`

**Auth:** Bearer token

**200 OK** — `{ "success": true, "message": "Lead retrieved successfully.", "data": { ...lead } }`

**404 Not Found**

```json
{ "success": false, "message": "Lead not found." }
```

**Status codes:** 200, 401, 404

---

### PUT / PATCH `/api/leads/{id}`

**Auth:** Bearer token

Any subset of `name`, `phone`, `email`, `source`, `status` may be sent; only the provided fields are changed. Provided fields must be valid (e.g. `status` must be an allowed value, `name` cannot be empty).

```json
{ "status": "contacted" }
```

**200 OK** — `{ "success": true, "message": "Lead updated successfully.", "data": { ...updated lead } }`

**Status codes:** 200, 401, 404, 422

---

### DELETE `/api/leads/{id}`

**Auth:** Bearer token

**200 OK**

```json
{ "success": true, "message": "Lead deleted successfully.", "data": null }
```

**Status codes:** 200, 401, 404

---

## Webhook

### POST `/api/webhooks/leads`

Endpoint for **external systems** (website forms, ad platforms, CRMs) to push new leads. It does **not** use a user token — instead each request is signed with a shared secret.

**Headers**

```http
Content-Type: application/json
Accept: application/json
X-Webhook-Signature: sha256=<hex HMAC-SHA256 of the raw request body using LEAD_WEBHOOK_SECRET>
```

**Body**

```json
{
  "name": "Sara Ahmed",
  "phone": "+923331112233",
  "email": "sara@example.com",
  "source": "facebook_ads"
}
```

- `name`, `phone` — required; `email` — optional; `source` — optional (defaults to `webhook`).
- `status` is not accepted from the caller: every webhook lead is created as `new`.

**201 Created**

```json
{
  "success": true,
  "message": "Lead received successfully.",
  "data": { "id": 26, "name": "Sara Ahmed", "phone": "+923331112233", "email": "sara@example.com", "source": "facebook_ads", "status": "new", "created_at": "...", "updated_at": "..." }
}
```

**Status codes:** 201, 401 (missing signature), 403 (invalid signature), 422 (invalid payload), 429, 503 (secret not configured)

### Signing a request (example)

```bash
BODY='{"name":"Sara Ahmed","phone":"+923331112233","source":"facebook_ads"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "local-dev-webhook-secret" | sed 's/^.* //')

curl -X POST http://127.0.0.1:8000/api/webhooks/leads \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Webhook-Signature: sha256=$SIG" \
  -d "$BODY"
```

The Postman request computes the signature automatically in a pre-request script.

### What happens on a new lead

1. `VerifyWebhookSignature` middleware checks the HMAC (constant-time `hash_equals`).
2. `WebhookLeadRequest` validates the payload.
3. `LeadIntakeService` stores the lead (status `new`).
4. It calls the `LeadNotifier` interface → the bound `MockLeadNotifier` writes to `storage/logs/laravel.log`:

   ```
   local.INFO: Mock WhatsApp/SMS notification sent to +923331112233 for lead Sara Ahmed. {"lead_id":26,"channel":"whatsapp/sms (mock)","message":"Hi Sara Ahmed, thanks for your interest! ..."}
   ```

5. If the notifier throws, the error is logged but the lead is kept and the webhook still returns 201 — so the caller does not retry and create duplicates.

No real or paid messaging service is called.

### Replacing the mock with a real provider

1. Create a class implementing `App\Contracts\LeadNotifier`, e.g. `TwilioSmsNotifier` or `WhatsAppCloudNotifier`, that calls the provider's API (credentials in `config/services.php` / `.env`).
2. Change the binding in `App\Providers\AppServiceProvider`:

   ```php
   $this->app->bind(LeadNotifier::class, TwilioSmsNotifier::class);
   ```

No controller or service code changes are required.

### Securing the webhook in production

- **HMAC signature** (implemented): only callers that know `LEAD_WEBHOOK_SECRET` can create leads; keep the secret long, random and out of version control, and rotate it periodically.
- **HTTPS only**, so the payload and signature cannot be intercepted.
- **Replay protection**: include a timestamp (e.g. `X-Webhook-Timestamp`) in the signed content and reject old requests, and/or require an idempotency key / event id stored with the lead.
- **Rate limiting** (implemented: 60 requests/minute) and optionally an IP allow-list for known providers.
- **Queue** the notification so slow providers don't delay the webhook response.

---

## Testing

Tests use an in-memory SQLite database (configured in `phpunit.xml`) with `RefreshDatabase`, so they do not touch your local database.

```bash
php artisan test
```

Covered scenarios:

| Area            | Tests |
|-----------------|-------|
| Authentication  | register, register validation, login, invalid login, login validation, current user, logout revokes token, protected routes without / with invalid token, JSON errors without `Accept` header |
| Leads           | create, required-field validation, invalid email/phone, every valid status, invalid statuses, list + pagination, filter by status/source, invalid filter, show, 404 for missing ID (show/update/delete), PUT update, PATCH partial update, update validation, delete |
| Webhook         | creates lead + notification triggered (mocked), mock notifier logs message, status forced to `new` / default source, payload validation (no notification), missing signature, invalid signature, missing secret, notification failure keeps the lead |

---

## Postman

1. Open Postman → **Import** → select `postman/NT-Nexa-Lead-API.postman_collection.json`.
2. Check the collection **Variables**:
   - `base_url` — `http://127.0.0.1:8000/api`
   - `token` — filled automatically
   - `lead_id` — filled automatically by *Create Lead*
   - `webhook_secret` — must equal `LEAD_WEBHOOK_SECRET` in `.env` (default `local-dev-webhook-secret`)
3. Run **Authentication → Register** (or **Login**). The token is saved to `{{token}}` by a test script, and all Lead requests use it through collection-level Bearer auth.
4. Run the **Leads** requests; *Create Lead* stores the new id for Get / Update / Delete.
5. Run **Webhook → New Lead Webhook** — the signature header is generated automatically. Check `storage/logs/laravel.log` for the mock notification.

---

## Project structure

```
app/
├── Contracts/LeadNotifier.php                 # Notification abstraction
├── Enums/LeadStatus.php                       # new | contacted | qualified | closed
├── Exceptions/ApiExceptionRenderer.php        # Exceptions → JSON error envelope
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── LeadController.php
│   │   └── LeadWebhookController.php
│   ├── Middleware/
│   │   ├── ForceJsonResponse.php
│   │   └── VerifyWebhookSignature.php
│   ├── Requests/
│   │   ├── Auth/{RegisterRequest, LoginRequest}.php
│   │   ├── Lead/{StoreLeadRequest, UpdateLeadRequest, ListLeadsRequest, LeadRules}.php
│   │   └── WebhookLeadRequest.php
│   └── Resources/{LeadResource, UserResource}.php
├── Models/{Lead, User}.php
├── Providers/AppServiceProvider.php           # Binds LeadNotifier → MockLeadNotifier
├── Services/
│   ├── LeadIntakeService.php                  # Store webhook lead + notify
│   └── Notifications/MockLeadNotifier.php     # Logs instead of sending
└── Support/ApiResponse.php                    # Success/error JSON envelope
bootstrap/app.php                              # API routes, middleware, exception rendering
database/
├── factories/LeadFactory.php
├── migrations/…_create_personal_access_tokens_table.php
├── migrations/…_create_leads_table.php
└── seeders/DatabaseSeeder.php
routes/api.php
tests/Feature/{Auth/AuthenticationTest, Lead/LeadApiTest, Lead/LeadWebhookTest}.php
postman/NT-Nexa-Lead-API.postman_collection.json
DESIGN.md
```

---

## Design decisions

- **Sanctum tokens** — lightweight, first-party, database-backed tokens that can be revoked per device; OAuth2 (Passport) would be overkill for this scope.
- **`LeadStatus` backed enum** — one source of truth used by the migration, the Eloquent cast (`$lead->status` is an enum, not a magic string) and validation (`Rule::enum`). The column is also a DB `enum` (native ENUM on MySQL, CHECK constraint on SQLite) as a second line of defence. Adding a status later means adding an enum case plus a small migration.
- **Indexes** on `status`, `source`, `email` and `created_at` support the filters and the default newest-first ordering.
- **Thin controllers** — validation lives in Form Requests (rules shared via `LeadRules`), serialisation in API Resources, the webhook workflow in `LeadIntakeService`, and responses/errors in `ApiResponse` + `ApiExceptionRenderer`.
- **Consistent errors** — one exception renderer in `bootstrap/app.php` handles validation, auth, 404 (with the model name), HTTP and unexpected errors for all `api/*` routes; `ForceJsonResponse` guarantees JSON even without an `Accept` header.
- **Webhook security** — HMAC-SHA256 signature of the raw body verified with `hash_equals`, failing closed if the secret is not configured.
- **Notification abstraction** — the webhook depends on the `LeadNotifier` interface; the mock implementation logs the message. A notifier failure never loses the stored lead.
- **Shared leads** — leads are not scoped per user: any authenticated user (e.g. a sales team member) can manage all leads, as the brief did not require ownership.

---

## Future improvements

- Queue the notification (`ShouldQueue` job/listener with retries & backoff) and store a delivery status per lead.
- Webhook replay protection (signed timestamp) and idempotency keys to de-duplicate retries; normalise phone numbers to E.164 (e.g. `libphonenumber`) and detect duplicate leads.
- Lead ownership / assignment with Policies, roles, and an activity history of status changes.
- Sanctum token expiration and abilities (scopes); password reset and email verification.
- Search & sorting on the list endpoint, soft deletes for leads.
- OpenAPI/Swagger documentation, CI pipeline (Pint, PHPStan/Larastan, tests), Docker setup.
