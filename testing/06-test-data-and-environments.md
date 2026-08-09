# Testing 06 — Test Data and Environments

**Parent:** `docs/14-test-plan.md`
**Purpose:** how test data is built, how environments are prepared and reset, and what must never appear in a non-production environment.

## Principle: Tests Build Their Own World

A test that depends on data someone inserted manually is a test that will fail mysteriously on another machine and be deleted rather than fixed. Every test builds exactly what it needs from factories and scenario builders, and asserts against what it built.

This matters more than usual in this system because the interesting behaviours are *stateful* — a stock count is only meaningful against a prior balance and a movement window; a weighted-average cost is only meaningful against a purchase history. The scenario builders below exist so that setting up that state is one line rather than forty.

---

## Factories

One factory per model, with sensible defaults and explicit states.

### Required Factory States

| Factory | States needed |
|---|---|
| `Branch` | `active`, `inactive`, `withSellingStore`, `withDrawer` |
| `Store` | `selling`, `warehouse`, `party` |
| `User` | one state per role: `administrator`, `branchManager`, `cashier`, `purchasingOfficer`, `warehouseManager`, `pricingOfficer`, `partyManager`, `stockCounter`, `accountant` — plus `scopedTo(branch, store)` |
| `Product` | `priced`, `unpriced`, `fractionalAllowed`, `composite`, `service`, `withLocalBarcode`, `withSupplierBarcode` |
| `PriceVersion` | `draft`, `submitted`, `approved`, `rejected` |
| `PurchaseInvoice` | `draft`, `submitted`, `approved` |
| `StockTransfer` | one per state in `INV-03` |
| `StockCountSession` | `planned`, `open`, `counting`, `submitted`, `reconciling`, `approved` |
| `Sale` | `draft`, `suspended`, `approved`, `withCashPayment`, `withElectronicPayment` |
| `Shift` | `open`, `submitted`, `approved`, `closed` |
| `Customer` | `withProductWalletBalance`, `withPartyWalletBalance`, `withLoyaltyPoints`, `withChildren` |
| `GiftCard` | `active`, `partiallyRedeemed`, `exhausted`, `expired`, `void` |
| `PartyBooking` | `draft`, `confirmed`, `inProgress`, `completed`, `cancelled` |
| `PartyInvoice` | `working`, `readyToClose`, `final` |
| `RentalAsset` | one per state in `AST-03` |

### Factory Rules

- **Defaults must be valid.** `Product::factory()->create()` must produce a product that can legally exist. Tests then override only what they are testing.
- **Never bypass business rules to create state.** An approved purchase invoice in a factory should be created by running the real approval, not by setting `status = 'approved'` directly. Otherwise the factory creates states the application itself cannot produce, and tests pass against impossible data.
  Where running the full path is too slow, provide a documented fast path and add one test asserting the fast path produces the same result as the real path.
- **No shared global state.** No factory writes to a shared singleton, cache, or config that leaks between tests.

---

## Scenario Builders

Reusable builders for the multi-step states that many tests need. Put these in `tests/Support/Scenarios/`.

| Builder | Produces | Used by |
|---|---|---|
| `ConfiguredBranch` | Branch + selling store + warehouse + drawer + price list + printer config + one user per role scoped to it | Almost every Feature test |
| `StockedProduct` | Product + approved price + stock at a given store via a real approved purchase invoice | POS, transfer, count, return tests |
| `SaleHistory` | A customer with N approved sales across dates, with payments and loyalty | Return, report, loyalty tests |
| `OpenCountSession` | A count session opened against known balances, with a configurable set of sales executed during the window | `TC-CNT-002`, `TC-CNT-003` |
| `TransferInFlight` | A transfer dispatched and in transit between two stores | Transfer receipt and difference tests |
| `PartyInProgress` | Booking + working invoice + payments on account + operating order + checked-out assets | Party settlement and asset tests |
| `TwoActiveShifts` | Two cashiers on two drawers in one branch | Shift and variance tests |
| `OfflineQueue` | A device with N queued offline sales, some conflicting with server state | Sync and conflict tests |

**Design note:** each builder returns a small object exposing the pieces the test needs, not an array the test has to guess its way through. `$scenario->cashier`, `$scenario->sellingStore`, `$scenario->product` reads far better than index lookups and survives refactoring.

---

## Seeders

Seeders serve two distinct purposes; keep them separate.

### `DatabaseSeeder` — for migration-from-clean

The minimum data required for a new deployment to be usable: roles, permissions, document number sequences, default settings, units of measure, and any reference data the system cannot function without.

