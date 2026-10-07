# Authentication QA result

Date: 2026-10-07  
Scope: BUG-221, BUG-226, M1-F01, M1-F13  
Worktree: `C:\Users\N\.codex\worktrees\m1-auth\erp`  
Branch: `hotfix/m1-auth-qa-20261007`  
Source baseline: `a01af760a2102dae4993aaa47a47e7c6847a11f3` (task source: `e693ab8c2e86b6e4e8ef628aacef2aa3d8ae987e`)  
Production: not inspected; local only.

## Findings

| ID | Classification | Evidence / result |
|---|---|---|
| BUG-221 | NOT REPRODUCED locally | Fortify registers `POST /login` with `ThrottleRequests:login`; its custom limiter allows five requests per minute for normalized username plus IP. The QA PDF page 6 screenshot shows an empty password and the browser's required-field prompt, so that captured attempt never reached Laravel. A focused Laravel POST regression and a local browser session both sent non-empty credentials: the first five returned the ordinary credential-mismatch response, and the sixth showed the localized 429 page with retry countdown. No throttle source change was needed. |
| BUG-226 | FIXED + VERIFIED locally | Login already rejects non-active accounts, but an existing web session was not rechecked before route authorization; the next request hit `Gate::before` and returned 403 without clearing the session. Added `RevokeInactiveUserSession` to the web middleware group. It logs out inactive/non-active web users, invalidates their session, regenerates the CSRF token, and redirects to login. The regression proves the request becomes a guest while the open POS shift, active shift assignment, and drawer assignment remain unchanged. |
| M1-F01 | AMBIGUOUS REQUIREMENT | Fortify public registration is enabled and the login page links to signup, while the product docs/current task do not specify whether self-registration is allowed. Existing behavior remains pending owner policy. |
| M1-F13 | COVERAGE VERIFIED locally; external mail delivery NOT TESTED | A focused test fakes Laravel notifications, confirms the reset notification and token, submits the token through Fortify's reset endpoint, and verifies the password changed. This proves app dispatch and reset behavior; it does not prove SMTP/provider delivery. |

## Root cause and change

`BUG-226` was reproduced before the production-code change: the focused test expected a redirect to login after an authenticated user's database status changed to inactive, but the route returned 403. The inactive-user check existed at login and in authorization gates; it was missing from the request middleware that runs for an already-authenticated web session.

The minimal fix is in `app/Http/Middleware/RevokeInactiveUserSession.php` and its web middleware registration in `bootstrap/app.php`. No role grants, POS closing code, migration, dependency, `.env`, or production state changed.

## Verification

- Guarded harness on XAMPP schema `qa_rajh_m1_auth_20261007`; schema was confirmed absent before creation. Migrations completed under explicit local QA environment values. Read-only check confirmed 113 migrations and 167 tables in this schema. The first migration boot attempt stopped before migration because the isolated view-cache directory did not exist; after creating schema-specific framework cache/session/view directories and setting the compiled-view path under TEMP, migration completed.
- RED: `php vendor/bin/phpunit tests/Feature/QA/AuthenticationRegressionTest.php` reported 3 tests, 2 passed, 1 failed, 30 assertions. The sole failure was the expected `403` instead of login redirect for an existing inactive-user session. The same RED run confirmed the fifth invalid login response and sixth-attempt 429, and completed the fake-mail password reset/token flow.
- GREEN: the same focused command after the middleware fix reported 3 tests passed, 35 assertions, 0.920 seconds. The test uses `DatabaseTransactions` so fixtures roll back against the already migrated named schema.
- Local browser: opened `http://127.0.0.1:8136/login` with a temporary worktree-only manifest to render the page. Submitted six non-empty form attempts for one username from the same loopback IP. Attempts 1–5 displayed the credentials-mismatch message; attempt 6 displayed `تم الوصول للحد الأقصى (429)` and a retry countdown. Server was stopped and the temporary `public/build/manifest.json` was removed. Cache and sessions used schema-scoped TEMP storage; no user row was created by the browser.
- `php -l` passed for `bootstrap/app.php`, `app/Http/Middleware/RevokeInactiveUserSession.php`, and `tests/Feature/QA/AuthenticationRegressionTest.php`.
- Pint exited successfully on all three changed PHP files using the working shared launcher at `C:\Users\N\.codex\worktrees\m1-regression\erp\vendor\bin\pint`. The local `vendor/bin/pint` launcher is unusable because its copied PHAR resolves an embedded path in the regression worktree; no dependencies were changed to repair that local tooling issue.
- `git diff --check` passed. No full suite was run by this investigator; the lead owns integrated suite verification.

## Remaining risks and follow-up

- Registration policy remains an owner decision; no auth behavior was changed for M1-F01.
- Real SMTP delivery/provider configuration remains outside this local fake-notification coverage.
- The configured 429 retry duration is per limiter window and was visible in the local page; it was not held open for an additional expiry-time browser retest.
- Lead review and integrated regression/red-team verification remain pending.
