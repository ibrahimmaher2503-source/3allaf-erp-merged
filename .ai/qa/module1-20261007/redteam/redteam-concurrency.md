# Module 1 branch and approval concurrency red-team review

Reviewed integration: `hotfix/module1-redteam-concurrency-20261007` at `07a514c94d106efe3516d81780ee9081ecfd86bc`.

## Scope

Reviewed branch deactivation and approval-driven logical deletion, request snapshots and source hashes, approval serialization, and lock ordering against store status activation, branch/store mapping writes, and POS shift opening. Relevant paths: `SaveBranchAction`, `SaveStoreAction`, `SaveBranchSellingStoreMappingAction`, `OpenShiftAction`, `PlatformSettingsApprovalAction`, `RequestApproval`, `ApproveRequest`, and `ApprovalRecordTransition`. Used only disposable XAMPP schema `qa_rajh_m1_concurrency_20261007` through the guarded local helper. No production data or writes were inspected or used.

## Attacks and results

- Ran `BranchLifecycleDependencyTest.php`: **5 tests / 25 assertions passed**. Active-store-only and active-mapping-only states block direct branch deactivation; active user scopes remain non-blocking; a rejected delete approval leaves its request pending and keeps the branch/store/mapping active.
- Added and ran `BranchLifecycleConcurrencyTest.php`: **1 test / 5 assertions passed**. Two independent PHP workers were released from a barrier to race branch deactivation against activation of an inactive store. Exactly one write succeeded, the competing write was blocked, the final state never had an active store under an inactive branch, and exactly one status audit was written. The first worker setup attempt stopped at authorization because the fixture lacked `branches_stores.edit`; after correcting the fixture to use a real permission and scopes, the actual race passed.
- Added and ran `PlatformSettingsApprovalConcurrencyTest.php`: **3 tests / 20 assertions passed**. Two workers concurrently approved the same branch source: one approval and one branch logical-delete audit were committed, while the second action was rejected. Tampering with `source_hash` and changing the branch after request each prevented approval; the request remained pending, the branch stayed active (with the newer name preserved in the changed-snapshot case), and neither an approval-decision nor delete audit was written.

## Lock and invariant review

`SaveBranchAction` locks Branch before checking active Store and mapping rows. `SaveStoreAction` status activation locks Branch before re-locking and writing Store. `SaveBranchSellingStoreMappingAction` locks Branch before mapping rows. This gives the branch lifecycle writers one parent-first order; the real branch/store race above confirmed the invariant under a two-worker conflict.

`OpenShiftAction` takes a shared Branch lock, then Store, then CashDrawer, and revalidates the drawer-to-branch/store relationship after acquiring those locks. Branch status changes require the conflicting Branch lock, and any active Store independently blocks deactivation/deletion. No lock-order cycle or persisted invariant violation was reproduced in the reviewed paths.

Branch-delete request counts are request-time context only. Approval rechecks active Store and active selling mappings under locks immediately before deactivation; it does not cascade. Active user-scope counts remain context only. The failed-approval test confirms the ApprovalRecord transition and audit are rolled back when a dependency blocks deletion.

## Findings and limits

No confirmed defect was found in the assigned branch/approval concurrency boundary. No speculative production lock or policy change is proposed. The worker checks establish specific two-writer schedules, not exhaustive stress coverage; POS opening was checked by lock-order/source review rather than a separate three-writer stress test. The full suite, browser/device workflows, production, and out-of-band database mutation scenarios were outside this bounded review.

The two added QA tests are review-only focused checks. No production implementation or migration was changed in this review branch.