**Must not include:** sample products, fake customers, demo branches. A production deployment runs this seeder, and demo data in production is a defect.

### `DemoSeeder` — for staging and manual review

A realistic dataset for UAT, visual regression, load testing, and manual review: several branches, a few hundred products with prices and stock, suppliers, purchase history, customers with wallet and loyalty balances, past sales, party bookings, and rental assets.

**Volume target:** large enough that pagination, N+1 problems, and slow reports actually surface. A demo dataset of twelve products hides every performance defect in the system.

**Must be synthetic.** See the data protection rules below.

---

## Environments

| Environment | Database | Data | Reset |
|---|---|---|---|
| Local | Local MySQL | Factories + `DemoSeeder` | Developer-controlled |
| Testing | Separate `*_testing` database | Built per test; transactions rolled back | Automatic per test |
| Staging | Staging MySQL | `DemoSeeder` or anonymised copy | Rebuilt per release candidate |
| Production | Production MySQL | Real | **Never reset, never seeded with demo data, never a test target** |

### Test Database Configuration

- The testing database is separate and named distinctly. A test suite that can reach the development database will eventually destroy it.
- Use transaction rollback per test by default; use `RefreshDatabase` only where a test genuinely needs a fresh schema.
- Migration-from-clean runs against the testing database with `--env=testing`, never anywhere else.

### Guard Against Wrong-Environment Execution

Add a guard that aborts the suite if the configured database name does not match the expected testing pattern, and aborts destructive commands if the environment is `production`. This costs ten lines and prevents the one mistake that cannot be undone.

---

## Data Protection Rules

These are not optional and apply to every non-production environment.

### Never in a Test Environment

- **Real customer phone numbers** — `MD-06` makes phone the unique customer key, so a copied dataset carries every real customer's contact number.
- **Real customer names**
- **Real children's names and birthday dates** — `MD-06` stores children's data for party operations. This is data about minors; it must not exist outside production in identifiable form, and it must not appear in demo data, screenshots, visual regression baselines, or exported test artifacts.
- **Real consent records**
- **Real payment evidence images** — POS terminal receipts may carry card fragments and merchant identifiers.

### Anonymisation Requirement

If a production copy is used for staging or migration rehearsal, anonymise before use, not after:

| Field | Treatment |
|---|---|
| Customer phone | Replace with a generated unique number in a reserved test range |
| Customer name | Replace with generated names |
| Children's names and birthdays | Replace entirely — generated names and shifted dates |
| Consent records | Reset to a neutral test value |
| Payment evidence attachments | Replace with placeholder images |
| User emails | Replace with a non-deliverable test domain |
| User passwords | Reset to known test credentials |

**Anonymise on the copy, before it leaves the production boundary.** Restoring identifiable data to staging and anonymising afterwards means the identifiable data existed on staging, which is the thing being prevented.

### Screenshots and Baselines

Visual regression baselines and manual review screenshots are committed to the repository. They must contain only synthetic data. A baseline image containing a real customer's phone number is a data exposure that persists in version history indefinitely.

---

## Environment Reset Procedure

### Local reset

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

### Testing database rebuild

```bash
php artisan migrate:fresh --seed --env=testing
```

### Staging rebuild for a release candidate

1. Take a backup of the current staging state if anything is worth keeping.
2. Rebuild schema from migrations — never from a schema dump, so that migration drift surfaces here rather than in production.
3. Seed `DatabaseSeeder`, then `DemoSeeder` at realistic volume.
4. Run `recon:check` to confirm the seeded data is internally consistent.
5. Confirm no identifiable data is present.

---

## Test Data Anti-Patterns

Things that make a suite unmaintainable, listed because each one is tempting in the moment.

| Anti-pattern | Why it fails | Instead |
|---|---|---|
| Hardcoded IDs (`Product::find(1)`) | Breaks the moment seed order changes | Build and reference what the test created |
| Depending on `DemoSeeder` in Feature tests | Couples every test to demo content | Factories and scenario builders |
| Shared state between tests | Order-dependent failures that appear only in parallel runs | Isolated per-test state |
| Setting statuses directly to skip workflow | Creates states the app cannot produce | Run the real transition |
| Asserting on a count of all records | Breaks whenever any other data exists | Assert on the specific records under test |
| Sleeping to handle async | Slow and flaky | Wait for the actual condition |
| Copying production data to local | Exposes real customer and children's data | `DemoSeeder` |

---

**Disclaimer:** This document defines test data and environment practice for already-approved PRD requirements. It introduces no new Phase 1 business capability, production value, permission, limit, state, or commercial policy.
