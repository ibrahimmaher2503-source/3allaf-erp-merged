# Current Milestone

**Date:** 2026-09-05
**Status:** v0.1.22-hotfix15 is the complete D3/I4 closure candidate for the explicitly requested four-scope batch from exact Hotfix14 commit `32c13a8e54f8dad45691a88318f9b475ff944f15`.

The candidate removes only the named duplicate navigation/readiness/settings UI, preserves canonical routes and sidebar destinations, fixes permission selection state and required Arabic printer/POS approval text, replaces static XLSX import downloads with current company-scoped schema-identified workbooks, adds bounded server-side product/location assignment and opening-inventory workflows, and centralizes safe product archive/dependency-free removal.

Hotfix14 pricing/cash rounding and Hotfix13 drawer integrity remain preserved. The production prerequisite is the operator-verified exact Hotfix14 release with migrations through 000108 Ran, maintenance OFF, and HTTPS smoke passing. Hotfix15 adds migration 000109. The QA/UAT product audit and destructive cleanup remain separate root-only operator activities and were not run.

One focused database-free release verification pass, documentation closure, commit, and immutable artifact/operator-script preparation finish the local milestone. Production access, backup, migration execution, promotion, activation, cleanup, tag, push, and remote contact remain unexecuted.
