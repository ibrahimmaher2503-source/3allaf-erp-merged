# Current Milestone

**Date:** 2026-09-08
**Status:** v0.1.22-hotfix23 is the verified I4 release candidate from exact Hotfix22 commit `163548b2f10f217ebf989589701f98a5a60b49ec`, targeting exact production Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`.

The release restores Hotfix20 desktop shell geometry, makes only the unique internal navigation list scrollable, preserves document/body scroll position during restoration, and removes active-navigation `scrollIntoView` while retaining legitimate tour and validation behavior. Hotfix21 payment and Hotfix22 keyless-navigation behavior remain intact.

The clean replacement Vite assets, actual Laravel layout render, deterministic geometry/runtime proof, Blade/PHP/Livewire, payment, navigation, locale, asset-integrity, and diff checks passed. Browser screenshots are unavailable after the one authorized install attempt failed with ENOSPC; the owner-authorized deterministic fallback is recorded truthfully.

Operator activation remains external and fail-closed from exact Hotfix20 with migration 000111 already Ran and no migration command.
