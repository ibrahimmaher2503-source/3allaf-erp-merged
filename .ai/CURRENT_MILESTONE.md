# Current Milestone

**Date:** 2026-09-08
**Status:** v0.1.22-hotfix21 is the verified I4 release candidate from exact production Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`.

The release preserves Hotfix20 POS/refund/Livewire-root/Vite/cache/accounting/inventory/permission/idempotency behavior while correcting cash and split-payment allocation and making desktop sidebar scrolling independent and persistent across navigation. One Vite production build produced manifest SHA-256 `1618bfbf5e4ebe3830db4f18bc435648d5f067cb03a24a1c4fe6b7ccab67d71c` with Hotfix21 app JS/CSS.

Operator activation remains external and fail-closed: exact Hotfix20 is the only accepted active baseline and rollback target, migration 000111 must already be Ran, no migration command is allowed, and runtime cache plus packaged asset identity must pass before switching.
