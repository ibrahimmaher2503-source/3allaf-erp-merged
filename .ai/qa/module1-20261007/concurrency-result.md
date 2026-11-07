# BUG-228 — Cash drawer stale writes

Date: 2026-10-07
Classification: reproduced; I4 additive schema change approved for this local repair.

## Root cause

`SaveCashDrawerAction` previously took a row lock but accepted every submitted form value without comparing it to a version displayed when the form opened. A row lock serializes concurrent transactions; it does not identify a stale form after the first transaction commits. `cash_drawers.created_at` and `updated_at` are second-precision timestamps, so two edits in the same second can share the same timestamp. The original direct-action regression reproduced a stale overwrite even with both writes fixed to the same timestamp; it failed with 0 passing tests, 1 failure, and 4 assertions before any production source or migration change.

The same gap existed in the drawer status action and initial logical-delete request. Status updates and approval-driven logical deletion also needed to advance the baseline version so a form opened before either operation could not save over the newer state.

## Repair

- Added an additive `cash_drawers.lock_version` unsigned integer column, defaulting to `1` for existing rows. New drawer creation retains its existing caller contract and uses that default.
- Update and status actions require a strictly positive integer baseline, compare it to the locked row before dependency checks, writes, or audit events, then advance the version on success. The logical-delete request compares the displayed version under a transaction and row lock; approval-driven logical deletion also advances the version.
- The drawer edit form captures its baseline in a Livewire `#[Locked]` property. Status and delete controls carry the version rendered with the row. The remediation seeder supplies a baseline only when updating an existing drawer.
- `lock_version` is not mass assignable. Trusted actions set the next value directly on the already-locked model.
- Version conflicts use a typed Laravel `ValidationException` on the `lock_version` key. The drawers component handles only that key, showing the fixed localized conflict message inline and as a toast; unrelated exceptions continue through `UserSafeError` without exposing arbitrary exception text.

## Verification

The test database was the dedicated XAMPP MariaDB schema `qa_rajh_m1_concurrency_20261007`; no SQLite, normal application schema, or production database was used. A read-only schema query confirmed `lock_version int unsigned NOT NULL DEFAULT 1` and the recorded migration row.

The focused suite passed twice after implementation; the final run reported 8 tests and 57 assertions. It verifies the unchanged create contract and server-only version field; missing, malformed, zero, stale, and forged baselines; same-second stale direct-action rejection with newer data, timestamp, and audit preserved; one-winner behavior for two parallel submissions from the same version; successful status and approval-delete version increments; stale status/delete requests with no audit or approval side effect; Livewire locked-version tamper rejection; and a visible save conflict that preserves the unsaved form value and leaves the modal open.

Commands/checks actually run:

- `php vendor\bin\phpunit tests\Feature\CashDrawerConcurrencyTest.php` — passed, 8 tests / 57 assertions, against the guarded QA schema and schema-specific temporary storage.
- `php -l` for the migration, action, model, seeder, and feature test — passed.
- JSON parse for `lang/en.json`, `lang/ar.json`, and `lang/ar-EG.json` — passed.
- `git diff --check` — passed.
- `C:\projects\erp\vendor\bin\pint --dirty --format agent`, launched with this worktree as the current directory — exited 0. The copied worktree `vendor/bin/pint` could not run because its packaged PHAR resolves an obsolete absolute path into the regression worktree; no vendor files were edited.

No full suite or browser test was run. The Livewire component behavior was exercised through the focused Laravel feature tests. No deployment, push, or merge occurred. Lead review remains required before integration.
