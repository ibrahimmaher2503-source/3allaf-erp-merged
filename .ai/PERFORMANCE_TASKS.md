# Performance and Large-Dataset Tasks — TSK-P01 … TSK-P12

**Status:** Proposed task set — team-adopted, owner approval outstanding
**Authority:** `docs/56`, NFR-05, NFR-03
**Blockers:** BLK-001 (production database engine, Redis availability, runtime limits)

These are separate from the functional task list. Most attach to an existing task rather than running standalone — the "Attaches to" column says where.

---

## Priority Groups

| Group | Tasks | When |
|---|---|---|
| **A — Do before data exists** | P01, P02, P03 | With the first ledger migration. Cheap now, migration-over-financial-data later |
| **B — Do during build** | P04, P05, P06, P07 | Inside the task that creates each surface |
| **C — Do before go-live** | P08, P09, P10 | Before TSK-042 |
| **D — Deferred** | P11, P12 | Only on measured need |

---

## Group A — Structural, before volume

### TSK-P01 — Ledger Indexes and Column Types

- **Status:** Implemented as a local schema slice; migration diagnostics and command smoke remain required
- **Attaches to:** TSK-015 Slice A (schema)
- **Authority:** `docs/56` §2
- **Scope:** Composite indexes on `stock_movements`, `stock_balances`, `audit_logs`, and `purchase_invoice_lines` per §2.2. `DECIMAL(19,4)` money and `DECIMAL(20,6)` quantity throughout. `ENUM` for movement type and document state. `utf8mb4_bin` on barcode, item code, and document number columns. `RESTRICT` foreign keys on all financial documents. Generated column for any expression used in a filter.
- **Dependencies:** TSK-014 complete; `docs/47` movement type catalog agreed.
- **Definition of Done:** Every index in §2.2 exists; `EXPLAIN` on the stock-card query and the period-report query shows index use with no filesort; no `CASCADE` foreign key on a financial table.
- **Why now:** Adding an index to a table with millions of rows is an online schema change with a maintenance window. Adding it in the first migration is free.

### TSK-P02 — Period Snapshot Table and Generator

- **Status:** Implemented as a local schema-only snapshot table; generator/reconciliation remain pending
- **Attaches to:** TSK-019 (inventory ledger), table created with TSK-015 Slice A
- **Authority:** `docs/56` §3
- **Scope:** `stock_period_snapshots` (product, store, period, quantity, value, generated_at, immutable). Scheduled generation job after period close. Invalidation and regeneration when a backdated correction lands before an existing snapshot. Reconciliation proving a snapshot reproduces from movements.
- **Dependencies:** TSK-P01.
- **Definition of Done:** A March report reads February's snapshot plus March movements only, verified in the query log; a snapshot regenerated from movements matches the stored figure exactly; a backdated correction invalidates and regenerates the affected snapshots.
- **Why now:** The table can exist before the generator does. Retrofitting snapshots after two years of ledger means backfilling every historical period.

### TSK-P03 — `inventory:rebuild-balances` Command

- **Status:** Implemented as a local command; dry-run/apply diagnostics remain required
- **Attaches to:** TSK-019
- **Authority:** `docs/47` §5, `docs/56` §13
- **Scope:** Artisan command rebuilding `stock_balances` from `stock_movements`, in a dry-run mode reporting divergence and an apply mode. Per-store and per-product scoping. Output usable as evidence.
- **Dependencies:** TSK-P01.
- **Definition of Done:** Dry run on a seeded divergence reports it precisely; apply mode corrects it inside a transaction; the command is referenced in the restore procedure (`docs/53` §6 step 6).
- **Why now:** This is the strongest diagnostic the system will have and the required proof step after any restore. A few hours of work before there is data to reconcile.

---

## Group B — Built into each surface

### TSK-P04 — Table Query Standard

- **Status:** Not Started
- **Attaches to:** Every list screen — TSK-010, 014, 015, 019, 020, 022, 038
- **Authority:** `docs/56` §4, §5
- **Scope:** Cursor pagination above the configured threshold; `simplePaginate` where the last page number is unused; explicit `select()`; tie-breaker on every sort; allow-listed filter and sort keys; mandatory default date range on ledger tables; filters in the URL; `resetPage()` on filter change; scope applied before filter, server-side.
- **Dependencies:** None.
- **Definition of Done:** No `OFFSET` pagination on a table expected to exceed 50k rows; a tampered sort or filter key is rejected rather than passed through; `EXPLAIN` reviewed for every table query; changing a filter returns to page 1.

### TSK-P05 — Livewire Table Conventions

- **Status:** Not Started
- **Attaches to:** Same screens as TSK-P04
- **Authority:** `docs/56` §6
- **Scope:** `#[Computed]` for result sets; `#[Locked]` on ID and scope properties; `#[Url]` on filters, sort, and page; `wire:key` on every row; debounced live search; `wire:model.blur` elsewhere; `wire:loading.delay`; `wire:navigate`; no collections passed between components.
- **Dependencies:** TSK-P04.
- **Definition of Done:** A tampered `#[Locked]` property is rejected; sorting and deleting do not misbind rows; the request payload does not carry a serialised result set; each table issues its query once, confirmed in the query log.

### TSK-P06 — Lazy Loading and N+1 Guard

