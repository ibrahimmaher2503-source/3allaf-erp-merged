<?php
declare(strict_types=1);

if (getenv('APP_ENV') !== 'qa' || !preg_match('/^qa_rajh_[a-zA-Z0-9_]{1,50}$/', (string)getenv('DB_DATABASE')) || !str_starts_with((string)getenv('DB_SOCKET'),'/tmp/rajeh-qa-mariadb.')) throw new RuntimeException('Unsafe integrity target');
$pdo = new PDO(sprintf('mysql:unix_socket=%s;dbname=%s', getenv('DB_SOCKET'), getenv('DB_DATABASE')), getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db = (string) getenv('DB_DATABASE');
$failures = [];
$warnings = [];
$tables = [];
foreach ($pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = database()') as $row) $tables[$row['table_name']] = true;
$columns = static function (string $table) use ($pdo): array { $q=$pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=database() AND table_name=?'); $q->execute([$table]); return array_column($q->fetchAll(), 'column_name'); };
$hasTable = static fn(string $table): bool => isset($tables[$table]);
$hasColumn = static function (string $table, string $column) use ($columns): bool { return in_array($column, $columns($table), true); };
$assertZero = static function (string $label, string $sql, array $params = []) use ($pdo, &$failures): void { try { $q=$pdo->prepare($sql); $q->execute($params); $n=(int)$q->fetchColumn(); if ($n>0) $failures[]="$label ($n)"; } catch (Throwable $e) { $failures[]="$label query error: ".$e->getMessage(); } };
$expected = ['companies','users','products','suppliers','purchase_invoices','purchase_invoice_lines','stock_movements','stock_balances','sales','sale_lines','sale_payments','retail_returns','retail_return_lines','pos_shifts','migrations'];
foreach ($expected as $table) if (!$hasTable($table)) $failures[]="expected table absent: $table";
if ($hasTable('migrations')) {
  $migrationFiles = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];
  $applied = (int) $pdo->query('SELECT count(*) FROM migrations')->fetchColumn();
  if ($applied < count($migrationFiles)) $failures[]="pending migrations (applied=$applied files=".count($migrationFiles).')';
}
$fkSql = "SELECT table_name,column_name,referenced_table_name,referenced_column_name FROM information_schema.key_column_usage WHERE table_schema=database() AND referenced_table_name IS NOT NULL AND referenced_column_name IS NOT NULL";
foreach ($pdo->query($fkSql) as $fk) {
  $sql = sprintf('SELECT count(*) FROM `%s` c LEFT JOIN `%s` p ON c.`%s`=p.`%s` WHERE c.`%s` IS NOT NULL AND p.`%s` IS NULL', $fk['table_name'], $fk['referenced_table_name'], $fk['column_name'], $fk['referenced_column_name'], $fk['column_name'], $fk['referenced_column_name']);
  $assertZero('orphan FK '.$fk['table_name'].'.'.$fk['column_name'], $sql);
}
if ($hasTable('products') && $hasColumn('products','item_code')) $assertZero('duplicate product item_code', 'SELECT count(*) FROM (SELECT item_code FROM products WHERE item_code IS NOT NULL AND item_code<>"" GROUP BY item_code HAVING count(*)>1) x');
if ($hasTable('barcodes') && $hasColumn('barcodes','barcode')) $assertZero('duplicate barcode identity', 'SELECT count(*) FROM (SELECT barcode FROM barcodes GROUP BY barcode HAVING count(*)>1) x');
$states=['products'=>['active','inactive'],'suppliers'=>['active','inactive'],'purchase_invoices'=>['draft','submitted','awaiting_distribution','approved','rejected','cancelled','reversed'],'sales'=>['draft','suspended','awaiting_approval','approved','cancelled'],'retail_returns'=>['draft','inspection','submitted','approved','completed','rejected','cancelled'],'pos_shifts'=>['open','closing_submitted','variance_review','closed','cancelled']];
foreach($states as $table=>$allowed) if($hasTable($table)) $assertZero('invalid status '.$table, "SELECT COUNT(*) FROM `$table` WHERE status IS NULL OR status NOT IN (".implode(',',array_fill(0,count($allowed),'?')).")",$allowed);
if ($hasTable('product_suppliers')) { if ($hasColumn('product_suppliers','product_id') && $hasColumn('product_suppliers','supplier_id')) $assertZero('broken product supplier pivot', 'SELECT count(*) FROM product_suppliers ps LEFT JOIN products p ON p.id=ps.product_id LEFT JOIN suppliers s ON s.id=ps.supplier_id WHERE p.id IS NULL OR s.id IS NULL'); }
if ($hasTable('stock_movements')) { $source = $hasColumn('stock_movements','source_id') && $hasColumn('stock_movements','source_type'); if ($source) $assertZero('stock movement without source', "SELECT count(*) FROM stock_movements WHERE source_id IS NULL OR source_type IS NULL OR source_type=''", []); }
if ($hasTable('purchase_invoice_lines') && $hasTable('purchase_invoices') && $hasColumn('purchase_invoice_lines','line_total') && $hasColumn('purchase_invoices','subtotal')) $assertZero('purchase header/line total mismatch', 'SELECT count(*) FROM (SELECT i.id FROM purchase_invoices i JOIN (SELECT purchase_invoice_id,SUM(line_total) total FROM purchase_invoice_lines GROUP BY purchase_invoice_id) x ON x.purchase_invoice_id=i.id WHERE ABS(COALESCE(i.total_amount,0)-x.total)>0.01) y');
if ($hasTable('sale_payments') && $hasTable('sales')) $assertZero('payment without sale', 'SELECT count(*) FROM sale_payments p LEFT JOIN sales s ON s.id=p.sale_id WHERE s.id IS NULL');
if ($hasTable('retail_return_lines') && $hasColumn('retail_return_lines','quantity')) $assertZero('negative return quantity', 'SELECT count(*) FROM retail_return_lines WHERE quantity<=0');

$assertZero('stock balance/movement quantity mismatch', 'SELECT COUNT(*) FROM stock_balances b LEFT JOIN (SELECT product_id,store_id,SUM(quantity) q FROM stock_movements GROUP BY product_id,store_id) m ON m.product_id=b.product_id AND m.store_id=b.store_id WHERE ABS(b.on_hand-COALESCE(m.q,0))>0.000001');
$assertZero('sale subtotal/line gross mismatch', 'SELECT COUNT(*) FROM sales s JOIN (SELECT sale_id,SUM(gross_amount) gross FROM sale_lines GROUP BY sale_id) l ON l.sale_id=s.id WHERE ABS(s.subtotal-l.gross)>0.01');
$assertZero('invalid sale quantities', 'SELECT COUNT(*) FROM sale_lines WHERE quantity<=0');
$assertZero('invalid purchase quantities', 'SELECT COUNT(*) FROM purchase_invoice_lines WHERE quantity<=0');
$assertZero('approved sale payment mismatch', 'SELECT COUNT(*) FROM sales s LEFT JOIN (SELECT sale_id,SUM(amount) total FROM sale_payments GROUP BY sale_id) p ON p.sale_id=s.id WHERE s.status="approved" AND ABS(s.payable_total-COALESCE(p.total,0))>0.01');
$assertZero('completed refund settlement mismatch', 'SELECT COUNT(*) FROM retail_returns r LEFT JOIN (SELECT retail_return_id,SUM(amount) total FROM retail_return_settlements GROUP BY retail_return_id) p ON p.retail_return_id=r.id WHERE r.status="completed" AND ABS(r.settlement_value-COALESCE(p.total,0))>0.01');
$assertZero('over-returned sale quantity', 'SELECT COUNT(*) FROM (SELECT l.sale_line_id,SUM(l.quantity) q FROM retail_return_lines l JOIN retail_returns r ON r.id=l.retail_return_id WHERE r.status IN ("draft","inspection","submitted","approved","completed") GROUP BY l.sale_line_id) x JOIN sale_lines s ON s.id=x.sale_line_id WHERE x.q>s.quantity');
$assertZero('conflicting effective price versions', 'SELECT COUNT(*) FROM price_lines a JOIN price_versions va ON va.id=a.price_version_id JOIN price_lines b ON b.product_id=a.product_id AND b.store_id=a.store_id AND b.price_version_id>a.price_version_id JOIN price_versions vb ON vb.id=b.price_version_id WHERE va.state="approved" AND vb.state="approved" AND (va.effective_to IS NULL OR vb.effective_from IS NULL OR va.effective_to>vb.effective_from) AND (vb.effective_to IS NULL OR va.effective_from IS NULL OR vb.effective_to>va.effective_from)');
$assertZero('descriptive filter assigned directly to variant', 'SELECT COUNT(*) FROM product_filter_values f JOIN products p ON p.id=f.product_id WHERE p.parent_product_id IS NOT NULL');
$assertZero('variant value assigned to non-variant', 'SELECT COUNT(*) FROM product_variant_values v JOIN products p ON p.id=v.product_id WHERE p.parent_product_id IS NULL');
foreach ($warnings as $warning) echo "WARN $warning\n";
foreach ($failures as $failure) echo "FAIL $failure\n";
if ($failures !== []) exit(1);
echo "PASS database invariants: foreign keys, identities, pivots, statuses, header/line totals, stock ledger, payments, returns, effective prices, filter/variant separation, migrations.\n";
