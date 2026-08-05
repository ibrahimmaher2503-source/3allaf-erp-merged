# TSK-014 — Purchase Orders (owner-authorized local continuation)

Repository: /home/ubuntu/toy-joy-phase-1-documentation

The owner explicitly authorized starting TSK-014 after the read-only AGY requirements audit. Implement only a reversible local slice in the current worktree. Preserve all existing uncommitted work; do not reset, revert, or overwrite unrelated changes.

Required reading already completed by the parent: all `.ai/*.md`, `TASKS.md`, and TSK-014 references in docs/35, docs/36, docs/37, docs/38, docs/17, docs/18, docs/19. Re-read the relevant files before implementation.

Implement TSK-014 only:
- purchase_orders and purchase_order_lines migration with strict FKs/indexes, status, dates, totals, lock_version, audit user references; no stock tables or invoice tables.
- PurchaseOrder/PurchaseOrderLine models and relationships.
- Document sequence allocation for PO numbers using an existing concurrency-safe pattern if available; do not invent production numbering policy. Local format may be explicitly marked demo/TBD.
- Save draft, submit, cancel-with-reason, and close actions with transactions, optimistic locking, state guards, audit events, and no stock/payment effect. Receipt states are downstream TSK-015: do not fabricate receipt actions.
- A bilingual responsive Livewire/Blade `/purchasing/orders` list/editor/detail UI with search/status filters, draft line editor, totals using configured/explicitly local zero or TBD tax (never assume 15% production tax), status actions, audit timeline/empty state, and truthful empty receipt links.
- A print-friendly A4 PO detail view only if it can be implemented without adding a PDF package or production company/tax data; otherwise keep a safe print view with placeholders.
- Route wiring, sidebar entry, and UI-PUR-001 tutorial registration.
- Authorization must use only task-documented actions: create/edit draft, submit, cancel, close, print/view as supported by the existing canonical matrix. Do not add approve/export/reverse permissions or alter unrelated permission grants. If the existing permission catalog cannot represent submit/close without broad matrix changes, use the narrowest existing documented action and record the limitation rather than inventing permissions.
- Local demo seed data only in LocalDemoSeeder: use existing DEMO suppliers/stores/products; no production supplier data, tax rules, payment defaults, stock, invoices, or prices. Seeder must be idempotent.

Forbidden:
- No purchase invoices/imports, receipts, stock movement/balance, weighted-average cost, pricing, returns, approval workflow, or TSK-015/016 implementation.
- No migration or code for production opening balances or commercial defaults.
- No automated PHPUnit/Pest/application test suite creation or execution per repository policy.
- No commit or push.
- Do not modify vendor files.

After implementation, report files changed and leave verification to the parent agent. Do not claim browser/tests passed unless you actually ran and observed them.
