# Current Task — v0.1.22-hotfix36 Egyptian localization quality repair

**Date:** 2026-09-13
**Change class:** I4 release of completed V0 repair
**Source baseline:** exact Hotfix35 commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`
**Repair source:** `80156c9ab50d9b76c19a797dc30db487b694ecb2`
**Branch:** `hotfix/v0.1.22-hotfix36-egyptian-localization-quality-repair`
**Status:** Hotfix36 integrates the completed repair onto exact active Hotfix35; immutable packaging and unexecuted operator scripts are in progress.

Repair the deployed `ar-EG` presentation quality without changing `ar`, `en`, business logic, routes, queries, permissions, database behavior, compiled assets, or locale infrastructure. Remove every literal `بالمصري:` prefix, rewrite malformed and mixed-language values as concise professional Egyptian Arabic, and review representative product, dashboard, alert, approval, sales, purchasing, and inventory surfaces.

One concise release-stage verification is authorized. No automated suite, browser-control run, Vite build, database operation, migration, production access, deployment, activation, push, or tag is authorized. Promotion and activation must accept only exact Hotfix35, require migration 000111 already `Ran`, execute no migration, verify authenticated `ar`, `en`, and `ar-EG` representative routes before and after switching, fail closed, and restore exact Hotfix35 after any post-switch failure.
