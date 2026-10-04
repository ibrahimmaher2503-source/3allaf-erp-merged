<?php

declare(strict_types=1);
/** New Phase 2 invariants only; shared by the scanner and populated hardening fixture. */
function qaPhase2Invariants(PDO $pdo): array
{
    $checks = [
        'capture never exceeds payable' => 'SELECT COUNT(*) FROM sales s JOIN (SELECT sale_id,SUM(amount) amount FROM sale_payments GROUP BY sale_id) p ON p.sale_id=s.id WHERE p.amount>s.payable_total',
        'refund never exceeds original tender' => 'SELECT COUNT(*) FROM sale_payments p JOIN (SELECT original_payment_id,SUM(amount) amount FROM retail_return_settlements WHERE direction="refund" AND original_payment_id IS NOT NULL GROUP BY original_payment_id) r ON r.original_payment_id=p.id WHERE r.amount>p.amount',
        'closed shift has no active assignment' => 'SELECT COUNT(*) FROM active_pos_shift_assignments a JOIN pos_shifts s ON s.id=a.shift_id WHERE s.status IN ("closed","cancelled")',
        'one active assignment per drawer' => 'SELECT COUNT(*) FROM (SELECT cash_drawer_id FROM active_pos_shift_assignments GROUP BY cash_drawer_id HAVING COUNT(*)>1) x',
        'one active assignment per cashier' => 'SELECT COUNT(*) FROM (SELECT cashier_id FROM active_pos_shift_assignments GROUP BY cashier_id HAVING COUNT(*)>1) x',
    ];
    $results = [];
    foreach ($checks as $name => $sql) {
        $count = (int) $pdo->query($sql)->fetchColumn();
        $results[$name] = $count;
        if ($count !== 0) {
            throw new RuntimeException('FAIL Phase 2 invariant '.$name.': '.$count);
        }
    }

    return $results;
}
