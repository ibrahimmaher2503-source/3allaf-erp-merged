# Current Task — TSK-027 Customer/Loyalty Readiness Boundary

**Date:** 2026-08-07
**Repository:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Status:** TSK-023 through TSK-026 have browser-verified Local/Dev/readiness boundaries; TSK-027 is now a discovery/read-only boundary only. No customer, loyalty, wallet, or gift ledger exists in the repository.

## Required reading completed

- `TASKS.md`, `AI_INDEX.md`, current `.ai/` control records, Git status/stash, and TSK-027 dependencies.
- `docs/27-customer-loyalty-wallet-gift-policy.md`
- `docs/31-pos-sales-specification.md`
- `docs/35-document-state-machines.md`
- `docs/36-module-data-contracts.md`
- `docs/37-ui-screen-specifications.md`
- `docs/38-print-export-specification.md`
- `docs/57-ui-interaction-and-data-entry-standard.md`
- `docs/05-user-stories.md` US-003/US-023 and `docs/04-roles-permissions.md` wallet/customer boundaries.
- `DEC-014`, `DEC-015`, `DEC-060`, `BLK-014`, and TSK-023–TSK-026 evidence.

## Repository findings

- No customer, customer-child, loyalty, wallet, Gift Card model/migration/route exists.
- No customer-specific permission contract is active; `pos_sales.view` is the existing verified read-only gate and will be reused only for this empty readiness page.
- No customer records, loyalty rates, expiry/rounding rules, consent wording/retention, wallet values, or Gift Card data will be fabricated.

## TSK-027 allowed scope

- Add a server-gated empty/readiness page for customer identity, consent/children privacy, unified history, and shared loyalty contracts.
- Show explicit PENDING cards for unique phone/duplicate review, legal consent/retention, purpose scopes, activity-specific loyalty rules, ledger/idempotency/expiry/rounding, insufficient-balance protection, and TSK-028/029 dependencies.
- State that no customer/loyalty/wallet/gift records are loaded or mutated and that the gate reuse is not a customer capability grant.

## Forbidden until explicit policy/configuration authorization

- No customer/child/consent persistence, merge, export, sensitive history, or source linkage.
- No loyalty rates, expiry/rounding, earn/redeem/adjustment, balance, approval, or ledger mutation.
- No Product/Party Wallet records or cross-wallet behavior; TSK-028 remains separate.
- No Gift Card/Gift Receipt records or issue/use/void/reprint behavior; TSK-029 remains separate.
- No new permission grant, Demo customer data, legal wording, financial default, UAT/Production claim.
- No PHPUnit/Pest or automated browser tests per DEC-012.

## Implementation plan

1. Add `GET /customers/loyalty-readiness` under `auth`, `verified`, and the existing `pos_sales.view` read-only gate; pass no customer/financial dataset.
2. Render bilingual LTR/RTL empty/readiness cards with explicit PENDING states and disabled/no-action semantics.
3. Verify source/route and inspect DOM/response for absence of customer records, balances, rates, ledger actions, wallet/gift actions, and sensitive values.
4. Run PHP lint/Pint/PHPStan/Blade diagnostics, locale parity, `git diff --check`, and real browser authorized English/Arabic/no-access scenarios with console evidence.
5. Synchronize `TASKS.md`, `CURRENT_MILESTONE`, `CURRENT_TASK`, `PROGRESS`, `TEST_RESULTS`, `HANDOFF`, `BLOCKERS`, `UI_SCREENS`, `DECISIONS`, and `SESSION_SUMMARY`; commit locally only.

## Production non-claims

This boundary does not complete TSK-027, DM 4.1, Phase 4, customer/loyalty policy, TSK-028/TSK-029, UAT, or Production readiness. BLK-014 and owner-configurable consent/rates/expiry/rounding/approval values remain open.

## Next action

Implement only the planned `/customers/loyalty-readiness` empty/read-only boundary, then verify and synchronize records. Keep TSK-025/TSK-026 policy boundaries open and do not create customer, loyalty, wallet, or gift data.
