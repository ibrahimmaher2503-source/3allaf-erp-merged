# Module 1 investigation and integration plan - 2026-10-07

Source: 20-page remote browser-only report dated 2026-10-06, including evidence images, partial/blocked coverage, policy questions and cleanup notes. Initial matrix: 9 reported bugs (4 MEDIUM, 5 LOW), 22 non-BUG findings, and 18 preserved PASS observations. There is no confirmed P0 failure; security/concurrency coverage is incomplete. Report-only claims remain evidence until locally reproduced. Production is not inspected.

Exactly ten subagents were launched concurrently using `gpt-6-luna`, reasoning `xhigh`, fresh isolated context. Lead is the eleventh participant and final reviewer. No nested agents allowed. Native worktree tools used where available; regression preserved its valid Git fallback after native availability failure.

| Agent | Domain / ownership | Primary IDs |
|---|---|---|
| auth | Fortify and inactive-session lifecycle | BUG-221, BUG-226, F01, F13 |
| authorization | Direct action/export/scope/last-admin checks | F04, F06, F10, F14 |
| branches | Branch/store dependencies and lifecycle | BUG-224, F02, F03 |
| tax | Tax form/request/effect validation, settings coverage | BUG-223, F08, F15-F17 |
| approvals | Safe meaningful review payload and maker/checker | BUG-222, F05 |
| concurrency | Atomic drawer stale-write protection | BUG-228 |
| audit | Stored event scope, safe labels and metadata | BUG-229, F07, F21 |
| pos | Canonical cashier route/shift contracts and retained QA state | BUG-227, F22 |
| localization | Named validation errors, masters bilingual/list coverage | BUG-225, F18, F19 |
| regression | Shared guarded test boot; numbering and cross-cutting coverage | F09, F11, F12, F20 |

Workflow: each agent traces real callers/docs, reproduces with meaningful focused RED tests, applies minimal safe repair, proves GREEN, formats changed PHP, reports precise evidence/limitations, and commits. Lead inspects every diff, reconciles overlaps and integrates verified changes only. The same ten agents then review different boundaries of the combined solution in parallel, read-only, trying to disprove it. Confirmed regressions return to their owning implementer. Lead runs final appropriate full suite/build/config/route/lint/static checks and maps every original matrix finding to a final status plus manual retest checklist.

Sensitive boundaries: no production, push, deployment, real-data deletion, credential change, role/grant policy invention or auto-closing financial shifts. Each disposable XAMPP MySQL schema is named before migration/destructive commands. No application SQLite, dependency upgrades or arbitrary tooling. A monotonic drawer lock_version requires a minimal additive migration; combined class escalated D3 to I4 on confirmed same-second stale-write risk. No generic locking framework.
