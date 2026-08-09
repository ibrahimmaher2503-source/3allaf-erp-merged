# Project Test Context

- Runtime: PHP 8.4/Laravel 13; SQLite is the fast default and MariaDB is the production-like verification database.
- Test data: deterministic Laravel factories and `Tests\Support\Scenarios`; Faker is seeded per test when exact repeatability is required.
- Profiles: TINY, SMALL, MEDIUM, LARGE, and RACE. LARGE requires explicit command invocation and uses bulk insertion.
- Safety: `testing:data` and `TestDataSeeder` refuse production-like environments and require an isolated database. Synthetic values only; no external providers are called.
- Existing canonical authorization seeding remains the source of role/permission truth.
- Applicable implemented modules currently covered by the foundation: platform, authorization scopes, catalog products, pricing approval records, inventory stock balances, and POS shift context.
- Unimplemented workflows are intentionally not represented by fixtures.
