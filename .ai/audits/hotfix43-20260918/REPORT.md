# Hotfix43 recovered requirements matrix

This is the final 84-row carry-forward of the recovered original 1–73 requirements, ten owner corrections, and the separately approved inventory exception. Original IDs and requirement text are unchanged. The machine-readable source is `audit-rows.json`; `requirements-matrix.csv` is the review export.

## Status meaning

- `VERIFIED COMPLETE`: implementation and retained/current evidence support completion.
- `IMPLEMENTED — EVIDENCE LIMITED`: final source review found no implementation gap, but the focused Hotfix43 pass intentionally did not repeat every broad role, state, concurrency, or interaction variant.
- `IMPLEMENTED — EXTERNAL ACCEPTANCE PENDING`: software is implemented, but Microsoft Excel or physical printer/scanner acceptance was not performed.
- `REQUIRES OWNER DECISION`: behavior is unchanged because implementing it would invent an unresolved business rule.
- `UNRESOLVED — APPROVED EXCEPTION`: the exact inventory exception remains preserved and is not presented as a passed invariant.

## Final focused evidence

- Isolated restored MariaDB: migrations 000117 and 000118 are each `Ran` once; exact required columns, unique/index/FK definitions, unchanged historical invoice money, no null tax owner, safe no-op rerun, and fail-closed rollback were checked.
- Focused transaction checks: inclusive/exclusive invoice calculations, company tax ownership/uniqueness/restricted deletion, cross-company tax rejection, branch PO numbering continuity, supplier English-name enforcement, submitted-PO return with preserved reason, and all seven central export datasets passed.
- Browser: authenticated affected pages returned 200 in English; Arabic and Egyptian Arabic rendered RTL; product lookup used explicit company context and Arrow/Enter selection; zero-opening correctly refused existing stock history; mobile Product Cards rendered without console/page errors.
- Documents: all 12 shared XLSX generators passed ZIP and every-XML-part validation. Three localized PO PDFs were generated and identified as PDF 1.7 files.

Microsoft Excel itself, a physical printer, and a physical scanner were not used. Deferred comparison C remains unidentified. See `totals.json` and the `evidence/` directory for the retained results.
