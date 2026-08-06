# Bulk Operations Contract and Inventory

**Date:** 2026-08-06
**Git root:** `/home/ubuntu/projects/toy-joy-phase-1-documentation`
**Decision:** `DEC-048`
**Status:** Shared selection foundation in progress; destructive and workflow bulk actions require separate approval.

## Shared-component review

- `resources/views/components/data-table.blade.php` is a display wrapper with slots; it does not own query state, selection, authorization, or action semantics.
- `resources/views/components/tables/data-panel.blade.php` owns panel layout, toolbar/footer slots, and overflow behavior; it is a suitable host for the bulk toolbar but is not a bulk-operation engine.
- `resources/views/components/tables/filter-bar.blade.php` is filter layout only.
- `resources/views/components/line-editor.blade.php` edits transactional line items. It is intentionally not bulk-enabled: line editing has different validation, locking, pricing, and inventory semantics from selecting resource rows.
- `resources/views/components/data/value.blade.php` is value presentation only.
- The new `resources/views/components/tables/bulk-actions.blade.php` is the shared selection/action surface. Domain screens provide the action slot; the component never guesses what a resource is allowed to mutate.


Every interactive mutation-capable data table uses the shared `x-tables.bulk-actions` component and `App\Support\Bulk\WithBulkSelection` trait when the screen owns a Livewire/Volt query. Read-only tables are explicitly registered as no-action resources in the inventory.

- current-page select all with an explicit current-page label;
- cross-page-safe selected ID set, bounded to 100 synchronous records;
- clear selection;
- action slot for resource-specific, permission-aware actions;
- no mutation action on read-only tables.

Cross-page “select all N results” is a queued job boundary, not a synchronous loop. It remains pending until queue availability, progress/cancellation UI, correlation IDs, retry/idempotency, and action-specific audit semantics are implemented.

## Existing table inventory

| Screen | Table/resource | Current behavior | Safe bulk action now | Deferred / prohibited |
|---|---|---|---|---|
| `catalog.products` | `products` | Paginated catalog, existing audited status toggle | Current-page status toggle through `SaveProductAction` and existing Gate | Delete, field mass-edit, cross-page job |
| `catalog.categories` | `categories` | Hierarchy, existing status toggle | Current-page status toggle through existing action | Delete/re-parent bulk, cross-page job |
| `catalog.brands` | `brands` | Paginated master, existing status toggle | Current-page status toggle through existing action | Delete, field mass-edit, cross-page job |
| `catalog.suppliers` | `suppliers` | Paginated master, existing status toggle | Current-page status toggle through `ToggleSupplierStatusAction` | Delete, commercial field mass-edit, cross-page job |
| `catalog.products.import` | `product_import_batches` / rows | Staged import review and explicit approve/cancel | None until batch-level idempotency/progress contract is approved | Bulk approve/cancel/import |
| `admin.branches` | `branches` | Local baseline CRUD/status/mapping | Status only if existing audited action is reused | Logical delete bulk, mapping changes |
| `admin.stores` | `stores` | Local baseline CRUD/status/mapping | Status only if existing audited action is reused | Delete, mapping, warehouse policy |
| `admin.cash-drawers` | `cash_drawers` | Local baseline CRUD/status | None until drawer/shift policy is approved | Delete, opening/reconciliation changes |
| `admin.settings` | `companies`, `payment_methods`, `tax_settings`, `document_sequences`, `printer_configurations` | Multiple unrelated config tables on one screen; transactional save actions exist | None as a generic row-bulk table | Cross-resource mass changes; tax/sequence/printer policy changes |
| `admin.authorization-baseline` | users/roles/scope review | Authorization-sensitive review and assignment | None | Bulk roles/scopes/user changes |
| `admin.audit` | `audit_logs` | Append-only read-only log | None; optional export is a separate future action | Delete/update/acknowledge mutations |
| `system.health` | health checks | Read-only diagnostics | None | No mutation |
| `system.ui-showcase` | demonstration rows | Read-only showcase | None | No mutation |
| `purchasing.orders` | `purchase_orders` | PO lifecycle actions and print | None in this slice | Submit/approve/cancel/close bulk; all require workflow/owner review |
| `purchasing.orders.print` | one PO document | Print-only | None | No mutation |

## Existing bulk and infrastructure gaps

- `catalog.product-import` is the existing true batch surface: staging is chunked and invalid-row export streams through a cursor. Approval is still synchronous and does not yet provide queued job progress/cancellation.
- The repository currently has no reusable `ShouldQueue`/`Bus::batch`/`WithoutOverlapping` implementation. Cross-page selection and large exports must not be presented as completed until that infrastructure exists.
- Most domain mutations use transactional action classes with audit writes, but there is no generic bulk-operation audit envelope yet. Future queued work must carry actor, request/correlation ID, selected scope, per-record result, reason, and idempotency data.
- Most resources do not have model Policies; they currently rely on Gate strings in Volt components/actions. A future action registry must not treat UI visibility as authorization.
- The PO print route and product-import error export need separate scope/audit review before they are used as templates for future bulk export.

## Required action contract for future resources

A resource may add a bulk action only when all items are present:

1. named action key and user-facing bilingual label;
2. server-side Gate/Policy check for the current user and every selected record;
3. explicit selection limit and confirmation/impact preview;
4. transaction and concurrency behavior appropriate to the resource;
5. per-record audit events with a bulk correlation ID;
6. idempotent retry behavior or an explicit rejection of retry;
7. queued cross-page implementation for large result sets;
8. progress, failure summary, and partial-failure semantics;
9. manual browser verification for selection, empty selection, permission denial, confirmation, success, failure, RTL/LTR, and narrow viewport;
10. an entry in this matrix and `.ai/DECISIONS.md` when scope changes.

No future table is “bulk-enabled” merely because a checkbox was copied into its Blade file. It must consume the shared component and register an approved action contract.
