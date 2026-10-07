# Module 1 authentication red-team review

Date: 2026-10-07

Reviewed integration SHA: `07a514c94d106efe3516d81780ee9081ecfd86bc`

Worktree: `C:\Users\N\.codex\worktrees\m1-approvals\erp`

Branch: `hotfix/module1-redteam-approvals-20261007`

Follow-up commit: `486a13683f5d2c610581daf79ff650c5050ab644` (local only; not pushed)

Scope: session revocation, subsequent GET/POST/Livewire requests, session and CSRF rotation, repeated unauthenticated access, non-active statuses, preservation of open POS state, and SEC-005 account-enumeration behavior. Production was not inspected.

## Confirmed finding and final repair

`SEC-005` had a deterministic login-work difference in `app/Providers/FortifyServiceProvider.php::configureActions()`: the custom Fortify callback returned before `Hash::check()` for a missing or inactive account, while an active account with a wrong password performed a bcrypt check. All three cases used the same visible username error. This custom callback bypassed Laravel SessionGuard’s normal credential-attempt Timebox.

The final repair wraps the existing username lookup, non-active check, and password-hash check in Laravel 13’s `Illuminate\Support\Timebox`, using `config('auth.timebox_duration', 200000)` like the installed SessionGuard. Failed cases retain the same generic failure and wait for the configured minimum; a valid active login calls `returnEarly()`. The existing hash driver/cost and status decision remain intact. No dummy digest, account status, error text, rate limiter, grant, or lockout policy was added or changed.

## Checks and results

- The first bounded RED check across missing, inactive, and active/wrong-password accounts observed only one `Hash::check()` call; the first two paths had skipped hashing. This identified the work gap without a wall-clock benchmark.
- Final `AuthenticationRedTeamReviewTest.php`: **6 passed, 65 assertions**. A recording native Timebox verifies all three denied login cases receive the same configured duration and generic error, while preserving the existing hash-check policy. The successful-login case verifies `returnEarly()`; Laravel’s enabled 2FA middleware invokes the custom callback twice for that successful request.
- Existing `AuthenticationRegressionTest.php`: **3 passed, 35 assertions**; includes the five-attempt limiter and proves inactive-session revocation leaves the open shift, active assignment, and drawer assignment unchanged.
- Other focused checks verify inactive session revocation on dashboard GET, session-ID and CSRF-token rotation, repeated guest access, denied POS shift-opening POST without new shift/assignment rows, real Livewire update POST revocation, and injected `suspended`/`deleted` values being treated as non-active.
- PHP syntax checks, `git diff --check`, and Pint passed. The local Pint launcher is misbound to the regression worktree; the installed launcher from that worktree was used against this checkout.
- Checks used only the named disposable loopback schema `qa_rajh_m1_approvals_20261007`; no schema was dropped or recreated.

## Remaining limits and dispositions

Laravel Timebox enforces a minimum duration, not a maximum. If lookup/hash work exceeds `auth.timebox_duration`, the callback returns after that longer work; this review did not benchmark wall-clock timing or claim perfect constant-time behavior. No stale-token replay, real browser, production or remote data, external mail delivery, or full suite was exercised. Existing legacy `403` expectations were not weakened. `suspended`/`deleted` are not supported account lifecycle states; direct database values only verified the existing `status !== active` revocation rule. Registration policy M1-F01 remains ambiguous and unchanged. No account-wide lockout policy or reviewer grant was inferred or changed.

The earlier tentative fixed-digest mitigation was superseded before handoff because the repository uses differing test/runtime hash costs and may configure another hash driver. The final implementation uses the framework Timebox and retains the original hash-check path.
