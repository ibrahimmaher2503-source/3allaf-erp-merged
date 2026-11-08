# 39 — UAT and Release Gates

**Product:** 3allaf  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define how Phase 1 moves from development increments to UAT, production readiness, go-live, and handover without claiming completion based only on code existence.

## 2. Traceability

- Implementation Plan: all Phase Gates, DM 6.3, DM 6.4.
- PRD acceptance scenarios.
- docs/12 acceptance criteria, docs/13 definition of done, docs/14 test plan.
- Current owner directive: manual browser verification; automated tests deferred.

## 3. Gate Model

### Task Gate

Task scope implemented and manually verified.

### Milestone Gate

All tasks in DM complete; integrated workflow demonstrated.

### Phase Gate

Cross-milestone acceptance, critical defects resolved, required business data/decisions available.

### Production Readiness Gate

Infrastructure, security, backup/restore, devices, data, training, support, rollback approved.

### Go-Live Gate

Named owners sign off and launch checklist passes.

## 4. Evidence Rules

Only actual evidence is recorded:

- Manual browser scenarios.
- Screenshots.
- Browser console/network.
- Print previews.
- Data reconciliation.
- Role/scope checks.
- Backup/restore record.
- Device/printer evidence.
- UAT sign-off.
- Known issues.

No automated coverage may be claimed under current directive.

## 5. Severity

- Critical: data loss, financial/stock corruption, security boundary failure, unusable core workflow.
- High: major workflow blocked/no safe workaround.
- Medium: partial impairment with workaround.
- Low: cosmetic/minor.

No open Critical defect at gate. High defects require explicit owner disposition.

## 6. DM 1 Gate — Foundation and Controls

Required:

- Authentication/session workflows.
- Layout/PWA baseline.
- Company/branch/store/drawer/settings.
- Roles/permissions/scopes.
- Approval/audit/attachment/immutability.
- Safe errors/request IDs.
- Backup/restore and monitoring evidence for production gate.
- Security review.
- Required business inputs.

Local completion does not equal production gate completion.

## 7. DM 2 Gate — Catalog, Purchasing, Pricing, Inventory

Required:

- Product/barcode/search/import.
- Suppliers/purchasing/returns.
- Weighted-average cost.
- Price versions/labels/unpriced block.
- Ledger/balance reconciliation.
- Transfers/adjustments/counts.
- No critical stock discrepancy.
- Approved opening data approach.

## 8. DM 3 Gate — POS and Daily Finance

Required:

- POS search/cart/suspend/retrieve.
- Tax/discount/payment/evidence.
- Stock/payment linkage.
- Prints.
- Shift open/blind close/variance.
- Restricted offline policy/device evidence if enabled.
- Totals and reconciliation.

## 9. DM 4 Gate — Customers, Loyalty, Wallets, Returns

Required:

- Unique customer.
- Consent handling.
- Loyalty.
- Separate Product/Party Wallets.
- Gift Cards/Gift Receipts.
- Returns/exchanges/refunds.
- Cross-activity privacy.

## 10. DM 5 Gate — Parties and Assets

Required:

- Booking/calendar.
- Working invoice.
- Payments on account.
- Operating order.
- Consumables.
- Asset reservation/checkout/return/inspection.
- Damage/depreciation.
- Final close/Party Wallet settlement.
- No mixed retail/party documents.
- No asset double booking.

## 11. DM 6 Gate — Reporting, UAT, Launch

Required:

- Dashboard KPIs.
- Alerts.
- All report groups.
- PDF/Excel.
- Scope/redaction.
- UAT execution.
- Data migration/opening setup.
- Training.
- Support/runbook.
- Production readiness.

## 12. UAT Roles

Name before UAT:

- Project owner.
- Business process owners.
- Finance.
- Retail operations.
- Warehouse.
- Party operations.
- Technical owner.
- Acceptance coordinator.
- Defect owner.
- Final signatory.

## 13. UAT Scenario Pack

Minimum end-to-end:

