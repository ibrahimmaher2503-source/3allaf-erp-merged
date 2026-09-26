# Current Milestone

**Date:** 2026-09-26

**Status:** Hotfix46 commit `345c3af3f34a9e0ecd75e6694f3183f8f345cb99` is deployed at `https://rajeh.xyz` from exact Hotfix45.

The reported role-based System Administrator can now list and manage users through the existing authorized global user-management boundary while remaining a non-super-admin for operational tenant and maker/checker enforcement. Admin-created accounts are verified in the server transaction, no verification notification is sent, and creator/verifier identity is audited. Exact validation messages replace the generic authorization banner text.

Fresh platform backup, isolated MariaDB restore, one rolled-back creation/login/list/role check, targeted live repair, atomic activation, runtime restoration and live read-only verification passed. Deployment evidence: `/var/lib/toyjoy/hotfix46-deployment/345c3af3f34a-20260926`; isolated evidence: `/var/lib/toyjoy/hotfix46-validation.141Les2c`.
