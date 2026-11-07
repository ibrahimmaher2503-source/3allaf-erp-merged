# Approvals QA result — 2026-10-07

BUG / SCOPE:
- BUG-222 (MEDIUM): reported tax approval review omitted the requested setting values and before/after context. Reproduced locally and fixed in the review presentation.
- M1-F05: the report asks whether settings changes require independent approval and who may decide a request. Existing requester-separation behavior is preserved; the product-level reviewer-role mapping remains ambiguous and is not changed here.

ROOT CAUSE:
- `ApprovalRecord.limit_context` already carries the canonical `before` and `proposed` values. The approval inbox rendered source/requester/state/reason only, so a reviewer could not inspect the change before deciding.
- Branch deletion dependency context is now rendered only from a fixed set of request-time count fields. This expects the coordinated branch request change to populate those same `proposed` keys.

EVIDENCE:
- Before the presentation change, the focused rendered Livewire test failed both intended assertions: tax review did not contain `VAT-OLD`; branch review did not contain `Request-time dependency context` (2 tests, 2 assertions, 2 failures, 0 errors; XML under the isolated temp storage).
- After the change, authorized reviewer rendering includes allowlisted tax fields in Before/After columns. Private tax-number/policy-note/token and unknown fields remain absent.
- Branch review displays only integer counts for linked stores, active stores, active selling-store mappings, active cash drawers, and active user-scope assignments. IDs, names, and unknown payload fields are omitted.
- The existing visible query and `view` policy remain in the selection path. An out-of-scope reviewer cannot open the request. A requester with the decision permission still cannot decide their own request; the record remains pending.
- Rendering leaves the stored source hash and submitted context unchanged. Existing `ApprovalRecord` rejects updates outside named transition actions; the source approval action also checks canonical metadata/hash before applying effects.

FILES INVOLVED:
- `app/Modules/Platform/Support/ApprovalPresentation.php`
- `resources/views/platform/system/approval-inbox.blade.php`
- `tests/Feature/Platform/ApprovalInboxPresentationTest.php`
- Shared locale additions are coordinated in the audit agent's locale change and are not edited in this branch.

FIX:
- Added source-aware review details with a strict tax field allowlist and strict branch count allowlist, using the existing viewer redactor for tax values.
- Added responsive tax Before/After table and branch dependency summary to the existing review modal. No arbitrary approval JSON is rendered and no approval grants/policies were changed.

TEST ADDED/UPDATED:
- Focused approval presentation feature test covering safe tax Before/After display and redaction, branch count allowlist, self-decision rejection, source/scope query protection, required rejection reason, and read-only presentation payload.

COMMANDS RUN:
- Focused RED: `php vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/Feature/Platform/ApprovalInboxPresentationTest.php` with process-scoped explicit QA environment, `DB_DATABASE=qa_rajh_m1_approvals_20261007`, and temporary QA storage. Result: 2 tests / 2 assertions / 2 failures, both expected presentation omissions.
- Focused GREEN: same command with the final five-test file. Result: 5 tests, 37 assertions, 0 failures, 0 errors.
- Pint formatting: same installed Pint package from its original regression worktree, invoked against the current approvals worktree's two PHP files. Result: exit 0. The local copied Pint proxy cannot start because its PHAR resolves an embedded autoload path in the regression worktree.
- `git diff --check`: passed.
- The named XAMPP schema `qa_rajh_m1_approvals_20261007` was the only test schema used; all 113 migrations were applied in local migration mode. No normal app database or secrets were used.

RESULT:
- BUG-222: FIXED + VERIFIED in the focused local rendered regression.
- M1-F05: AMBIGUOUS REQUIREMENT for business assignment of approval roles; existing requester-separation behavior remains unchanged.

RISKS:
- The review component intentionally shows only known, reviewed fields. New setting fields require an explicit allowlist update.
- Branch counts appear only when the coordinated branch request path stores the agreed count keys. Their semantics remain request-time context; this display does not imply cascade deletion or new dependency policy.
- This focused scope did not re-test concurrent approval races or audit event completeness; those paths were inspected and remain with the concurrency/audit investigators.

FOLLOW-UP:
- Integrate the branch request-time count payload and shared locale labels before final combined QA.
- Keep M1-F05's reviewer-role assignment open for owner decision; do not infer or add sensitive grants.
