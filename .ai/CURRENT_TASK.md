# Current Task — Hotfix45 Purchasing Form Financial Repair

**Date:** 2026-09-21

**Change class:** I4

**Source baseline:** deployed Hotfix44 commit `d94384eabe1890889a6c2acac44c6461ab0962bc`, tree `6660d3e6c4542dfe5100076a6642ed0394384cb0`

**Branch:** `hotfix/v0.1.22-hotfix45-purchasing-form-calculations`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix45`

**Status:** Completed and deployed. Production serves exact Hotfix45 application commit `7a327b93abc48987eab7238e6bcd125635413fbb`, tree `81aa595c614fec6812e64370212bb7894f8d7df4`.

Hotfix45 repairs purchase-order and purchase-invoice reactive financial previews using the existing authoritative calculators, including discounts and inclusive/exclusive tax. Purchase-invoice cost remains distinct from consumer selling price, uses the established supplier/general/average-cost fallback, stays editable under the existing permission, and remains blank and required when no purchase cost exists. Ordinary cost-source/server-calculation notes were removed from the forms and retained as contextual help.

The focused browser interaction pass, isolated MariaDB save/reload pass, fresh encrypted production backup/restore reconciliation, schema-preservation gate, atomic activation, authenticated read-only render, and authenticated live browser interaction all passed. No schema change or production test document was created.
