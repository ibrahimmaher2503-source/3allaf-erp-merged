# Current Task - v0.1.22-hotfix21 cash checkout and persistent sidebar

**Date:** 2026-09-08
**Change class:** I4
**Source baseline:** exact production Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`
**Branch:** `hotfix/v0.1.22-hotfix21-cash-sidebar`
**Status:** Hotfix21 implementation and focused verification are complete; immutable operator packaging is in progress and remains undeployed.

Hotfix21 makes checkout calculations decimal-safe and consistent between browser and server, blocks cash underpayment and invalid/duplicate/overallocated portions, and renders remaining versus customer change correctly. The desktop sidebar is fixed independently of page scrolling, keeps its own vertical/no-horizontal scroll region, and preserves scroll position and expanded/active navigation state across full and Livewire navigation while retaining logical RTL, collapsed desktop, and mobile drawer behavior.

Exactly one Vite production build generated the Hotfix21 assets. No migration was added or run. Activation must accept only exact Hotfix20, require migration 000111 already Ran, prepare and verify `bootstrap/cache` as `rajeh`, authenticate packaged assets, and roll back exactly to Hotfix20 on any failure.
