# TOY & JOY Mandatory Agent Instructions

This file governs every AI coding agent working on TOY & JOY Phase 1. The repository contains the documentation baseline and a Laravel 13 application foundation. Project state is dynamic. Always read `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, and the latest relevant `.ai/SESSION_SUMMARY.md` entry for the actual state.

Repository-wide mandatory policy: every Codex account and every user working through SSH, in this worktree or any future worktree based on this commit, must follow `.ai/ERP_CHANGE_POLICY.md`, including its classification, scope-escalation, verification-budget, batching, and reporting rules. No user-specific workflow or nested instruction file may weaken or bypass that policy; additional instructions may only be equally or more restrictive.

## Current Implementation Baseline

- PHP 8.4.21 and Laravel 13.23.0
- Livewire 4.3.4, Flux UI 2.15.0, Tailwind CSS 4, and Vite 8
- Laravel Fortify authentication with passkey and two-factor foundations from the official starter
- XAMPP MySQL/MariaDB managed through phpMyAdmin for local development; the production database remains an owner decision
- Responsive application and authentication shells with locale-aware Arabic RTL and English LTR direction
- Automated tests remain Not Created and Not Run by explicit project-owner directive

## Post-Hotfix51 Development Operating Model

- GitHub repository: `IS-Intelligent-Solutions/rajeh-erp`.
- Git/server account: `rajeh_ahmed`. This identifies the operating account, not a change to the configured Git commit author.
- Git remote: `git@github-rajeh:IS-Intelligent-Solutions/rajeh-erp.git`.
- `main` is protected and read-only unless the owner explicitly authorizes a merge. QA batches use one working branch per module (`fix/module1-qa`, `fix/module2-qa`, etc.), with one clear commit per bug. Do not create or push per-bug, publication, result, review or red-team branches.
- During QA batches, subagents investigate/review and return BUG, ROOT CAUSE, FILES, RECOMMENDED CHANGE, TEST TO RUN and RISK; the lead applies changes to the module branch. An experimental worktree/branch is temporary and local only; preserve and incorporate its needed work, then remove it. Push only the module branch. Preserve all needed history/dirty work before deleting obsolete refs; never delete or modify main/master.
- The recorded GitHub/main baseline is `4182c08e108da7cb3ed1a119470edb65a3681392` (`4182c08`), and the main worktree is `/home/rajeh/worktrees/rajeh-erp-main`. Start future work from current `main` in a new dedicated branch/worktree; this recorded commit is the post-Hotfix51 reference, not a permanent pin for future tasks.
- Use `feature/*` for features, `hotfix/*` for fixes, and `release/*` only when needed. The owner-requested operational documentation task uses `docs/post-hotfix51-operational-baseline`.
- Commit completed work, push only the task branch, and merge to `main` only when the work is ready and the merge is authorized. Never mass-push historical branches or use `--all`/`--mirror` to publish them.
- Production's recorded current application release is Hotfix51, application commit `b709193815c548bb0100a0b641171c5af7aaf314`, at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`. The application commit and the development documentation baseline are distinct.
- Keep the established production deployment workflow unchanged unless explicitly requested. A development commit, branch push, or merge does not authorize deployment.
- The old `/home/rajeh/worktrees/rajeh-erp-hotfix50` worktree and Hotfix51 branch identify completed historical work, not the starting point for future development. Preserve their history and `.ai/HOTFIX51_CHECKPOINT.md` evidence.

## CodeGraph and Bounded Structural Analysis

- Tool baseline: CodeGraph `1.6.0` and ast-grep `0.45.3`.
- CodeGraph is the preferred tool for semantic analysis, relationships, and change-impact analysis. Use ast-grep for bounded structural searches within the affected source paths.
- The current main worktree `/home/rajeh/worktrees/rajeh-erp-main` must have its own CodeGraph index. Every new worktree needs its own index; never reuse an index bound to another worktree.
- Initialize each new worktree once with `codegraph init <exact-root>`, substituting its exact absolute root. Apply and verify indexing exclusions before initialization; initialize the main worktree once if its own index is absent.
- Ordinary changes use watcher/connect-time synchronization. Run `codegraph sync <exact-root>` only when needed, such as when the watcher was unavailable or the index is stale; use the exact worktree root and avoid routine full synchronization.
- Never index secrets, `.env` or environment overrides, credentials, keys, sensitive configuration, `storage`, uploads, backups, database dumps, logs, `vendor`, `node_modules`, or build/cache paths. Exclusions apply to every worktree, including linked paths.
- This documentation-only task records the policy; it does not initialize an index, synchronize code, or change tool configuration.

### Windows Local CodeGraph Workflow

- The local checkout is `C:\projects\erp`. Its native CodeGraph 1.6.0 index lives in the ignored `.codegraph/` directory; `codegraph.json` supplies exclusions for initialization, synchronization, and watching.
- This machine's project-local `.codex/config.toml` starts native CodeGraph with this exact root. It overrides the older global server connection for this project. Keep machine-specific connection settings out of Git.
- Before trusting any result, confirm its paths belong to the current checkout. A connected server returning another project is unsuitable; use the project-scoped CLI until Codex reloads the local connection.
- In the native MCP connection, use `codegraph_explore` with `projectPath` set to the verified root to gather source and dependencies before an edit. The CLI remains available without an app restart.
- For a bug, begin with `codegraph query "<symbol>" --path "C:\projects\erp"`, then `codegraph callers "<symbol>" --path "C:\projects\erp"` and `codegraph impact "<symbol>" --path "C:\projects\erp"`. Read the actual affected code before editing, and follow the existing verification directive.
- Check index health with `codegraph status "C:\projects\erp"`. The native MCP watcher and connect-time reconciliation keep it current; use `codegraph sync "C:\projects\erp"` only when the watcher was unavailable or status indicates stale files. Each future checkout/worktree needs its own initialization and root-specific connection.

## Database Direction

- Use only the XAMPP MySQL/MariaDB server through phpMyAdmin for local, development, staging, and test database operations.
- SQLite is prohibited: do not create SQLite files, configure SQLite connections, run migrations or seeders against SQLite, or add SQLite test/backup fixtures.
- Use a dedicated MySQL/MariaDB database for each disposable workflow and record its name before running destructive database commands.

Inspect the current-state files identified by `AI_INDEX.md` before making any change.

## Task-Aware Required Reading

Before changing code or documentation:

1. `AGENTS.md`
2. `AI_INDEX.md`
3. Resolve `.ai/CURRENT_TASK.md` and `.ai/CURRENT_MILESTONE.md`.
4. Read only the current task section in `TASKS.md`.
5. Follow that task's routing entry in `AI_INDEX.md`.
6. Read only the referenced blockers, decisions, screen IDs, requirements, acceptance criteria, and documentation sections.
7. Expand reading only when a real dependency or contradiction requires it.

The PRD is authoritative for functional behavior. The Implementation Plan is authoritative for Development Milestone order, Delivery Criteria, and Phase Gates. Never resolve a conflict silently; record it in `.ai/DECISIONS.md` with status `Requires Owner Decision`.

## Mandatory Technology Direction

- One Laravel modular-monolith repository and one deployment.
- PHP and Laravel on the backend; Laravel sessions, Form Requests, policies, gates, transactions, queues, and scheduler where justified.
- Blade layouts and templates; full-page Livewire components for interactive screens.
- Flux UI components first, Tailwind CSS for styling, Alpine.js only for small browser behaviors, and Vite for assets.
- Browser-based Arabic-first application with full RTL and English LTR support; restricted POS continuity as a PWA.
- Admin, Operations, POS, and Auth layouts remain inside the same Laravel application.

Do not adopt Filament, Inertia.js, Vue, React, Angular, Next.js, Nuxt, a separate frontend, a separate ordinary-screen API, GraphQL, a full SPA, headless architecture, or microservices.

## Working Rules

- Inspect the entire repository before concluding that a feature is absent.
- Work on only the current milestone and its linked tasks. Do not advance before its Delivery Criteria and Phase Gate are satisfied.
- Preserve every Requirement ID and business rule. Do not delete, merge away, or silently change a requirement.
- Record missing facts as assumptions, open decisions, or blockers with impact and affected milestones.
- Deliver the smallest reviewable vertical slice with a complete UI. A backend-only business feature or placeholder UI is not complete.
- Use Laravel conventions. Do not add repository patterns, generic services, base classes, traits, internal packages, event-driven architecture, or generalized workflow/form/table builders without a documented need.
- Apply authorization on the server through policies and gates; hiding a control is not authorization.
- Use database transactions for financial, inventory, wallet, loyalty, gift-card, shift, and document-approval operations. Use row locks or equivalent concurrency control where races can corrupt results.
- Approved documents are immutable. Correct them through referenced reversal, return, cancellation, or adjustment documents. Use logical deletion only where permitted.
- Keep retail products and party services/assets out of the same order or invoice. Keep Product Wallet and Party Wallet ledgers, visibility, settlement, and reporting separate.
- Never claim implementation, verification, or testing that did not occur. Update `.ai/` status files after every task.
- Every agent must append a factual entry to `.ai/SESSION_SUMMARY.md` at the end of each session that changes the repository or project state. Include the session date, task, work completed, verification actually run, remaining blockers or next action, and whether code, tests, browser checks, commits, or pushes occurred. Do not rewrite prior session entries except to correct a factual error.

## Reuse Before Custom Build

Do not build from scratch when Laravel, Flux UI, or an approved mature package provides the capability. This applies to authentication, password reset, email verification, roles/permissions, audit logging, Excel import/export, PDF generation, barcodes, backups, media handling, uploads, tables, pagination, filtering, sorting, date/time pickers, searchable selects, modals, drawers, toasts, alerts, charts, sidebar, breadcrumbs, form controls, loading states, and empty states.

If a standard component cannot meet a documented requirement, record why, implement the smallest extension, and do not create an internal UI library. Package names and versions require compatibility and maintenance review at implementation time and a decision-log entry.

## UI Rules

- Reuse the common application shell, navigation, page header, breadcrumbs, filters, data table, form sections, summary cards, status badges, dialogs, state panels, details, status timeline, audit panel, and print layouts.
- Every relevant screen must handle default, loading, empty, error, success, disabled, permission-denied, validation, confirmation, and unsaved-change states.
- Large tables use server-side pagination, filtering, sorting, indexed search, eager loading, and clear row actions; never load an unbounded dataset into the browser.
- Forms use logical sections, explicit bilingual fields, server validation, inline errors, required indicators, duplicate-submission protection, and confirmations for sensitive actions.
- All screens must be responsive and support Arabic RTL and English LTR. Printed documents must be verified in every required format.
- POS is a dedicated, fast, barcode-first, keyboard- and touch-friendly Livewire interface. It must preserve cart content on errors and display branch, selling store, drawer, shift, totals, and connectivity status.
- Alpine.js or small TypeScript may be used only for barcode events, shortcuts, print handling, IndexedDB, service worker, offline queue, connectivity detection, and local device integration.

## Automated Testing and Browser-Control Directive

The current task verification directive is determined by `.ai/CURRENT_TASK.md`, `.ai/DECISIONS.md`, and explicit owner instructions. Automated tests are deferred unless those current sources explicitly authorize a named scope.

- Do not create PHPUnit, Pest, unit, feature, integration, browser, end-to-end, Playwright, or Cypress tests.
- Do not run `php artisan test`, Pest, PHPUnit, Playwright, Cypress, or any automated suite.
- Automated tests are not a Definition of Done condition under the current directive.
- During future implementation, perform scenario-based manual browser verification, permission checks, data-integrity checks, print checks, responsive/RTL/LTR checks, backup/restore checks, and UAT as specified in `docs/14-test-plan.md`.
- Record only actual results in `.ai/TEST_RESULTS.md`.

### Historical Local Visual Verification Exception - 2026-08-03

The project owner explicitly authorized browser-control tooling, screenshots, and authenticated local-only visual verification for the named Phase 1 work at that time. This historical exception does not apply to later tasks unless their current authorization explicitly says so. It does not apply to production and does not authorize final TSK-008 grants.

### Historical TSK-008 Automated Verification Exception - 2026-08-03

The project owner explicitly authorized focused local PHPUnit feature tests and Playwright checks for TSK-008 only. This named, dated exception is not generalized to other tasks, production changes, commits, pushes, or authorization behavior for modules that did not exist.

Browser-control or automated testing is prohibited unless the active task has explicit current authorization.

## Required Task Closure

For each implementation task: confirm linked requirements and acceptance criteria, complete all listed UI states, apply validation and server authorization, review audit and concurrency requirements, perform the prescribed manual verification, update documentation and `.ai/` files, append the required factual session summary, and leave no unresolved critical blocker.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

</laravel-boost-guidelines>
