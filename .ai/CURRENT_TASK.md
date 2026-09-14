# Current Task — v0.1.22-hotfix39 Catalog, Opening Balance, and Parties

**Date:** 2026-09-14
**Change class:** Customer Notes Batch 3 hotfix release
**Source baseline:** exact active Hotfix38 commit `45d9e78c757cd83a596b11d613475dbdc441d2c3`
**Branch:** `hotfix/v0.1.22-hotfix39-catalog-opening-balance-parties`
**Status:** Complete in development and ready for the authorized root operator; implementation, focused verification, release commit, and immutable offline packaging are complete.

Hotfix39 compacts product and party master workflows, preserves Excel imports while removing distributed export buttons, introduces derived Draft/Resume handling for incomplete products, exposes separate supplier item codes and existing branch-price matrices, adds stable customer display codes and loyalty help, and extends supplier contacts with mobile/notes while hiding payment terms from UI only.

Opening inventory is now a branch/store/date document with Hotfix38 branch/type continuous numbering, dynamic keyboard/scanner product lookup, quantity/cost/totals, paste and repaired XLSX flows, Draft/Resume/Approve, audited reversal, zero-opening completion, and ecommerce identity mapping guidance. Posted documents and stock/accounting actions remain immutable and unchanged.

The owner-authorized migration is `2026_09_14_000113_extend_opening_inventory_and_supplier_contacts_for_hotfix39.php`. It adds nullable opening header context, nullable supplier contact mobile/notes, indexes, and branch opening-inventory sequence rows with `reset_rule=never`. Its `down()` intentionally retains these backward-compatible additions. No local migration execution is authorized. Activation must prove 000111 and 000112 are already `Ran`, run only 000113 if pending, and retain its additions if application rollback restores exact Hotfix38.
