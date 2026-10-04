# Scoped mutation result

410 generated Infection mutants; 219 killed; 191 escaped; corrected covered MSI **53.41%**. Enforced initial minimum: 50%; improvement target: 70%, reported as a Release warning until reached.

Original stdout claimed 100%; that score is rejected. Infection 0.29 did not recognize JSON-only passing PHPUnit summaries. Original per-mutant records prove 175 genuine kills and 235 misclassified passes. The permanent executable adapter emits a canonical success footer only when the underlying PHPUnit process succeeds. Six added boundary tests then replayed **only the 235 survivors**, producing 44 further kills; 175 original kills were preserved. No application source was changed. Full mutation records stay in ignored local evidence; this compact summary is committed.

Scope: covered arithmetic in POS calculations, DecimalMoney, purchase invoice calculations, product price/cost validation and quantity handling. Payment/refund/authorization/database actions have focused integration evidence but were not mutation-tested. A 53.41% score is not comprehensive financial assurance.
