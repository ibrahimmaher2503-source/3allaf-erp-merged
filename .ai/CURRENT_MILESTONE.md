# Current Milestone

**Date:** 2026-09-05
**Status:** v0.1.22-hotfix14 is the focused D3/I4 pricing and POS cash-rounding correction from exact Hotfix13 commit `1c0519dc57b9c5b9af1aa6963dbbb448fce56bbe`.

The release confirms decimal-safe ceiling-to-5 only for automatically percentage-derived selling prices, establishes the canonical append-only POS cash denomination at exactly 5 EGP, keeps nearest rounding limited to cash settlement, and converts missing/invalid cash configuration into a localized fail-closed cash-only state. Base/manual prices, non-cash totals, historical and posted documents, Hotfix13 drawer integrity, authorization, company/store scope, accounting, inventory, and POS lifecycle controls are preserved.

The root operator supplied conclusive exact-Hotfix13 production baseline evidence. No further production access is permitted. The focused database-free verification pass is complete; local immutable release preparation completes this milestone; deployment remains unexecuted and exact Hotfix13 is the prerequisite and rollback release.
