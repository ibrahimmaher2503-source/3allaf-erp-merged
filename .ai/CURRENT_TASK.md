# Current Task — v0.1.22-hotfix38 Financial and Print Settings

**Date:** 2026-09-14
**Change class:** Customer Notes Batch 2 hotfix release
**Source baseline:** exact active Hotfix37 commit `0f0e7aa18d47986f6805c89b3494f4f55042e151`
**Branch:** `hotfix/v0.1.22-hotfix38-financial-print-settings`
**Status:** Complete in development and ready for the authorized root operator; implementation, focused verification, release commit, and immutable offline packaging are complete.

Hotfix38 adds accessible modal create/edit workflows for payment methods and taxes, hides tax effective dates from presentation only, adds a nullable product tax default with active/effective current-company validation, provides human branch-specific continuous numbering rules, and consolidates printer/template management around one visible configuration table and a 15-type template library.

The owner explicitly authorized migration `2026_09_14_000112_add_tax_setting_id_to_products.php`. It adds only nullable indexed `products.tax_setting_id`, references `tax_settings.id`, and uses `nullOnDelete`. Historical purchase and sales tax snapshots and accounting calculations are unchanged. No local database or migration execution is authorized. Activation must confirm 000111 already `Ran`, execute only 000112, confirm it `Ran`, and leave that backward-compatible column in place if application rollback restores exact Hotfix37.
