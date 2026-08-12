# TOY & JOY Phase 1

TOY & JOY Phase 1 is an Arabic-first, bilingual retail and party operations platform. It is being implemented as one Laravel modular monolith for central administration, branches, warehouses, cashiers, purchasing, inventory, customers, and party teams.

## Current Implementation Status

- Current phase: Phase 1, Foundation, Access and Operational Controls
- Current milestone: DM 1.1, Platform Foundation
- Status: In Progress
- Active task: TSK-001, Establish the Laravel Platform and Operational Baseline
- Automated tests: Not created and not run, by explicit project-owner directive

The repository now contains a runnable Laravel application and the complete implementation documentation baseline. Later business modules remain unimplemented.

## Verified Local Stack

- PHP 8.4.21
- Laravel 13.23.0
- Livewire 4.3.4
- Flux UI 2.15.0
- Tailwind CSS 4
- Vite 8
- XAMPP MySQL/MariaDB for local development, managed through phpMyAdmin

The production database, hosting, cache, queue, scheduler, storage, backup, and monitoring architecture still require owner approval.

## Local Setup

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
# Create `toyjoy_local` in XAMPP/phpMyAdmin before running migrations.
php artisan migrate
npm run build
php artisan serve
```

The current prepared environment uses its local `.env` and the `toyjoy_local` MySQL/MariaDB schema, with migrations, Composer dependencies, npm dependencies, and production assets prepared locally.

### Production-safe initial data

Without a setup-data path, the default seeding path creates only the canonical
role/permission catalog and first system administrator. A second, opt-in stage
can load every Production setup area from an owner-approved private JSON file;
it never invents missing values or loads the example template.

Supply the four `PRODUCTION_ADMIN_*` deployment values documented in
`.env.example`, then run:

```bash
php artisan db:seed --force
```

The seeder is transactional and idempotent. Missing or conflicting administrator
identity values fail closed. Re-running it does not reset the existing
administrator password.

For a complete owner-data load, copy
`database/seeders/production-setup.example.json` to a private path outside the
web root, replace every `__REPLACE__` value, review it, calculate its SHA-256,
and set:

```dotenv
PRODUCTION_SETUP_DATA_PATH=/absolute/private/production-setup.json
PRODUCTION_SETUP_DATA_SHA256=<approved-file-sha256>
PRODUCTION_SETUP_USER_PASSWORDS='{"MAKER":"<16+ characters>","APPROVER":"<16+ characters>"}'
```

Then clear cached configuration and run the normal seeder:

```bash
php artisan optimize:clear
php artisan db:seed --force
```

The optional stage covers company, branches/stores, users/roles/scopes,
payments/tax/numbering/printers, customer policy settings, catalog/variations,
suppliers/SKUs, approved prices, controlled opening inventory, customers, and
Party booking drafts. Price and opening-stock approval require different maker
and approver usernames and run through the normal guarded actions. Keep customer
and Party arrays empty unless genuine activity requires them. Remove seeding
passwords from the runtime environment after the run, rotate initial passwords,
configure MFA, and complete the reconciliation workflow in `docs/54`.
After removing `PRODUCTION_ADMIN_PASSWORD` and
`PRODUCTION_SETUP_USER_PASSWORDS` from the runtime environment, run
`php artisan optimize:clear && php artisan optimize` so cached secrets are not
retained.

## Delivery and Performance Guardrails

The repository identity is the Git root, not a server port:

```bash
git rev-parse --show-toplevel
```

The expected project name is `toy-joy-phase-1-documentation`, configured as `PROJECT_NAME` in `.env`. AGY runs through `scripts/ai/run-gemini.sh`, which injects the verified root/project identity and passes `--add-dir` for that root.

Composer install/update configures the tracked `.githooks/pre-commit` hook. It runs Pint on staged PHP files, PHPStan, locale-key parity, and staged whitespace checks. PHPStan uses `phpstan-baseline.neon` for 189 pre-existing findings; new findings fail the hook.

Local/staging observability is enabled through `.env` controls:

- `Model::preventLazyLoading()` outside production.
- `QUERY_BUDGET_ENABLED=true` and `QUERY_BUDGET=100`; a request aborts above the budget.
- `SLOW_QUERY_LOG_ENABLED=true` and `SLOW_QUERY_MS=100`; logs go to `storage/logs/slow-queries-*.log`.
- `DEBUGBAR_ENABLED=true` for the dev-only Debugbar package.

For realistic volume work, create a disposable MySQL/MariaDB schema in XAMPP/phpMyAdmin first, then use it only:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_performance_20260809 APP_ENV=local QUERY_BUDGET_ENABLED=false \
  php artisan migrate:fresh --force
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_performance_20260809 APP_ENV=local \
  php scripts/seed-performance-fixture.php --products=50000 --movements=1000000
```

Drop `toyjoy_performance_20260809` from phpMyAdmin after verification. The fixture refuses non-empty product/movement tables and must never be seeded into shared Demo data.

The `toy-joy-milestone-watcher` remains paused while an interactive writer owns this repository. Resume it only after the worktree is clean and the interactive session has handed off ownership; it is read-only with respect to `.ai/`, and only one agent may modify `.ai/` at a time.

## Application Foundation

- Official Laravel Livewire starter foundation
- Laravel Fortify session authentication and recovery flows
- Passkey and two-factor foundations supplied by the official starter
- Responsive Flux application and authentication shells
- Locale-aware document direction for Arabic RTL and English LTR
- Light-first restrained operational interface
- Vite production asset pipeline

## Documentation

- `PRODUCT.md`: product and interface context
- `docs/01-project-brief.md` through `docs/15-security-checklist.md`: product, requirements, architecture, milestones, acceptance, manual verification, and security
- `.ai/`: current milestone, progress, blockers, decisions, UI inventory, handoff, and verification evidence
- `TASKS.md`: milestone-ordered implementation backlog
- `AGENTS.md`: mandatory rules for future coding agents

The PRD governs functional behavior. The Implementation Plan governs milestone order, Delivery Criteria, and Phase Gates.

## Technology Rules

Use Blade, Livewire, Flux UI, Tailwind CSS, Vite, and limited Alpine.js inside one Laravel application. Do not introduce Filament, Inertia.js, Vue, React, a separate frontend, an ordinary-screen API, or microservices.

## Verification Policy

Automated tests remain deferred. Do not create or execute PHPUnit, Pest, browser, or end-to-end suites unless the owner explicitly changes that directive. Record actual command checks, manual browser verification, role checks, data-integrity checks, print checks, responsive RTL/LTR review, offline checks, backup and restore verification, and UAT evidence in `.ai/TEST_RESULTS.md`.

## Start Here

Read `AGENTS.md` in full, follow its required reading order, confirm `.ai/CURRENT_MILESTONE.md`, and work only on tasks linked to DM 1.1 until its exit criteria are accepted.
