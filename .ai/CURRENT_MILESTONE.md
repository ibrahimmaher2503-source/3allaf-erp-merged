# Current Milestone

**Date:** 2026-09-10
**Status:** v0.1.22-hotfix28 is the I4 regression-repair candidate from exact Hotfix27 commit `40439ebc3a7e87fb96255addc7796d3c1ef16c8c`.

The bounded release repairs the purchase-order Livewire render data contract that caused both overview and create routes to return HTTP 500. Hotfix27 full-page creation, navigation, supplier-aware product lookup, keyboard/scanner behavior, pricing, totals, authorization, localization, and sidebar restoration remain unchanged.

Focused local PHP, Blade, route, navigation, product-lookup, locale, fixture-runtime, asset-identity, and diff checks pass. No frontend input changed and Vite was not run. The new activation-only production-shaped verifier requires real authenticated Arabic/English HTTP 200 rendering before and after switching; it performs no document write and rolls back its database transaction. Activation failure rolls back to exact Hotfix26 rather than broken Hotfix27.
