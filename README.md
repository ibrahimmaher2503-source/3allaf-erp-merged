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
- SQLite for local development only

The production database, hosting, cache, queue, scheduler, storage, backup, and monitoring architecture still require owner approval.

## Local Setup

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
npm run build
php artisan serve
```

The current prepared environment already has its local `.env`, application key, SQLite database, migrations, Composer dependencies, npm dependencies, and production assets.

### Local Demo Authentication Personas

In `local` environment with `DEMO_AUTH=true`, role-specific browser verification is available via `/__demo/auth` with an allowlisted persona (`as` parameter):

- Admin (Default): `/__demo/auth` or `/__demo/auth?as=demo-admin`
- Branch Manager: `/__demo/auth?as=demo-branch-manager`
- Cashier: `/__demo/auth?as=demo-cashier`
- Reviewer: `/__demo/auth?as=demo-reviewer`
- No Access: `/__demo/auth?as=demo-no-access`

Optional redirect parameter: `/__demo/auth?as=demo-cashier&redirect=/pos`

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
