# Current Task — TSK-046 Production Setup UI Hardening

**Date:** 2026-08-12
**Status:** Implementation and isolated MariaDB/rendered-UI verification completed; real Production data entry and headed-browser UAT remain open.

## Scope

- Nine ordered Production setup stages exposed through `/initial-setup`.
- Full active permission catalog for the System Administrator role.
- Truthful readiness checks for company, locations, users/scopes, operational settings, catalog, suppliers, prices, and opening inventory.
- Optional Customer/Party creation only for genuine activity.
- Company ownership enforced for new branches and stores.

## Verification directive

- Do not create or run PHPUnit, Pest, Playwright, Cypress, or another automated suite.
- Use XAMPP MariaDB `toyjoy_production_seed_verify_20260812` for isolated verification.
- Permitted checks include syntax, Pint, PHPStan, Blade/route caches, rendered UI/form contracts, integrity queries, and `git diff --check`.

## Remaining closure

Enter owner-approved real data through the Production UI, use independent approvers for sensitive settings/prices/opening stock, rotate exposed credentials, complete backup/restore, and perform headed-browser UAT before release approval.
