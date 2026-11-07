# Final Remaining Audit Evidence — 2026-10-03

## 2026-10-03 — Final Full-System Audit Closure

**Status:** Remaining authorized browser/output audit closed; confirmed defects repaired and deployed. **Readiness:** Operational application verified; unconditional trading/client handover NOT approved pending cashier/shift/opening configuration, populated financial/inventory UAT in disposable XAMPP MySQL/MariaDB, device/transaction-print/offline acceptance and next scheduled backup evidence.

**Application:** `599a5084f7c1c2bb0aee5d6a67fc5df3d9dfd0be`, tree `51129f22539a3ceebc14e6b1d0171896f9131886`, version `0.1.22-audit-final3`; `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final3-24f1c5a`. UI/source fixes merged via `a4d2604`, PDF repair via `66605f1` and cost disclosure repair via `10957d6`, and pushed to main; later report-only closure does not rebuild the artifact.

**Coverage:** 386 unique authenticated Arabic/English desktop/mobile renders; 23 safe-control assertions; shipped UI affected checks; focused independent cost-view export confidentiality checks; 25 output requests and 42 final continuation assertions; XLSX/XML/PDF integrity; isolated repaired bilingual PDF visual rendering. Completed initial source/CodeGraph/database/backup coverage remains preserved. No suite, broad rescan, CodeGraph reinitialization/sync or repeated passing browser matrix. One Vite build; PDF artifact reused it.

**Access:** Exactly two approved sessions for existing administrator ID 1 and normal user ID 3 were created without password-hash inspection or password/identity/grant changes, then immediately revoked after authenticated coverage. Private cookies/tokens deleted; no further session created. No production business fixtures/transactions; output GETs produced their intended operational audit events.

**Release:** New immutable PDF artifact SHA-256 `35eef98261e033575f4662a99a070b5d0b0ff03fce6654d5b17bfac3905f9286`; manifest `a0c96bbd206480b75db8e05c8b8bc5cfeded11af82b8583f3dd3b8d570c3aab7`; 14,816 files verified. Original e6d2d1e and af6874e archives preserved. Journal `/var/lib/toyjoy/audit-final3-599a508`; exact rollback target 24f1c5a. Final-root cache/autoload checks and the single final compact smoke passed, maintenance off, services active, failed jobs zero, cron/schedules present, `/up`/`/login` 200 and unauthenticated `/admin` 302.

**Report:** `docs/audits/RAJEH-FULL-SYSTEM-AUDIT-2026-10-03.md`; evidence `.ai/evidence/full-system-audit-2026-10-03/`. No milestone, phase gate, UAT or device sign-off advanced. Remaining acceptance constraints are documented, not represented as passing checks.

Current receipts: `authenticated-coverage.json`, `safe-interactions.json`, `remaining-outputs-and-guard.json`, `pdf-verification.json`, `session-revocation.txt`, `final-release.json`, `final-smoke.json`, `final-http.json`, `final-services.txt`, `final-cron.json`. `affected-live.json` preserves the initial verifier-interceptor failure; its diagnosed failed assertion passed in `remaining-outputs-and-guard.json`. Earlier status below remains historical only.

**Late D3 repair:** Confirmed product XLSX/PDF cost disclosure when export is granted without the independent cost-view permission. Server output now omits the cost header/value unless the existing cost-view gate allows it. Focused visible/hidden-cost shapes, XLSX content and PDF generation passed; no roles, permissions, grants, identities or sessions changed. Dedicated final3 artifact activated; prior release receipts are preserved. The final target compact smoke passed once after this code change.

## Previous Evidence Checkpoint (Historical)

# Remaining Audit Evidence — 2026-10-03

These are continuation results. Original scans/evidence in audit commit `e6d2d1e` are preserved and not repeated.

| Check | Actual result |
|---|---|
| Existing production identity | Already active e6d2d1e; old activation not repeated |
| Outstanding caller-cwd backup regression | Failed on stale staging path; diagnosed AUD-005; affected regression passed after cache repair |
| Six repaired runtime path assertions | PASS: log, compiled views, session, file cache, private files, backup temp all use final release path |
| Prior deployment's missing compact smoke | PASS: maintenance off, PHP-FPM/cron active, no failed queue jobs, three backup schedules, `/up` and `/login` 200 |
| Arabic login desktop, 1440px | PASS layout/direction/no page error/no page overflow; missing separator translation found |
| English login desktop, 1440px | PASS layout/direction/no page error/no page overflow; email/username wording mismatch found |
| Arabic login mobile, 390px | PASS layout/direction/no page error/no page overflow; separator defect visible |
| Source login separator repair | PASS JSON and translation resolution; changed Blade/compiled PHP; isolated rendered ar/ar-EG/en HTML |
| Authenticated Admin/User browser | NOT RUN: explicit temporary-session approval pending after broader helper rejection |
| Persisted financial/workflow/concurrency/print/offline/device acceptance | NOT RUN by this continuation; no coverage inferred from public login |
| Fresh CodeGraph analysis, broad lint/tests/integrity scans | NOT RUN again; completed original results preserved |

Public screenshots and DOM-derived text/geometry are preserved in this directory; no credentials, CSRF values or cookie values are included. Three-locale candidate HTML remains task-private in `/tmp/rajeh-audit-continuation/evidence` because it contains temporary unauthenticated CSRF markup. No production business or permission records were changed.

The render verifier initially failed because the isolated worktree did not contain a Vite manifest. Its correction reused existing release public assets and added explicit nonzero failure handling. Only the affected render verifier resumed. No Vite build or dependency update inside the application occurred.

Cached Chromium and isolated Playwright Core are browser-control tooling, not an automated test suite. No PHPUnit/Pest/Playwright/Cypress test runner was run and no test suite was created.

Source repair commit `b04924d4c973580249aa999adab34078935cefb3` is pushed only on the preserved audit branch; it is not merged/deployed.
