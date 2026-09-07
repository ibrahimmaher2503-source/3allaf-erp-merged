# Current Task - v0.1.22-hotfix20 POS production assets

**Date:** 2026-09-07
**Change class:** I4
**Source baseline:** exact Hotfix19 commit `1fd252c6f0e612f8b3a0d4cea3413a53295205a8`
**Branch:** `hotfix/v0.1.22-hotfix20-pos-production-assets`
**Production baseline:** owner-confirmed and not re-checked: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`; migration 000111 is already Ran and must remain Ran.
**Status:** Hotfix20 source and production asset preparation are complete. The immutable operator package uses the newly generated Hotfix20 Vite assets and remains undeployed.

Hotfix20 preserves every Hotfix17-19 POS redesign, refund, Blade repair, and permanent Livewire-root behavior. Tailwind v4 already scans `resources/views` recursively through `@source '../views'`, including every POS and Livewire Blade template, so no content-path or application markup change was needed.

Exactly one clean production Vite build generated manifest SHA-256 `8e5c124882f8b0af416f5a35e28e90adfc2952a38abf70296780b80075c73901` and POS CSS SHA-256 `56e206d8fc38fc2e01c82d5f2b2b8e8ef20947b67fc5feac0124db18c850ec87`. Both differ from Hotfix16. The final archive and activation preflight authenticate these exact assets before any active-symlink change.

Authenticated POS visual rendering was technically unavailable without a safe non-production environment/database, and production access is prohibited. No visual PASS or screenshots are claimed. Activation remains fail-closed on exact Hotfix16, already-Ran migration 000111, packaged asset identity, Livewire root rendering, and `rajeh` bootstrap/cache traversal, ownership, writability, and cache generation. No migration command is executed.
