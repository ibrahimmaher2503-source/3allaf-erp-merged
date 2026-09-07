# Current Milestone

**Date:** 2026-09-07
**Status:** v0.1.22-hotfix20 is the corrective I4 release candidate from exact Hotfix19 commit `1fd252c6f0e612f8b3a0d4cea3413a53295205a8`, with newly generated production Vite assets.

Hotfix20 retains the Hotfix17 compact three-column POS, durable order tabs, customer and product lookup, scanner control, compact payment cards, wide checkout dialogs, full refund workflow, Hotfix18 Blade repairs, and Hotfix19 permanent Livewire root and runtime-cache preparation.

Tailwind v4 already includes all Blade templates through `@source '../views'`. One clean Vite build produced Hotfix20 manifest SHA-256 `8e5c124882f8b0af416f5a35e28e90adfc2952a38abf70296780b80075c73901` and app CSS SHA-256 `56e206d8fc38fc2e01c82d5f2b2b8e8ef20947b67fc5feac0124db18c850ec87`, replacing the Hotfix16 asset identity.

Exact active Hotfix16 remains the only activation prerequisite and rollback target. Migration 000111 must already be Ran and is never executed by the operator scripts. Authenticated non-production POS rendering was unavailable, so no screenshot PASS is claimed; the activation script instead verifies the exact packaged Vite manifest/assets, Laravel and Livewire rendering, and runtime cache contract before switching.
