# Current Task - v0.1.22-hotfix25 keyboard product lines

**Date:** 2026-09-09
**Change class:** I4
**Source baseline:** exact Hotfix24 commit `fcb40cd596dd7b45a577befbdf13f46ead072860`
**Branch:** `hotfix/v0.1.22-hotfix25-keyboard-product-lines`
**Status:** Implementation, focused local verification, release commit, and immutable operator packaging are complete. Promotion/activation remain unexecuted operator actions.

Hotfix25 adds one authorized, bounded, bilingual product lookup and one reusable keyboard line-editor workflow to transactional product-entry screens in purchasing, inventory, sales/returns, quotations, Party consumables/invoices, and POS. Exact barcode/code entry, keyboard list selection, quantity/price progression, calculated line totals, one-at-a-time automatic blank rows, and parent-form submission protection are shared while downstream document actions retain their existing authorization, scope, pricing, tax, stock, validation, duplicate, and transaction rules.

No migration was added or run. Activation must accept only exact Hotfix24 commit `fcb40cd596dd7b45a577befbdf13f46ead072860`, require migration 000111 already Ran without executing migrations, preflight runtime access/caches/bilingual component rendering/compiled asset identity before switching, and roll back exactly to Hotfix24 on any failure.
