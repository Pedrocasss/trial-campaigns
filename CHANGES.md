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
