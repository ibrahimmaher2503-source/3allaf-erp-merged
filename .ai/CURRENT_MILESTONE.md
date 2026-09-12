# Current Milestone

**Date:** 2026-09-12
**Status:** v0.1.22-hotfix34 is the immutable I4 replacement for failed Hotfix33, rebuilt from exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a`.

The release preserves the accepted distinct `ar-EG` locale and replaces only locale-switch dispatch with an invokable, strict-allowlist controller that is safe under Laravel route caching. Full RTL/Cairo rendering, exact `lang="ar-EG"`, three-way switching, session/cookie persistence, and fallback `ar-EG → ar → en` remain. Arabic `ar`, English `en`, all business behavior, database queries, permissions, calculations, posting, migrations, and every Hotfix24–Hotfix32 repair remain unchanged.

Exactly one focused packaged-candidate verification is permitted. Frontend inputs are unchanged, exact Hotfix33 compiled assets are reused, and Vite build count is zero. Promotion and activation remain unexecuted external root-operator steps. Migration 000111 must already be `Ran`; no migration is added or executed.