1. Foundation/admin setup and authorization.
2. Purchase receipt → stock/cost → pricing → labels.
3. Transfer/count/adjustment reconciliation.
4. POS sale → payment/evidence → print → shift close.
5. Return/exchange/Gift Card.
6. Customer loyalty and wallet separation.
7. Party lifecycle through final settlement.
8. Asset conflict/checkout/return/damage.
9. Reporting/export/audit.
10. Offline sync/conflict if enabled.
11. Backup/restore.
12. Security/scope/denial.

## 14. Data Reconciliation

At gate reconcile:

- Stock movements vs balances.
- Purchase receipt vs cost.
- Sale vs payments vs stock.
- Shift expected vs source activity.
- Loyalty/wallet/gift ledgers vs balances.
- Party invoice vs payments/wallet.
- Asset status vs reservations/checkouts/returns.
- Reports vs source transactions.

## 15. Production Readiness Checklist

- Hosting/domain/SSL.
- Production database.
- Secrets.
- Queue/scheduler/cache/workers.
- Storage.
- Backup and restore test.
- Monitoring/alerts.
- Printers/scanners/devices.
- Final master data.
- Tax/payment/numbering/templates.
- Roles/users/scopes.
- Offline policy/devices.
- Legal wording/retention.
- Security review.
- Performance at expected volume.
- Migration/cutover.
- Rollback.
- Support contacts.
- Training.
- Known-issues acceptance.

## 16. Go-Live

- Freeze approved release/version.
- Final backup.
- Controlled migration/deploy.
- Smoke verification.
- Printer/device verification.
- First transaction monitoring.
- Named hypercare window.
- Incident escalation.
- Rollback trigger.

## 17. Handover

Deliver:

- Source/version.
- Deployment runbook.
- Configuration inventory.
- Data dictionary.
- User/admin guide.
- Support guide.
- Backup/restore guide.
- Known issues.
- Evidence repository.
- Training record.
- Sign-offs.

## 18. Exit Statement

A task/milestone/phase may be marked complete only when its actual acceptance evidence exists. “Implemented locally,” “UAT accepted,” and “production ready” are distinct statuses and must never be conflated.

## 2026-11-05 — Launch stabilization production release evidence

Owner-authorized A–N plus I/K/N acceptance hotfix merged to main as `8306dee6324f0ecaf50d18bcfb8c7410ca210426` (parents `5ff5a7a` and `a381b94`) and pushed. Immutable production release `/home/rajeh/releases/toyjoy/releases/toyjoy-v2-launch-stabilization-8306dee` is LIVE; previous exact code rollback target `/home/rajeh/releases/toyjoy/releases/toyjoy-v2-consolidated-c6f4607` is preserved. Migration `2026_11_05_064701_add_launch_operational_role_grants` was reviewed as insert-only built-in role-permission grants, no schema or historical business-data mutation, no custom-role rewrite; rollback to the prior code remains compatible while grant rows may remain. It ran once as batch 48; no other migration or seeder ran. Fresh maintenance-gated database backup `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261105T081746Z_pre_launch_8306dee.sql.gz` (SHA-256 `9c04bdded140df3396809871df0edd8f1bb7ad5fc717ab435ca4352a94044d2b`; gzip/174-table SQL readability PASS) and matching attachment archive `/home/rajeh/shared/toyjoy/backups/rajeh_storage_20261105T081746Z_pre_launch_8306dee.tar.gz` (SHA-256 `ded290b6f12bc7146d7c8790c90eb73c13b805ef0ac27ab7461072f3f5cd9bcc`; tar readability PASS) were made before migration. The release source marker matches the merge SHA, shared env/storage links and lockfile identities were verified, frontend rebuilt with Node 20, final-path Laravel caches built as `rajeh`, and PHP 8.5 FPM reloaded. Production maintenance is OFF. Public `/`, `/up`, `/login` and CSS/JS returned 200; guest protected routes redirected to login; read-only authenticated renders for purchasing invoices, pricing labels, transfers/inbox, Entry Permit, Stocktake Create, POS and Gift Cards all returned 200 within a rolled-back database transaction. No production sale, receipt, transfer, inventory adjustment or stocktake was posted, so live transaction paths are not claimed validated by this smoke; the focused disposable-data validation remains in `.ai/TEST_RESULTS.md`. No critical smoke failure or rollback occurred. Physical scanner and printer acceptance remains manual post-release. Next action: owner operational UAT/monitoring; no deployment blocker recorded.
