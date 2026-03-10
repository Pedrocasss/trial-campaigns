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