- **Status:** Implemented in local configuration; screen-by-screen query evidence remains pending
- **Attaches to:** All list and detail screens
- **Authority:** `docs/56` §8
- **Scope:** `Model::preventLazyLoading()` in local and staging; `#[Lazy]` with a shaped placeholder on heavy tables; deferred expensive columns; tab content loaded on activation; `with()` and `withCount()` for everything displayed.
- **Dependencies:** TSK-P05.
- **Definition of Done:** An unguarded relation access raises in local; the product list, stock card, and dashboard each issue a bounded, countable number of queries; the `#[Lazy]` double-query trap in §6 is verified absent.

### TSK-P07 — Table UI Standard

- **Status:** Not Started
- **Attaches to:** TSK-004 shared UI foundation, applied by every list screen
- **Authority:** `docs/56` §7
- **Scope:** Sticky header and first column; priority columns collapsing to a detail drawer; user-configurable columns persisted via `user_ui_preferences`; right-aligned `tabular-nums`; page total and grand total distinguished; approximate count above threshold; active filter count with clear-all; shared empty, loading, error, and denied states; select-all page-versus-result-set distinction; RTL mirroring with LTR numerals.
- **Dependencies:** TSK-004.
- **Definition of Done:** One reference table implements the full standard and is the pattern other screens copy; verified in Arabic RTL and English LTR on desktop and mobile.

---

## Group C — Before go-live

### TSK-P08 — Background Export and Import Hardening

- **Status:** Not Started
- **Attaches to:** TSK-040 (export centre), TSK-012 and TSK-015 Slice E (imports)
- **Authority:** `docs/56` §10, `docs/50` §9, `docs/42` §4
- **Scope:** Queued export above a row threshold; streaming with `lazy()` or `cursor()`; formula-prefixed cells written as text on **export** as well as import; priority queues separating posting from export; `WithoutOverlapping`, `RateLimited`, `ThrottlesExceptions`; jobs carrying IDs rather than serialised models; job batching with progress and cancellation; correlation ID propagated into jobs.
- **Dependencies:** TSK-P04.
- **Definition of Done:** A large export queues, streams, and does not exhaust memory; a posting job is not delayed behind an export; an exported cell beginning with `=` opens as text in Excel; a cancelled batch stops cleanly.

### TSK-P09 — Caching Layer and Scope Safety

- **Status:** Not Started
- **Attaches to:** TSK-038 (dashboards), applied wherever caching is introduced
- **Authority:** `docs/56` §9
- **Scope:** Application cache for master data; version-embedded keys; tags where Redis is available; stampede protection via lock or `flexible()`; the never-cache list in §9.4; **scope in every key for permission-filtered data**; read-your-writes if a replica is introduced.
- **Dependencies:** BLK-001 (Redis availability).
- **Definition of Done:** A cached list for a user in branch A is never served to a user in branch B — verified by direct request, not by inspection; a balance read during posting comes from source under lock, not cache; a permission decision is evaluated live; an expired hot key rebuilds once, not once per concurrent request.
- **Note:** §9.5 is a data-leak control, not an optimisation. It is verified as a security check.

### TSK-P10 — Observability and Volume Testing

- **Status:** Local observability controls implemented; realistic-volume execution and per-screen baselines remain pending
- **Attaches to:** TSK-042 (production readiness)
- **Authority:** `docs/56` §12, §13
- **Scope:** Structured JSON logging with correlation ID; slow query log enabled; `DB::listen` query-count budget failing the request in local and staging; a documented volume test at approximately 50,000 products and 1,000,000 movements; a recorded response-time baseline per screen.
- **Dependencies:** TSK-P01 through TSK-P07.
- **Definition of Done:** Every list and report screen measured at realistic volume; any screen above 500ms recorded with a remediation note; baselines stored as evidence for `docs/39` release gates.
- **Note:** Volume test data lives in a throwaway environment and is never committed, seeded into a shared database, or carried toward production.

---

## Group D — Deferred until measured

### TSK-P11 — Ledger Partitioning and Retention

- **Status:** Not Started — **do not begin without a measured trigger**
- **Trigger:** `stock_movements` or `audit_logs` exceeding a measured query-degradation threshold, or a retention policy requiring bulk deletion.
- **Authority:** `docs/56` §11
- **Scope:** Monthly or quarterly partitioning; retention by `DROP PARTITION` rather than `DELETE`; online schema change procedure.
- **Note:** `audit_logs` grows fastest because NFR-01 mandates an event for every sensitive action. The retention policy is an owner decision and is unresolved.

### TSK-P12 — Read Replica, Materialised Aggregates, and Outbox

- **Status:** Not Started — **do not begin without a measured trigger**
- **Trigger:** Reporting load measurably affecting posting latency, or dashboard aggregate queries exceeding budget after TSK-P02 snapshots are in place.
- **Authority:** `docs/56` §11
- **Scope:** Read replica with read-your-writes; incrementally maintained aggregates **with a nightly reconciliation against source**; outbox pattern for post-approval fan-out.
- **Note:** The nightly reconciliation is not optional. An aggregate that cannot be proven against source is a number nobody can defend.

---

## Sequencing

```
TSK-014 local PO prerequisite / DEC-044 continuation
   ↓
TSK-P01 ──→ TSK-P02, TSK-P03        (with TSK-015 Slice A)
   ↓
TSK-P04 → TSK-P05 → TSK-P06 → TSK-P07   (with each surface)
   ↓
TSK-P08, TSK-P09                     (with TSK-040, TSK-038)
   ↓
TSK-P10                              (before TSK-042)
   ↓
TSK-P11, TSK-P12                     (only if measurement demands)
```

Group A is the only group with a hard deadline: it must land with the first ledger migration. Everything after that can be scheduled.
