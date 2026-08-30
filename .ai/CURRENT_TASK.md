# Current Task — M52 v0.1.18 Product Cards and Opening Inventory

**Date:** 2026-08-30
**Branch:** `feature/v0.1.18-product-cards-opening-stock`
**Baseline:** published `v0.1.17` commit `ef840368bea5f4ed67d9ec25f8f7c322bd428e02`

## Scope

- Product Filters management aligned with the established category interaction.
- Two-stage Product Card entry with international/local barcode identity and explicit cost/base consumer price contract.
- Product/category/brand XLSX imports, rejection workbooks, and filtered XLSX/PDF exports.
- Dedicated draft/preview/approve/reverse Opening Inventory documents plus an audited zero-opening decision.
- Existing inventory ledger, permissions, audit, Setup context, Cairo PDF, and M51 exchange foundations remain authoritative.

## Verification directive

The owner explicitly authorizes focused M52 automated tests and one genuine authenticated Arabic/English desktop/mobile pass against an isolated task-local MariaDB runtime. Production/shared data and runtime remain prohibited.

## Closure

Implementation, focused tests, isolated MariaDB migration, Cairo PDF inspection, and genuine Arabic/English desktop/mobile acceptance passed. Frontend sources were unchanged, so the verified v0.1.17 build was retained without rebuilding.
