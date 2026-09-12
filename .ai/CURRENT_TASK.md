# Current Task — Egyptian Arabic locale quality repair

**Date:** 2026-09-13
**Change class:** V0 (UI/localization only)
**Source baseline:** exact Hotfix35 commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`
**Branch:** `ui/egyptian-arabic-locale-quality-repair`
**Status:** Complete; focused verification passed and the result is included in the local handoff commit.

Repair the deployed `ar-EG` presentation quality without changing `ar`, `en`, business logic, routes, queries, permissions, database behavior, compiled assets, or locale infrastructure. Remove every literal `بالمصري:` prefix, rewrite malformed and mixed-language values as concise professional Egyptian Arabic, and review representative product, dashboard, alert, approval, sales, purchasing, and inventory surfaces.

No automated suite, browser-control run, Vite build, database operation, migration, production access, deployment, packaging, activation, push, or tag is authorized. The final focused pass covers locale/PHP syntax and integrity, unchanged `ar`/`en`, representative rendered-source wording, RTL/Cairo/switching preservation, corruption-marker absence, and Git diff hygiene.
