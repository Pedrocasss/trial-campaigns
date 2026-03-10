# CHANGES.md

## 1. Missing Models: Contact, ContactList, CampaignSend

**Issue:** Three models (`Contact`, `ContactList`, `CampaignSend`) are referenced throughout the codebase — factories, seeder, service, and job — but were never created. The application crashes immediately on any operation.

**Why it matters:** Without these models, nothing works. Seeding fails, the campaign service cannot query contacts, and jobs cannot resolve sends. This is a blocking issue for any environment.

**Fix:** Created all three models with proper `$fillable` attributes, relationships (`belongsTo`, `hasMany`, `belongsToMany`), and the `HasFactory` trait for test support.

---

## 2. `contacts.email` missing unique constraint

**Issue:** The `email` column on the `contacts` table has no unique constraint, allowing duplicate email addresses.

**Why it matters:** Duplicate emails lead to duplicate sends per campaign — the same person receives multiple copies. It also makes unsubscribe operations ambiguous (which record to update?). This violates basic normalisation.

**Fix:** Added `->unique()` to `contacts.email`. Added an index on `status` since it's used to filter active contacts during dispatch.

---

## 3. `contact_contact_list` missing unique constraint

**Issue:** The pivot table allows the same contact to be added to the same list multiple times. No uniqueness enforced on the `(contact_id, contact_list_id)` pair.

**Why it matters:** Duplicates in the pivot mean a contact receives duplicate emails when a campaign dispatches. The seeder's `->attach()` doesn't check for duplicates, and in production, repeated API calls would silently corrupt data.

**Fix:** Added `$table->unique(['contact_id', 'contact_list_id'])`. Also added `cascadeOnDelete()` on both foreign keys — deleting a contact or list should clean up the pivot.

---

## 4. `campaigns.scheduled_at` defined as string instead of timestamp

**Issue:** The `scheduled_at` column is defined as `$table->string()`. The scheduler compares it with `now()` using `where('scheduled_at', '<=', now())`.

**Why it matters:** Comparing a string column against a datetime value produces unreliable results. Lexicographic ordering on date strings is fragile and format-dependent. Indexes on string-encoded dates are inefficient. MySQL cannot optimise range queries on a varchar date.

**Fix:** Changed to `$table->timestamp('scheduled_at')->nullable()`. Added a composite index on `['status', 'scheduled_at']` to support the scheduler query. Added `'scheduled_at' => 'datetime'` cast in the Campaign model.

---

## 5. `campaign_sends` missing unique constraint (idempotency)

**Issue:** No unique constraint on `(campaign_id, contact_id)`. If the dispatch service runs twice for the same campaign (e.g., scheduler race condition), it creates duplicate send records.

**Why it matters:** This is a core idempotency issue. The same contact would receive the same email multiple times. The `campaign_sends` table should guarantee one send per contact per campaign at the database level.

**Fix:** Added `$table->unique(['campaign_id', 'contact_id'])`. Added an index on `status` for efficient stats aggregation. Added `cascadeOnDelete()` on both foreign keys.

---

## 6. Campaign stats computed via collection instead of DB aggregation

**Issue:** `getStatsAttribute()` in the Campaign model loads ALL send records into memory with `$this->sends`, then counts each status using collection methods.

**Why it matters:** A campaign with 100k contacts loads 100k Eloquent models into memory just to count them. Listing 10 campaigns triggers 10 queries, each hydrating thousands of objects. This causes memory exhaustion and slow responses at scale. The requirements explicitly state: "Stats must use DB aggregation, not collection counting."

**Fix:** Replaced the accessor with a `scopeWithSendStats()` query scope that uses `withCount()` with conditional closures. This produces a single SQL query with `COUNT(CASE WHEN ...)` subqueries — no models hydrated, no N+1.

---

## 7. Useless `'status' => 'string'` cast in Campaign model

**Issue:** `protected $casts = ['status' => 'string']` does nothing — enum columns already return strings.

**Why it matters:** Misleading code. Developers might assume the cast is doing something meaningful. A proper approach would be a PHP `BackedEnum` for type safety.

**Fix:** Removed the useless cast. Replaced with `'scheduled_at' => 'datetime'` which is actually needed. A PHP enum for status could be added later but was not introduced here to keep changes focused.

---

## 8. Foreign keys without `onDelete` policy

**Issue:** All foreign keys used `->constrained()` without specifying delete behaviour. The default is `RESTRICT`, which silently prevents deletions.

