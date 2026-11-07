# Browser role matrix

| Role | Login source | Protected screens exercised | Expected denial |
| --- | --- | --- | --- |
| system-administrator | `LocalAuthSeeder` via environment password | dashboard, administration roles, catalog, inventory | none for assigned scope |
| branch-manager | `LocalAuthSeeder` via environment password | to be expanded in Task 3-11 | administration routes without permission |
| cashier | `LocalAuthSeeder` via environment password | dashboard, catalog products | `/admin/roles` |
| purchasing-officer | `LocalAuthSeeder` via environment password | purchasing routes in Task 5 | administration and unrelated routes |
| warehouse-manager | `LocalAuthSeeder` via environment password | inventory balances | `/admin/roles` |
| pricing-officer | `LocalAuthSeeder` via environment password | pricing lists | `/admin/roles` |
| party-manager | `LocalAuthSeeder` via environment password | party/customer routes in Task 4 and Task 11 | unrelated operational routes |
| stock-counter | `LocalAuthSeeder` via environment password | stock count routes in Task 6 | unrelated approval routes |
| accountant-reviewer | `LocalAuthSeeder` via environment password | reports and ledgers in Tasks 9-10 | unrelated create routes |

Passwords stay in process environment variables and are not stored in this file.
