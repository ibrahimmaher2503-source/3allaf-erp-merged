# Current Task — TSK-027 Dynamic Customer/Loyalty Policy Settings

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** TSK-027 empty/readiness boundary is browser-verified; this owner-directed follow-up adds a Local/Dev dynamic settings slice. Customer/loyalty/wallet/Gift Card transaction workflows remain deferred. Page Guide desktop tour QA is implemented and verified; true mobile viewport remains unverified and commit is pending.

## Owner direction

The customer/loyalty decision cards must be dynamic and changeable from Settings. This authorizes a reversible Local/Dev settings slice, not Production/UAT policy approval or customer data mutation.

## Required reading completed

- `TASKS.md`, current `.ai/` control records, Git status/stash.
- `docs/27-customer-loyalty-wallet-gift-policy.md`
- `docs/31`, `docs/35`, `docs/36`, `docs/37`, `docs/38`, `docs/46`, and `docs/57`.
- US-003/US-023, role/permission boundaries, existing `FinancialSettingVersion`, `SaveLocalSettingsAction`, `RecordAuditEvent`, and settings routes/views.

## Allowed scope

- Add append-only `customer_policy_setting_versions` with stable decision keys, free-form owner/configuration value, version, actor, notes, and Local/Dev status.
- Add a settings page and guarded save action under `company_settings.view/edit`.
- Make `/customers/loyalty-readiness` resolve and display the latest value/version for every TSK-027 decision key, or `PENDING` when empty.
- Audit every setting version through the existing append-only audit contract.
- Preserve bilingual English/Arabic UI and RTL/LTR.

## Forbidden scope

- No `approved` state or approval bypass; every configured value remains `Owner approval required`.
- No financial_setting_versions reuse for customer/legal/loyalty values.
- No customer/child/consent persistence, history, loyalty ledger, earn/redeem, wallet, Gift Card, balance, rate calculation, expiry enforcement, or transaction mutation.
- No defaults, Demo customer data, legal wording, production values, or new permission grants.
- No PHPUnit/Pest or automated browser tests per repository policy.

## Implementation plan

1. Create reversible versioned settings migration/model/registry/action with key allowlist, text-only values, append-only versioning, authorization, and audit.
2. Add `GET /admin/settings/customer-loyalty` and `POST /admin/settings/customer-loyalty` with company settings permissions and a form for Local/Dev values/notes; blank value means PENDING.
3. Update `/customers/loyalty-readiness` to read the latest version per key and show dynamic configured values without treating them as approved policy.
4. Add bilingual translations and links between readiness and Settings.
5. Run migration status/targeted migration, PHP lint/Pint/PHPStan, Blade cache, route checks, locale parity, Vite build, and `git diff --check`.
6. Browser verify authorized English/Arabic empty settings, save one non-sensitive Demo policy value, refresh readiness to prove dynamic reflection, no-access denial, no mutation outside settings, no console errors/overflow.
7. Synchronize `TASKS.md`, `.ai/PROGRESS`, `CURRENT_MILESTONE`, `TEST_RESULTS`, `HANDOFF`, `BLOCKERS`, `DECISIONS`, `UI_SCREENS`, and `SESSION_SUMMARY`; commit locally only.

## Non-claims

This follow-up proves only dynamic Local/Dev policy display/configuration. It does not approve the values, implement customer/loyalty workflows, close BLK-014, close Phase 4, or claim UAT/Production readiness.