**Why it matters:** In production, attempting to delete a contact list that has campaigns would fail with a database error. The delete policy should be an intentional architectural decision, not an accidental default.

**Fix:** Added `cascadeOnDelete()` to pivot and send foreign keys. When a contact or list is deleted, related pivot entries and send records are cleaned up automatically. Campaign foreign keys also cascade — deleting a list removes its campaigns and their sends.

**Trade-off:** Cascade deletes are convenient but irreversible. For a system where audit trails matter, soft deletes or `RESTRICT` with explicit cleanup would be safer. For this application's scope, cascade is the pragmatic choice.

---

## 9. Middleware `EnsureCampaignIsDraft` — inverted logic

**Issue:** The condition `if ($campaign->status === 'draft')` returns a 422 error. This is the opposite of what the middleware name implies — it blocks campaigns that ARE in draft and allows campaigns that are already sending or sent.

**Why it matters:** With this bug, dispatching a campaign that has already been sent would pass validation, while attempting to dispatch a draft campaign would be rejected. This completely breaks the dispatch workflow.

**Fix:** Changed `=== 'draft'` to `!== 'draft'`.

---

## 10. `CampaignService::dispatch()` — loads all contacts without chunking

**Issue:** The dispatch method calls `->get()` on the contacts query, loading every active contact in the list into memory at once.

**Why it matters:** A list with 500k contacts loads 500k Eloquent models into memory simultaneously. Combined with creating a `CampaignSend` and dispatching a job for each, this leads to memory exhaustion and request timeouts.

**Fix:** Replaced `->get()` with `->chunkById(500, ...)` to process contacts in batches of 500. This keeps memory usage constant regardless of list size.

---

## 11. `CampaignService::dispatch()` — status update after the loop

**Issue:** `$campaign->update(['status' => 'sending'])` is called AFTER the loop that creates sends and dispatches jobs.

**Why it matters:** If the loop fails midway (exception, timeout, OOM), the campaign status remains `draft`. The scheduler will re-dispatch it on the next tick, creating duplicate sends for contacts already processed. This is an idempotency issue.

**Fix:** Moved `update(['status' => 'sending'])` to BEFORE the loop. Now if the loop fails partway, the campaign is already marked as `sending` and won't be picked up by the scheduler again.

---

## 12. `CampaignService::dispatch()` — no idempotency on send creation

**Issue:** `CampaignSend::create()` is used without checking if a send already exists for that campaign-contact pair. If dispatch runs twice, duplicate sends are created.

