# Current Milestone

**Date:** 2026-09-21

**Status:** Hotfix45 application commit `7a327b93abc48987eab7238e6bcd125635413fbb` is deployed at `https://rajeh.xyz` from exact Hotfix44.

Purchase-order and purchase-invoice line/document totals now remain synchronized through Livewire morphs and use the existing financial calculators for discount and inclusive/exclusive tax treatment. Purchase cost is visible and editable under existing authorization, supplier/general/average-cost fallback remains authoritative, and Base Consumer Price List 0 is kept separate from purchase cost. Explanations are in contextual help rather than ordinary inline notes.

The release is schema-free. Fresh encrypted backup and isolated restore/reconciliation passed; focused order/invoice save-reload verification passed with transaction rollback; production activation, runtime restoration, read-only authenticated rendering, and live browser interaction passed. Deployment journal: `/var/lib/toyjoy/hotfix45-deployment/54f67f3a27b35e6e04`.
