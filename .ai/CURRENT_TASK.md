# Current Task — Connected Device Setup

**Date:** 2026-11-08
**Change class:** I4 (additive scale-configuration table and settings UI)
**Source baseline:** `e2d710b3e05b46fe20946af871112301f32f57d2`
**Production baseline:** No current release verified; no production action authorized.
**Status:** Local implementation in progress on `feature/device-settings`.

**Outlet and PLU slice:** Source now includes outlet selection, saved KG-unit/PLU mappings, effective-price preview, confirmed single-item send and unverified status. Migration and authenticated UI verification are pending. Batch partial success, automatic stale-price indicators, keys, labels, and weight capture are still open.

**Latest owner direction:** Run all device operations from ERP. MScale UI and manual file transfers are excluded. The owner reports this scale is empty and waived the initial backup pull for it. The remaining gate is a verified command and acknowledgment contract for the device.

**Direct protocol evidence:** Read-only TIM and PLU queries returned complete frames from the physical device. PLU 1 returned an empty result. The ERP connection check now validates a clock response. Write payloads and their acceptance, shortcut keys, templates and weight data remain unverified.

**2026-11-08 follow-up:** Physical PLU 3999 was verified empty, then written as ERP TEST at 12.34 and read back. Price-only update to 13.57 was verified by exact record readback with the other fields preserved. ERP now includes a permission-checked PLU lookup for the saved active scale. ERP catalog-to-scale writes, key and label operations, and weight or transaction reads are still open. Test PLU 3999 remains on the device.

**Execution resumed by owner, 2026-11-08:** Phase 1 is open. Ping to `192.168.0.150` succeeded. The vendor-hosted MScale EN3.1 package launched from a temporary directory and exposed Ethernet settings. Desktop capture failed with Windows `0x8007000E` during target entry and the bound window became unavailable while the process remained responsive. The target setting and a hardware Callback backup have not been verified. Later phases depend on protocol evidence and a verified recoverable backup before the first hardware write.

**2026-11-08 owner scope clarification:** Implementation is paused at the owner's request pending review of the full scale-management plan: `docs/superpowers/plans/2026-11-08-scale-management.md`. Requested capabilities now include product/price upload, price-update button, key assignments, scale label layouts, and receiving product/weight/price. The earlier connectivity-only slice is incomplete for this scope. No new runtime changes or migrations are authorized by the plan itself.

Extend the existing printer-settings page with one company-scoped network-scale configuration and TCP reachability check, plus an in-browser USB keyboard barcode-scanner check. Existing printer configuration remains in place. The observed scale is reachable at `192.168.0.150:33581` from this Windows computer after adding temporary `192.168.0.151/24` to Ethernet 2. Opening TCP does not prove a weight-read protocol, and no weight is inserted into POS or sales.

Remaining: migration review and execution on an authorized local MariaDB database, browser/manual permission checks, actual scanner and printer checks, and confirmed scale protocol/weight calibration before any sale integration. No automated suite is authorized for this task. No release, deployment, or production migration is authorized.

**2026-11-08 implementation continuation:** Batch price preview/send is now implemented in the ERP settings screen with per-item outcomes and stop-on-unverified behavior. Additive migrations and permission-checked ERP forms now persist shortcut-key positions and label-template drafts. These are saved ERP definitions only; physical key/template upload and live weight/transaction reads remain unverified and are not presented as complete.

**Owner authorization update, 2026-11-08:** The owner explicitly requested direct integration test scripts before UI review. `scripts/scale-integration.php` is the named focused hardware scope. Its current run proves PLU and report operations and reports the missing KEY/D0/D1 write protocol as a blocker.

**Scale settings workspace redesign (owner request):** The scale section of the existing printers tab (no new route or page) is now a status bar (device, IP:port, outlet, connection result, counts) plus internal tabs: Products and prices (product search, PLU suggestion, filterable list, price preview and batch send), Shortcut keys (layer switcher, 63-key visual grid, key picker, read-only compare with the device, send unconfirmed keys), Labels and barcode (barcode print settings with live preview, label template with preview and delete of drafts) and Operations (PLU read, connection check, sale-log report). Barcode print settings (prefix, item identifier and digits, weight or price value, decimals, symbology) are saved in ERP in the new nullable `scale_configurations.barcode_settings` column (migration `2026_11_08_210000`, not yet run on any database). Uploading the barcode format to the device is NOT verified; only the raw `BAR` read is offered.
