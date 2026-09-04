# Current Milestone

**Date:** 2026-09-04
**Status:** v0.1.22-hotfix12 corrects the POS shift-opening route collision from exact Hotfix11 commit `405c0a2a667438f4383e41ca538234f8be54e05c`. The functional diff is limited to canonical route names, an authenticated and permission-gated compatibility redirect, the existing form action, and the successful post-create destination.

Hotfix11 remains the exact production and rollback baseline. Existing verified Hotfix11 vendor and compiled frontend assets are reused without Composer or Vite. Promotion and activation are prepared only; production remains untouched unless an authorized root operator executes them.
