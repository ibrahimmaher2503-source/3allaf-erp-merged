# TOY & JOY — Testing Documentation

Entry point for the testing documentation set. Start with `docs/14-test-plan.md`.

## Documents

| File | Read it when |
|---|---|
| `../14-test-plan.md` | You need the overall strategy: levels, tiers, entry/exit criteria, environments, responsibilities. **Start here.** |
| `01-test-taxonomy-and-triggers.md` | You need to know what a test type proves, when it runs, its exact command, and what separates a real pass from a hollow one. |
| `02-traceability-matrix.md` | You need to know whether a requirement is tested, or which tests a requirement needs. All 72 PRD IDs. |
| `03-test-catalog.md` | You are writing tests for a module and need the concrete case list, including the mandatory negative cases. |
| `04-cross-cutting-test-suite.md` | You are implementing approval, attachments, immutability, numbering, idempotency, scope, export, print, audit, errors, or concurrency. |
| `05-manual-checklists.md` | A human check is due: visual review, milestone review, UAT, device test, DR, security review, migration rehearsal, go/no-go. |
| `06-test-data-and-environments.md` | You are writing factories or scenario builders, or preparing an environment. Also the data protection rules. |
| `07-agent-test-protocol.md` | You are an AI agent working in this repo, or you are configuring one. |

## Commands

```bash
bash scripts/run-tests.sh task        # after any implementation task
bash scripts/run-tests.sh milestone   # at the end of a milestone
bash scripts/run-tests.sh preprod     # before a production release
```

Reports land in `docs/testing/reports/`.

## The Three Rules That Matter Most

1. **Negative tests are not optional.** In this system, proving that a cashier *cannot* see a party wallet matters more than proving they can see a product list. The blocked-transition index in `03-test-catalog.md` is a milestone gate.

2. **Tier A requirements get the effort.** Thirty-one of the 72 requirements have irreversible financial or stock consequences. They need concurrency and audit coverage, not just feature tests. `02-traceability-matrix.md` lists them.

3. **Human checks stay human.** No script and no agent may sign a manual review, UAT, device test, or DR exercise. An empty checkbox is honest; a filled one without a name is not.

## Reports Folder

`docs/testing/reports/` holds generated run reports and completed manual checklists. Reports are evidence — commit them with the work they certify, and never edit one to change a result. Fix the code and produce a new report instead.
