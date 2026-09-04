# Current Task — v0.1.22-hotfix12 POS Shift-opening Route

**Date:** 2026-09-04
**Change class:** D3
**Source baseline:** exact Hotfix11 commit `405c0a2a667438f4383e41ca538234f8be54e05c` (tree `03f2c2a6f73b688719fe4336f512174da87cee6b`)
**Branch:** `hotfix/v0.1.22-hotfix12-pos-shift-open-route`
**Status:** The production-blocking POS shift-opening 404 is corrected with a minimal route-only functional change and release version `0.1.22-hotfix12`.

The shift form posts through the dedicated canonical `pos.shift.store` route. A permission-equivalent authenticated compatibility GET at `/pos/shift/open` redirects to the existing canonical `pos.shift` page, and successful shift creation redirects to the intended `pos` destination. The existing `OpenShiftAction` remains unchanged, preserving outlet and drawer scope, opening balance, transactions, idempotency, and duplicate-active-shift prevention.

One focused database-free feature test passed with 1 test / 24 assertions. No migration, seeder, Composer install, Vite build, broad or repeated test, browser check, database operation, production HTTP request, deployment, service, tag, push, or production change occurred.
