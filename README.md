# Campaign Manager

Laravel application for managing email campaigns with contacts, lists, scheduled dispatch, and send tracking.

## Tech Stack

- PHP 8.4 + Laravel 12
- MySQL 8.0
- Redis 7 (Queue Workers)
- Docker Compose

## Setup

### 1. Clone and configure

```bash
git clone <repository-url>
cd trial-campaigns
cp .env.example .env
```

### 2. Install dependencies

```bash
docker run --rm -v ${PWD}:/var/www -w /var/www composer:latest install
```

### 3. Start containers

```bash
docker compose up -d
```

This starts 4 services:
- **app** — PHP-FPM + Laravel (port 8000)
- **queue** — Redis queue worker
- **mysql** — MySQL 8.0 (port 3307)
- **redis** — Redis 7 (port 6380)

### 4. Generate key and run migrations

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

### 5. Verify

```bash
docker compose exec app php artisan test
```

## API Endpoints

All endpoints are rate-limited to 60 requests/minute.

### Contacts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/contacts` | Paginated list |
| POST | `/api/contacts` | Create (name, email) |
| POST | `/api/contacts/{id}/unsubscribe` | Mark as unsubscribed |

### Contact Lists

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/contact-lists` | List with contact count |
| POST | `/api/contact-lists` | Create (name) |
| POST | `/api/contact-lists/{id}/contacts` | Add contact to list |

### Campaigns

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/campaigns` | List with send stats |
| POST | `/api/campaigns` | Create (subject, body, contact_list_id, scheduled_at) |
| GET | `/api/campaigns/{id}` | Show with send stats |
| POST | `/api/campaigns/{id}/dispatch` | Dispatch to queue |

## Architecture

```
app/
├── Contracts/          # Repository + service interfaces
├── Enums/              # CampaignStatus, ContactStatus, CampaignSendStatus
├── Http/
│   ├── Controllers/    # Thin controllers, delegate to repositories/services
│   ├── Middleware/      # EnsureCampaignIsDraft guard
│   ├── Requests/       # FormRequest validation with Enum rules
│   └── Resources/      # API Resources for JSON output
├── Jobs/               # SendCampaignEmail (retry, backoff, idempotency)
├── Models/             # Eloquent models with enum casts and scopes
├── Repositories/       # Eloquent implementations of contracts
├── Services/           # CampaignService (dispatch orchestration)
└── Providers/          # Interface → implementation bindings
```

## Key Decisions

- **Repository Pattern** for data access abstraction (SOLID — Dependency Inversion)
- **PHP Enums** for type-safe status fields
- **Redis** queue driver to decouple job processing from MySQL
- **Pessimistic locking** on campaign dispatch to prevent race conditions
- **Batch upsert** instead of per-record insert for send creation
- **chunkById** for memory-efficient processing of large contact lists
- **Exponential backoff** on job retries (10s, 60s, 300s)

## Tests

```bash
docker compose exec app php artisan test
```

20 feature tests covering:
- Contact CRUD and unsubscribe
- Contact list management and duplicate prevention
- Campaign CRUD, stats aggregation, and dispatch
- Dispatch idempotency and active-only filtering
- Job send, skip, failure, and retry configuration

## Out of Scope

- **Authentication/Authorization** — Not implemented as the requirements explicitly state "No authentication required". In a production environment, this would be the first addition (e.g., Laravel Sanctum for API token auth).

## Documentation

See [CHANGES.md](CHANGES.md) for a detailed list of all 43 issues identified and fixed.
