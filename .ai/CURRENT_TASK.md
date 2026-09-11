# Current Task - v0.1.22-hotfix30 purchase-order compiled browser search

**Date:** 2026-09-11
**Change class:** I4
**Source baseline:** exact Hotfix29 commit `40d894259928c475eac3f902686e20c882e4c370`
**Production baseline:** exact active Hotfix29 path `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix29-40d894259928`
**Branch:** `hotfix/v0.1.22-hotfix30-purchase-order-browser-search`
**Status:** Browser lifecycle repair, one clean production build, and focused compiled-DOM evidence are complete. Release commit and immutable packaging remain; promotion and activation remain unexecuted root-operator actions.

Hotfix30 preserves the Hotfix29 compact page layout exactly and repairs the missed browser visibility contract. Lookup feedback is rendered into a fixed body overlay outside the bounded line scroller, controller binding covers initial load, Livewire initialization/navigation/morph hooks, mutation fallback, and added rows, and request failures now remain visibly localized and fail closed.

No migration, production access/change, deployment, sudo/root execution, push, tag, package/browser installation, layout redesign, or unrelated change is authorized. Exactly one Node 20 production Vite build is required after all frontend edits. Activation must accept only exact Hotfix29, require migration 000111 already Ran, run authenticated bilingual HTTP and compiled browser-DOM checks before and after switching, and atomically restore exact Hotfix29 after any post-switch failure.
