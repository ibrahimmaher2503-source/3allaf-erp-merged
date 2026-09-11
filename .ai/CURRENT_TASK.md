# Current Task - v0.1.22-hotfix29 purchase-order search and compact layout

**Date:** 2026-09-11
**Change class:** I4
**Source baseline:** exact Hotfix28 commit `97ef774eeb4bee5050a86605a698ba396cfebac1`
**Production baseline:** exact active Hotfix28 path `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix28-97ef774eeb4b`
**Branch:** `hotfix/v0.1.22-hotfix29-purchase-order-search-compact-layout`
**Status:** Source repair, one successful clean production build, and focused local evidence are complete. Release commit and immutable packaging remain; promotion and activation remain unexecuted root-operator actions.

Hotfix29 repairs the Livewire-preserved product-lookup root after supplier-driven child morphs and prevents an empty product ID from triggering a competing Livewire request during incremental typing. It preserves unique-exact and multiple-match keyboard safeguards. The camera control is an adjacent button, locale direction/alignment is explicit, and the full-page editor uses compact desktop metadata and line grids, a bounded sticky-header line area, end-positioned notes, and the full available width.

No migration, production mutation, deployment, sudo/root execution, push, tag, package/browser installation, or unrelated change is authorized. Exactly one Node 20 production Vite build is required after all frontend edits. Activation must accept only exact Hotfix28, require migration 000111 already Ran, verify both authenticated routes before and after switching, and atomically restore exact Hotfix28 after any post-switch failure.