**Why it matters:** Even with the unique constraint at the DB level (fix #5), the application would throw an exception on duplicate insert. The service should handle this gracefully.

**Fix:** Replaced `CampaignSend::create()` with `CampaignSend::firstOrCreate()`. Jobs are only dispatched for newly created sends (`$send->wasRecentlyCreated`), preventing duplicate processing.

---

## 13. `CampaignService` — dead code (`buildPayload`, `resolveReplyTo`)

**Issue:** `buildPayload()` and `resolveReplyTo()` are never called. `resolveReplyTo()` references `$campaign->reply_to`, a field that doesn't exist in the campaigns table.

**Why it matters:** Dead code increases cognitive load and can mislead developers into thinking these methods are part of the workflow. `resolveReplyTo()` would always return null.

**Fix:** Removed both methods.

---

## 14. `SendCampaignEmail` job — no retry/backoff, exception swallowed

**Issue:** The job catches all exceptions internally and marks the send as `failed` on the first error. No `$tries`, `$backoff`, or `$maxExceptions` are defined. The queue worker never sees the failure.

**Why it matters:** Transient failures (network timeouts, SMTP rate limiting) are treated as permanent. There is no exponential backoff. Since the exception is caught, the job doesn't go to `failed_jobs` — failures are invisible to monitoring.

**Fix:** Added `$tries = 3` and `$backoff = [10, 60, 300]` for exponential backoff. Removed the try/catch — exceptions now propagate to the queue worker, enabling automatic retries. Added a `failed()` method that marks the send as failed and logs the error only after all attempts are exhausted.

**Trade-off:** Letting exceptions propagate means the job appears as "failed" in the queue during retries. This is the correct behaviour — it gives the queue worker control over retry timing and enables monitoring via `failed_jobs`.

---

## 15. `SendCampaignEmail` job — receives ID instead of model

**Issue:** The constructor receives `int $campaignSendId` and manually queries `CampaignSend::find()`. This bypasses Laravel's model serialization for queued jobs.

**Why it matters:** Laravel's `SerializesModels` trait automatically serializes/deserializes Eloquent models by ID. Passing the model directly is cleaner, avoids a manual query, and handles model-not-found scenarios via `ModelNotFoundException`. It also adds an idempotency check — if the send is already `sent`, the job exits early.

**Fix:** Changed the constructor to accept a `CampaignSend` model directly. Added an early return if status is already `sent`.

---

## 16. Scheduler in `Kernel.php` — deprecated in Laravel 11+

**Issue:** The scheduler is defined in `app/Console/Kernel.php` using the `ConsoleKernel` class. In Laravel 11+, scheduling is done in `routes/console.php` using the `Schedule` facade.

**Why it matters:** `Kernel.php` may not be loaded by the framework, meaning scheduled tasks silently never run.

**Fix:** Moved scheduling to `routes/console.php`. Deleted `Kernel.php`.

---

## 17. Scheduler — no status filter, re-dispatches sent campaigns

**Issue:** The scheduler query `Campaign::where('scheduled_at', '<=', now())` doesn't filter by status. Every campaign with a past `scheduled_at` — including those already `sending` or `sent` — gets re-dispatched every minute.

**Why it matters:** This creates duplicate sends that grow linearly with the number of historical campaigns. Each scheduler tick re-processes the entire campaign history.

**Fix:** Added `->where('status', 'draft')` and `->whereNotNull('scheduled_at')` to the query. Only draft campaigns with a scheduled time in the past are dispatched.

---

## 18. Queue driver using database instead of Redis

**Issue:** The queue connection is set to `database`, meaning every job dispatch and consumption performs INSERT/SELECT/UPDATE/DELETE on the same MySQL instance that serves the application.

**Why it matters:** Under load — e.g., dispatching a campaign to 100k contacts — the queue operations compete with API queries for MySQL connections and I/O. The `jobs` table becomes a bottleneck with constant polling. This degrades both queue throughput and API response times.

**Fix:** Switched `QUEUE_CONNECTION` to `redis`. Added a Redis 7 container to docker-compose. Installed the `phpredis` extension in the Dockerfile. Redis is in-memory, non-blocking, and purpose-built for this workload — it decouples queue processing from the application database entirely.

**Trade-off:** Adds an infrastructure dependency (Redis). For this application's scale, the performance gain far outweighs the operational cost. Redis is already a standard component in Laravel production deployments.

---

## 19. Scheduler uses `each()` instead of `cursor()` — memory bloat

**Issue:** The scheduler loads all matching campaigns into memory with `->each()` before iterating.

**Why it matters:** With thousands of scheduled campaigns, this allocates all models in memory at once. A `cursor()` streams one model at a time, keeping memory constant regardless of dataset size.

**Fix:** Replaced `->each()` with `->cursor()->each()` for memory-efficient streaming.

---

## 20. `CampaignService::dispatch()` — race condition on concurrent calls

**Issue:** Two simultaneous dispatch calls (e.g., scheduler + API request) could both read status as `draft`, both set it to `sending`, and both create sends — bypassing idempotency.

**Why it matters:** Without locking, the status check and update are not atomic. Under concurrent load, this leads to duplicate sends and data corruption.

**Fix:** Wrapped the dispatch in a `DB::transaction()` with `lockForUpdate()` on the campaign row. The first caller acquires the lock and proceeds; the second caller blocks until the first finishes, then sees status `sending` and fails with `firstOrFail()`.

**Trade-off:** Pessimistic locking adds a small overhead per dispatch. This is acceptable because campaign dispatch is a low-frequency operation (once per campaign), and correctness is more important than throughput here.

---

## 21. `CampaignService::dispatch()` — `firstOrCreate()` in loop causes N queries

**Issue:** For each contact in the list, `firstOrCreate()` executes a SELECT then an INSERT (if not found). With 100k contacts, this results in 100k–200k queries.

**Why it matters:** This is the single biggest performance bottleneck in the dispatch flow. Each query has network round-trip overhead to MySQL, and the total time scales linearly with list size.

**Fix:** Replaced the per-contact `firstOrCreate()` with a batch `upsert()` per chunk. This inserts up to 500 records in a single query, reducing total queries from N to N/500. After upsert, only pending sends are queried once to dispatch jobs.

---

## 22. Missing composite index on `campaign_sends(campaign_id, status)`

**Issue:** The stats scope queries `campaign_sends` filtered by `campaign_id` and `status`. The existing `index('status')` alone doesn't cover this pattern efficiently.

**Why it matters:** Without a composite index, MySQL must scan all sends for a campaign and then filter by status. With millions of sends, this causes slow aggregation queries on the campaigns list endpoint.

**Fix:** Replaced the single `index('status')` with a composite `index(['campaign_id', 'status'])`. This covers both the stats queries and individual status lookups for a given campaign.

---

## 23. Missing eager loading on `contactList` in campaign endpoints

**Issue:** `CampaignController::index()` and `show()` return campaign data without eager loading the `contactList` relationship. If the response includes contactList data, each campaign triggers an additional query.

**Why it matters:** With 15 campaigns per page, this causes 15 additional queries per request (N+1 pattern). At scale, this multiplies response times.

**Fix:** Added `with('contactList')` to `index()` and `load('contactList')` to `show()`.

---

## 24. Middleware `EnsureCampaignIsDraft` — duplicate query

**Issue:** The middleware calls `Campaign::findOrFail()` even when route model binding has already resolved the campaign model. This executes an unnecessary duplicate query.

**Why it matters:** Every request through this middleware wastes one database query. Under high traffic, these add up.

**Fix:** Check if the route parameter is already a Campaign model instance (from route model binding) before querying. Falls back to `findOrFail()` only when needed.

---

## 25. `ContactController::unsubscribe()` — unnecessary UPDATE

**Issue:** The endpoint always executes `UPDATE contacts SET status = 'unsubscribed'` even if the contact is already unsubscribed.

**Why it matters:** Unnecessary write operations on the database. Minor impact individually, but indicates a lack of defensive coding.

**Fix:** Added a status check before the update — only writes to the database if the contact isn't already unsubscribed.

---

## 26. `Contact` model — repeated `where('status', 'active')` filter

**Issue:** The query `->where('status', 'active')` appears in multiple places (service, scheduler). This duplicates business logic across layers.

**Why it matters:** If the definition of "active" changes (e.g., adding a `verified` status), every occurrence must be updated. Missed updates cause subtle bugs.

**Fix:** Added a `scopeActive()` query scope to the Contact model. All consumers now use `->active()` instead of `->where('status', 'active')`.

---

## 27. No rate limiting on API endpoints

**Issue:** All API endpoints accept unlimited requests with no throttling.

**Why it matters:** Without rate limiting, a malicious actor (or a misconfigured client) can flood the API with requests. The dispatch endpoint is particularly dangerous — spamming it could queue millions of jobs. This is a denial-of-service vector.

**Fix:** Wrapped all API routes in `throttle:60,1` middleware — 60 requests per minute per IP. Laravel handles the 429 Too Many Requests response automatically.

---

## 28. Weak email validation on contact creation

**Issue:** The email validation rule was `'email'`, which accepts loosely formatted strings that aren't valid email addresses.

**Why it matters:** Invalid emails waste resources during campaign dispatch — jobs are created and queued for addresses that will inevitably bounce. This increases queue load and degrades deliverability metrics.

**Fix:** Changed to `'email:rfc'` for strict RFC 5321 compliance. Added `max:255` to prevent oversized inputs.

**Trade-off:** DNS validation (`email:rfc,dns`) was considered but rejected — it requires network lookups during validation, slows down requests, and fails in environments without internet access (CI, containers).

---

## 29. Missing type validation on foreign key inputs

**Issue:** `contact_list_id` and `contact_id` in FormRequests only validated with `exists:` but not `integer`. String values could pass validation and cause unexpected query behaviour.

**Why it matters:** MySQL's type coercion silently converts strings to integers, which can lead to incorrect matches (e.g., `"1abc"` matches ID `1`). Explicit type validation catches malformed input at the boundary.

**Fix:** Added `'integer'` rule to all foreign key fields in FormRequests.

---

## 30. No max length on campaign body

**Issue:** The campaign `body` field had no size limit in the FormRequest. The migration defines it as `text` (64KB in MySQL), but no application-level validation existed.

**Why it matters:** An attacker could submit a body with millions of characters, causing excessive memory usage during request processing, storage bloat, and slow job processing when the body is loaded for each send.

**Fix:** Added `'max:65535'` to match the MySQL `text` column limit.

---

## 31. Job error messages may contain sensitive data

**Issue:** The `failed()` method in `SendCampaignEmail` stores the raw exception message in the database. Exception messages can contain SQL queries, file paths, credentials, or internal application details.

**Why it matters:** If error data is exposed via an API endpoint (e.g., campaign show with send details), internal implementation details leak to the client. This is an information disclosure vulnerability.

**Fix:** Truncated error messages to 500 characters using `Str::limit()`. Added a `timeout` of 30 seconds to prevent hung jobs from blocking the worker indefinitely.

---

## 32. Long-running database transaction in campaign dispatch

**Issue:** The entire dispatch operation (lock + status update + chunked contact processing + job dispatching) was wrapped in a single `DB::transaction()`. With large lists, this transaction could run for minutes.

**Why it matters:** Long-running transactions hold row locks, preventing other operations on the same rows. They also increase the risk of deadlocks, connection timeouts, and innodb lock wait timeouts in MySQL.

**Fix:** Separated the transaction into two phases: (1) a short transaction that acquires the lock and updates the status atomically, and (2) the chunked contact processing outside the transaction. The status update is the critical section — once marked as `sending`, the scheduler won't re-dispatch it, even if the chunking fails midway.

**Trade-off:** If the chunking fails after the transaction commits, some contacts may not have sends created. This is recoverable — a retry mechanism or admin action can resume dispatch for the remaining contacts. The alternative (long transaction) risks worse outcomes: deadlocks, connection pool exhaustion, and cascading failures.

---

## 33. API error responses expose internal details

**Issue:** The default Laravel exception handler returns detailed error messages and stack traces in JSON responses, including model class names, SQL queries, and file paths.

**Why it matters:** Information disclosure — attackers can use internal error details to map the application structure, discover table names, and craft targeted attacks.

**Fix:** Added custom exception rendering in `bootstrap/app.php` for `ModelNotFoundException` and `NotFoundHttpException`. API requests now receive a generic `{"error": "Resource not found."}` instead of model-specific details. In production, `APP_DEBUG=false` hides all stack traces.

---

## 34. No Data Access Abstraction Layer — controllers and services coupled to Eloquent

**Issue:** Controllers called Eloquent models directly (`Contact::paginate()`, `Campaign::create()`, etc.). The service layer also depended directly on Eloquent models for all database operations. There was no abstraction between the business logic and the data access layer.

**Why it matters:** Direct coupling to Eloquent means:
- Business logic cannot be tested without a database
- Switching the data source (e.g., from MySQL to an API, or to a different ORM) requires rewriting controllers and services
- Violates the Dependency Inversion Principle (SOLID) — high-level modules depend on low-level modules
- The requirements explicitly state: "Use of a Data Access Abstraction Layer for interaction with MySQL"

**Fix:** Introduced the Repository Pattern with interfaces and concrete implementations:
- `ContactRepositoryInterface` → `EloquentContactRepository`
- `ContactListRepositoryInterface` → `EloquentContactListRepository`
- `CampaignRepositoryInterface` → `EloquentCampaignRepository`

Controllers now depend on interfaces injected via constructor. The `AppServiceProvider` binds interfaces to their Eloquent implementations. To switch data sources, only the bindings and implementations need to change — controllers and services remain untouched.

---

## 35. Email sending tightly coupled to the Job — not mockable or swappable

**Issue:** The `SendCampaignEmail` job contained a private `sendEmail()` method with the email logic hardcoded. There was no way to swap the email transport (e.g., from log to SMTP, or to a third-party API) without modifying the job.

**Why it matters:** Violates the Open/Closed Principle (SOLID) — the job must be modified to change email behaviour. It also makes testing difficult — you can't mock the email sender without mocking the entire job.

**Fix:** Extracted an `EmailSenderInterface` contract with a single `send()` method. Created `LogEmailSender` as the default implementation (mocks the send). The job receives the sender via method injection in `handle(EmailSenderInterface $sender)`. To switch to a real SMTP sender, create a new implementation and update the binding in `AppServiceProvider` — no job code changes needed.

---

## 36. Service layer not using dependency injection

**Issue:** `CampaignService` accessed Eloquent models and the database directly, with no constructor dependencies. The scheduler also resolved the service via `app()` without going through an interface.

**Why it matters:** Without DI, the service cannot be tested in isolation. Its behaviour is permanently tied to the database layer. This violates the Dependency Inversion Principle and makes unit testing impractical.

**Fix:** Refactored `CampaignService` to receive `CampaignRepositoryInterface` via constructor injection. All database operations (lock, upsert, query) go through the repository. The scheduler resolves both the repository and the service via the container, respecting the dependency chain.
