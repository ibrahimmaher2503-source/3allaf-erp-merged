# v0.1.22-hotfix3 Post-review Evidence

Date: 2026-09-01. Baseline: `f3d3ba2e2b2683c9a5478c86e73fafecef8b209c`.

- PDF root exception: `Barryvdh\DomPDF\Facade\Pdf` was referenced but `barryvdh/laravel-dompdf` is not installed; direct `dompdf/dompdf` 3.1.6 is installed. The fixed path uses `Dompdf\Dompdf`, local Cairo TTFs, writable font cache, PDF-only PNG barcodes, and explicit thermal/A4 geometry.
- Focused source checks: 23 tests, 173 assertions; 21 passed. Two obsolete source-string assertions failed because parent customer groups are now intentionally selectable and a removed historical destination-label view is still referenced by the old test. No database-backed suite ran.
- Render primitives: Code 128 `TESTLOCAL0001` and EAN-13 `4006381333931` produced valid PNG signatures. Quantity examples rendered `2`, `-2`, `0`; `1.5` raised an explicit integrity exception.
- PHP syntax, compiled Blade, route listing, Arabic/English JSON parsing, and diff hygiene passed. CSS changed, so one Node 20 production build is required and recorded at release closure.
- Production/shared data, database, deployment, active symlink, tags and remotes were not accessed or mutated.

