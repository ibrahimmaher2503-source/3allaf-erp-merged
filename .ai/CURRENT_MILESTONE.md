# Current Milestone

**Date:** 2026-09-12
**Status:** v0.1.22-hotfix33 is the bounded I4 Egyptian Arabic integration from exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a`.

The release adds a distinct `ar-EG` locale named `العربية المصرية`, full RTL/Cairo rendering, exact `lang="ar-EG"`, three-way switching with existing session/cookie persistence, and fallback `ar-EG → ar → en`. Arabic `ar`, English `en`, all business behavior, database queries, permissions, calculations, posting, migrations, and every Hotfix24–Hotfix32 repair remain unchanged.

Exactly one focused integration verification and conditional final Node 20 build are permitted. Promotion and activation remain unexecuted external root-operator steps. Migration 000111 must already be `Ran`; no migration is added or executed.
