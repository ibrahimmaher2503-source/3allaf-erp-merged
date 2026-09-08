# Current Task - v0.1.22-hotfix23 sidebar layout repair

**Date:** 2026-09-08
**Change class:** I4
**Source baseline:** exact Hotfix22 commit `163548b2f10f217ebf989589701f98a5a60b49ec`
**Production baseline:** exact Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`
**Branch:** `hotfix/v0.1.22-hotfix23-sidebar-layout`
**Status:** Implementation, the clean replacement production Vite build, actual Laravel render, deterministic layout proof, and focused verification are complete. Immutable operator packaging remains undeployed.

Hotfix23 restores the exact Hotfix20 desktop grid relationship, confines scrolling/restoration to one explicit internal `[data-sidebar-scroll]` container, removes both active-navigation `scrollIntoView` paths, and preserves the existing collapsed-mode preference, RTL/LTR, mobile drawer, Hotfix21 payments, and Hotfix22 navigation normalization.

The single authorized browser installation attempt failed with ENOSPC, so screenshot evidence is unavailable and no visual PASS is claimed. The owner-authorized deterministic fallback passed against the actual rendered layout and replacement assets.

No migration was added or run. Activation must accept only exact Hotfix20, require migration 000111 already Ran without executing migrations, run the layout/navigation runtime contract as `rajeh` before switching, and roll back exactly to Hotfix20 on failure.
