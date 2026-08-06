# Demo Master Data — Local Development Only

**Status:** Development fixture. **BLK-006 remains Open.**
**Authority:** DEC-043 §7
**Guard:** `LocalDemoSeeder`, protected by `EnvironmentSafetyTest` and `LocalDemoSeederSafetyTest`

---

## 1. Rules

1. Seeds only when `APP_ENV=local`. The seeder aborts otherwise.
2. Every row carries a `DEMO-` code prefix and an `is_demo` flag.
3. No opening stock is seeded. Balances arise from test purchase invoices so the weighted-average series is exercised from zero.
4. Demo presence blocks the Cutover wizard (`docs/46` §8).
5. This data closes no blocker and grants no production readiness.

---

## 2. Branches

| Code | Name (AR) | Name (EN) | Status |
|---|---|---|---|
| `DEMO-BR-01` | فرع مدينة نصر | Nasr City | active — main |
| `DEMO-BR-02` | فرع المعادي | Maadi | active |
| `DEMO-BR-03` | فرع الإسكندرية — سموحة | Alexandria — Smouha | **inactive** |

BR-03 is deliberately inactive so that receiving-into-a-closed-branch is exercised without a bespoke test scenario.

---

## 3. Stores

| Code | Name (AR) | Type | Owning branch | Receives goods |
|---|---|---|---|---|
| `DEMO-ST-01` | المخزن الرئيسي — العبور | warehouse | BR-01 | Yes |
| `DEMO-ST-02` | مخزن بيع — مدينة نصر | selling | BR-01 | No |
| `DEMO-ST-03` | مخزن بيع — المعادي | selling | BR-02 | No |
| `DEMO-ST-04` | مخزن حفلات — مدينة نصر | party | BR-01 | Yes |
| `DEMO-ST-05` | مخزن بيع — سموحة | selling | BR-03 | No |
| `DEMO-ST-06` | مخزن تالف | quarantine | BR-01 | No |

A central receiving warehouse feeding selling stores is the normal Egyptian retail pattern. ST-04 is separate so the retail/party boundary required by the PRD is exercised.

---

## 4. Demo Users

| Username | Role | Scope |
|---|---|---|
| `demo.purchasing` | Purchasing Officer | BR-01, BR-02 |
| `demo.warehouse` | Warehouse Manager | ST-01, ST-04 |
| `demo.manager` | Branch Manager | BR-01 |
| `demo.cashier` | Cashier | ST-02 |
| `demo.reviewer` | Accountant / Reviewer | all, read-only |

`demo.purchasing` and `demo.warehouse` are separate accounts on purpose — separation of duties on invoice approval cannot be verified with one user.

---

## 5. Removal Before Production

`DELETE` every row where `is_demo = true`, verify zero `stock_movements` reference a demo store, then run the Cutover wizard with real data. Removal must happen before cutover, never after.
