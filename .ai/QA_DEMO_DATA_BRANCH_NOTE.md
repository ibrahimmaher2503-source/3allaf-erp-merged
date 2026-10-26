# QA/demo data framework branch continuity

- Date: 2026-10-26.
- Branch: `feature/qa-demo-data-framework-20261026`, from main `99bb8b6d12c454f58eadec0520996cb0721eadd1`.
- Scope: development/QA only. No Production database, user, environment file, release, deployment, or main branch change.
- Framework: conditionally registered QA reset command for `testing`/`qa`, runtime environment and disposable database guard, factories/Faker, modular catalog/pricing/inventory/purchasing/POS/stocktake seeders, focused disposable MariaDB tests, and `docs/qa-demo-data.md`.
- Verification: disposable socket-only MariaDB focused tests passed 5 tests / 94 assertions across the main gate and separate inventory/POS mode gate. `--demo` rerun and `--empty` were included. Production Artisan reported the QA reset command undefined even when the QA enable flag was true; direct demo seeding threw the production guard. Changed PHP syntax, Pint, and diff hygiene passed. No Production access, merge, or deployment occurred.
- The canonical five Production-state `.ai` files remain main-only under `.ai/CURRENT_TASK.md`; after any future merge/deployment, update them with the verified release result. This branch note records the current unmerged state without changing Production continuity.
