# Pending Change Batch

**Status:** CLOSURE CANDIDATE PREPARED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix15 complete batch
**Opened after:** `v0.1.22-hotfix10`
**Source baseline:** exact Hotfix14 commit `32c13a8e54f8dad45691a88318f9b475ff944f15`
**Production baseline:** operator-verified Hotfix14 release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix14-pricing-cash-rounding`, commit `32c13a8e54f8dad45691a88318f9b475ff944f15`
**Baseline disposition:** Hotfix14 is already deployed and is outside this batch.

The owner explicitly instructed closure as Hotfix15. Do not clear or archive this ledger until an authorized operator confirms successful production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V0/V1 | Shared navigation/settings/readiness deduplication, permission checkbox state, Arabic printer/POS approval presentation | Hotfix15 candidate (this commit) | Shared/sidebar/admin/setup/approval Blade, navigation support/config, status presentation, `lang/ar.json` | CANDIDATE COMPLETE — NOT DEPLOYED |
| L2/I4 | Dynamic company-scoped XLSX import templates, exact schema/header validation, live reference sheets and scalable opening-inventory workbook | Hotfix15 candidate (this commit) | Data-exchange support, import actions, import routes | CANDIDATE COMPLETE — NOT DEPLOYED |
| D3/I4 | Company/branch/location-scoped product assignment plus scalable opening inventory draft/import/approval/reversal workflow and migration 000109 | Hotfix15 candidate (this commit) | Inventory actions/query/models/routes/Livewire UI, migration 000109 | CANDIDATE COMPLETE — NOT DEPLOYED |
| D3 | Centralized product dependency inspection, safe archive/dependency-free deletion, protected QA/UAT audit and cleanup preparation | Hotfix15 candidate (this commit) | Product model/detail UI, removal service/action, root-only commands, seeder guard | CANDIDATE COMPLETE — NOT DEPLOYED |

Production deployment and the separately documented QA/UAT product cleanup are not part of candidate preparation and remain unexecuted.
