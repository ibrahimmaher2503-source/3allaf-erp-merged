# Current Task - v0.1.22-hotfix22 navigation runtime normalization

**Date:** 2026-09-08
**Change class:** I4
**Source baseline:** exact Hotfix21 commit `63d542558fc301f7b6554da5c5747ca2f4124a46`
**Production baseline:** exact Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`
**Branch:** `hotfix/v0.1.22-hotfix22-navigation-runtime`
**Status:** Hotfix22 implementation, the single Vite production build, and focused verification are complete; immutable operator packaging remains undeployed.

Hotfix22 preserves Hotfix21 cash/split settlement and persistent sidebar behavior while normalizing every renderer-facing navigation group, item, subgroup, heading, and separator. Missing optional fields are defaulted before Blade rendering, and missing keys receive deterministic hierarchy/route/label/index-derived fallback identities. The confirmed keyless Initial Setup navigation item now renders safely.

No migration was added or run. Activation must accept only exact Hotfix20, require migration 000111 already Ran, run the authenticated dashboard/navigation runtime contract as `rajeh`, authenticate packaged Vite assets, and complete all cache/render preflights before switching. Any failure must retain or restore exact Hotfix20.
