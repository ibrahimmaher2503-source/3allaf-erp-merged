# TSK-015 — Documentation Coverage Report

**Scope of review:** all 40 files in `docs/`, plus `.ai/` control files, `TASKS.md`, `AI_INDEX.md`, `AGENTS.md`, and the current Purchasing/Catalog/Platform implementation.
**Question asked:** does the existing documentation already answer the TSK-015 owner-input requirements?
**Answer:** No. The requirements are *named* across the existing docs, but no numeric value, formula, or rounding rule is defined anywhere in the repository.

---

## 1. Search evidence

| Term searched across `docs/` | Result |
|---|---|
| `half-up` / `half_up` / `HALF_UP` | **zero matches in the entire repository** |
| `weighted` | 11 files, all stating the *requirement*; no formula |
| `decimal` | 4 files; `docs/07` proposes `decimal(19,4)` and explicitly marks precision as an Open Decision |
| `over-receipt` | 1 file (`docs/05`), as a user story only, no policy |
| `rounding` | 12 files, all deferring to owner configuration |

`docs/07-database-schema.md` §3 states directly that exact money/quantity precision requires owner approval before implementation. `docs/09-coding-standards.md` §45 requires "explicit rounding rules" without defining them.

---

## 2. Per-section coverage

| Owner-input section | Nearest existing doc | Status |
|---|---|---|
| 1. Cost policy | `docs/24` (selling price only), `docs/07` §9, `docs/22` §83 | **Gap.** WAC named as a service capability; no formula, no precision, no rounding, no edge cases |
| 2. Tax | `docs/30`, `tax_settings` table | **Gap.** Table exists, values pending under BLK-008 |
| 3. Discount | `docs/26` | **Gap.** `docs/26` covers *sales* discounts and returns, not purchase discounts |
| 4. Import | `docs/23`, existing `ProductImportBatch` implementation | **Partial.** A working staged-import pattern exists for products and should be reused; no purchase template exists |
| 5. Receiving/matching | `docs/35` §5, `docs/36` §7, `docs/02` §177 | **Contradiction risk.** PRD §177 says invoice approval increases inventory; `docs/35` titles the machine "Purchase Invoice/Receipt". Whether a separate receipt document exists is undecided |
| 6. Opening stock | `docs/25` (marks opening inventory method as pending), BLK-010, BLK-012 | **Gap.** No source, no cutover timestamp, no approver |
| 7. Branches/stores data | `docs/30`, BLK-006 | **Gap by design.** Blocker explicitly states no production master data may be inferred |
| 8. Permissions | `docs/04` (canonical, DEC-038) | **Partial.** Module row exists; Approve/Reverse/Override are `R` cells requiring owner approval; no limits defined |

---

## 3. Blockers already covering this

The register in `.ai/BLOCKERS.md` already anticipates all of it:

- **BLK-008** — currency, tax, numbering, print templates. Status: Open.
- **BLK-010** — supplier records/terms, purchase templates, receipt semantics, returns, discounts/tax, opening-stock method. Status: **Open**, and explicitly listed as affecting TSK-013–TSK-016.
- **BLK-012** — opening inventory method. Status: Mitigated for local scope only.

So the owner-input request is not extra scope. It is the exact set of answers BLK-010 has been waiting for.

---

## 4. Files added by this review

| File | Covers |
|---|---|
| `docs/41-purchase-cost-tax-discount-policy.md` | Sections 1, 2, 3 |
| `docs/42-purchase-invoice-import-specification.md` | Section 4 |
| `docs/43-receiving-and-matching-policy.md` | Section 5 (+ numbering, print) |
| `docs/44-opening-stock-cutover-specification.md` | Section 6 |
| `docs/45-purchasing-authorization-matrix.md` | Section 8 |
| `.ai/TSK-015_OWNER_INPUTS.md` | All 8 sections as keyed questions, including section 7 master-data templates |

Every proposed value in docs 41–45 is marked `PENDING` and carries a decision key (`OI-*`) that maps to a row in the owner-inputs file. Nothing in these files is an approved decision.

---

## 5. Required follow-up before coding

1. Owner answers `.ai/TSK-015_OWNER_INPUTS.md` — at minimum the seven keys listed in its final section.
2. Answers are transcribed into `.ai/DECISIONS.md` (the authoritative source per `AI_INDEX.md`).
3. `docs/41`–`docs/45` are updated from `PENDING` to the approved value, and their Status line changes to approved.
4. `AI_INDEX.md` Authority Order §7 is extended to cover `docs/30` through `docs/45`.
5. `.ai/BLOCKERS.md` BLK-008/010/012 rows are updated with the closed items.
6. `TASKS.md` TSK-015 acceptance criteria reference the approved keys.

Item 4 matters: `AI_INDEX.md` currently routes agents to `docs/30`–`docs/39` only. Without that edit, a future agent will not read docs 41–45.

---

## 6. Open contradiction to resolve explicitly

`OI-RCV-01` is not merely a configuration choice. If the owner requires a separate goods-receipt document, that conflicts with `docs/02-prd.md` §177, which is the highest authority in the index. Per `AI_INDEX.md`, a real conflict must be recorded in `.ai/DECISIONS.md` and implementation must stop until it is resolved. It must not be settled inside a Livewire component.
