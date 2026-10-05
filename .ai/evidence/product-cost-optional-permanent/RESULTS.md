# Permanent optional Product Card cost — focused verification

Date: 2026-10-05. Source baseline: `13a2c5efaae3038706e98197463805d8d24cba61`.
Production baseline: `3821af553daca932e36c8a49ab78f911e1cf2379`, `0.1.22-qa-phase2`, `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-qa-phase2-3821af5`.

Owner explicitly authorized this hotfix, focused automated/browser checks, clean merge, push and production deployment. Owner additionally authorized socket-only disposable native MariaDB for this named task because XAMPP is absent. Disposable DB: `qa_rajh_20261005173119_24094`; no production fixture/write used.

## Root cause and correction

Livewire independently required average_cost on final saves unless a previous saved cost existed. ProductPricingContract independently rejected null/empty cost, and Product Card workbook import had a third mandatory amount regex. The prior preservation repair only covered existing saved costs. Shared optionalCost/costRules now define the authoritative Product Card input rule; final/draft actions and workbook import use that same contract. Unauthorized cost input is excluded before form validation and ignored before action validation. Existing blank/omitted edits preserve the locked stored cost. Cost remains editable only with the existing cost permission; create no longer exposes it without permission. Identity help no longer calls cost required.

## Actual results

- A PASS: omitted/blank/null action edits and browser edits retain 12.34; Arabic blank final-save/reload also passes.
- B PASS: no-cost product final edit and browser reload retain true null.
- C PASS: new complete no-cost product finalizes, including Admin and costless Editor browser creation; database readback confirms null.
- D PASS: entered 87.65 action cost and 45.67 browser cost persist exactly after reload/readback. Existing master schema precision remains two places; purchasing snapshots retain four places.
- E PASS: genuine non-super-admin disposable role has create/edit/view and no cost permission. Hidden-cost final edit/create succeed, forged action input is ignored, existing cost remains 87.65 after browser edit.
- F PASS: receiving from null master cost snapshots previous_product_cost=null, unit_cost=12.1234, total=24.2468; destination quantity=2, WAC=12.1234, value=24.2468. Subsequent Product Card maintenance does not change any purchase/receipt/valuation/approval table snapshots. Opening inventory still safely rejects absent explicit/master cost without creating a document. No purchasing, inventory, pricing, report, export or approval accounting implementation changed.
- Draft completion, explicit zero, invalid entered cost rollback and stale edit rejection PASS.
- Real Product Card XLSX import: empty/valid costs accepted, negative cost rejected; null/exact stored values confirmed.
- Focused PHPUnit: 3 tests / 24 assertions PASS, 19 ms. Cost-required expectation corrected to the new approved rule.
- Affected PHPStan/Larastan: no new findings; 10 baseline, 9 current pre-existing findings. No suppression/baseline changes.
- Affected PHP syntax and Blade compilation PASS. Browser has no page exceptions or HTTP 500 errors.
- First browser harness counted empty Flux error containers; corrected the assertion to visible nonempty errors, resumed only browser work against the same DB. Backend/passing unit/static checks were not repeated.

No full Deep Audit, Fast Gate or Release Gate ran. No migration, dependency installation, frontend asset build, historical accounting rewrite, production business transaction or permission change.

## Production delivery

Runtime `1d27f7b33b1536c10ddc7083a5eac452ce1b9c06` committed/pushed on `hotfix/product-cost-optional-permanent`, cleanly fast-forwarded to main and pushed. Active release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-cost-optional-1d27f7b`, version `0.1.22-cost-optional`. Exact rollback is the previous `0.1.22-qa-phase2` release at `3821af5`.

Immutable artifact authenticated against 1,018 runtime source files and a 14,415-file manifest; existing vendor/assets reused. Fresh encrypted database-only backup verified. Final-root cache/link/autoload/version and compiled Livewire optional-cost rule passed. First activation automatically rolled back before any smoke row because the helper was unreadable by the runtime account; corrected mode to 0644 and reused the unchanged artifact/caches. No source repair, artifact rebuild, repeated backup or passing-check rerun.

Completed compact production smoke PASS once: exact identity, maintenance OFF, health/login 200 and guest admin 302; six authenticated read-only allow/deny routes; optional runtime contract; five stored costs read; failed/queued jobs zero; FPM/cron and queue/scheduler/backup schedules healthy; backup monitor healthy; new critical/error count zero. No persisted production authentication session, password/role/grant change, fixture, Product Card/financial QA write or migration.

Disposable database, credentials and browser sessions removed after final readback. Receipts: `artifact.json`, `activation.txt`, `smoke-*` and deployment/resume helpers. Report-only publication does not change or rebuild this application release.
