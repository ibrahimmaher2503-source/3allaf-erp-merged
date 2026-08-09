# Deterministic Test Data Guide

The test-data foundation uses Laravel factories, Faker, seeders, and scenario builders. It creates synthetic records only; no real customer data, credentials, secrets, or paid providers are used.

## Factories and states

- `UserFactory`: verified, unverified, two-factor.
- `CompanyFactory`, `BranchFactory`, `StoreFactory`, `CashDrawerFactory`, `PaymentMethodFactory`, `CategoryFactory`, `BrandFactory`, `ProductFactory`.
- Shared states include `active`, `inactive`, `fractional`, `warehouse`, and scoped user relationships.

## Scenarios

- `PlatformScenario::ready()` creates company, branch, selling store, cashier, role, and branch/store scopes.
- `PosScenario::ready()` extends that chain with drawer, open shift, products, approved price list/version/lines, stock balances, and payment method.
- Purchasing, inventory-transfer, and pricing-approval scenarios are not fabricated here; add them only when their workflow contracts are implemented.

## Dataset profiles

| Profile | Product records | Use |
|---|---:|---|
| TINY | 1 | Unit/feature tests |
| SMALL | 10 | Integration/security/browser |
| MEDIUM | 100 | E2E/UAT/reconciliation |
| LARGE | 10,000 | Performance/load only; bulk inserts in batches of 500 |
| RACE | 2 | MariaDB concurrency/deadlock fixtures |

## Commands

Run only against an isolated local/testing database:

```text
php artisan testing:data --size=tiny
php artisan testing:data --size=small
php artisan testing:data --size=medium
php artisan testing:data --size=large
php artisan testing:data --size=race
```

The command refuses `production` and `staging`. `LARGE` is never selected by normal PHPUnit runs.
