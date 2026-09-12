# Current Milestone

**Date:** 2026-09-13
**Status:** v0.1.22-hotfix36 integrates the Egyptian Arabic quality repair onto exact active Hotfix35 commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`.

The release removes the documented corrupted `ar-EG` prefixes, word ordering, mixed-language values, and malformed presentation copy while preserving 6,115 JSON keys and 244 PHP locale leaves. Full RTL/Cairo rendering, exact `lang="ar-EG"`, three-way switching, session/cookie persistence, fallback `ar-EG → ar → en`, and Hotfix35's controller-based route-cache repair remain. Arabic `ar`, English `en`, business behavior, queries, permissions, routes, search, keyboard workflows, accounting, posting, migrations, and internal identifiers remain unchanged.

Exactly one concise packaged-candidate verification is permitted. Vite inputs are unchanged, exact Hotfix35 compiled assets are reused, and Vite build count is zero. Promotion and activation remain unexecuted external root-operator steps and accept only exact Hotfix35. Migration 000111 must already be `Ran`; no migration is added or executed.
