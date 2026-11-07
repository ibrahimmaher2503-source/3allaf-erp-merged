<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$dump=getenv('QA_STORAGE').'/qa.sql';$p=proc_open(['mariadb-dump','--no-defaults','--socket='.getenv('DB_SOCKET'),'-uroot','--single-transaction',getenv('DB_DATABASE')],[0=>STDIN,1=>['file',$dump,'w'],2=>STDERR],$pipes);
if(!is_resource($p)||proc_close($p)!==0)exit(1);
$zip=new ZipArchive;if($zip->open($argv[1],ZipArchive::CREATE)!==true)exit(1);$zip->addFile($dump,'qa.sql');$zip->close();
$tables=['products','purchase_invoices','stock_movements','sales','sale_payments','retail_returns','retail_return_settlements'];$expected=[];foreach($tables as $table)$expected[$table]=Illuminate\Support\Facades\DB::table($table)->count();file_put_contents(getenv('QA_STORAGE').'/backup-counts.json',json_encode($expected));
echo "PASS backup of disposable QA schema and records only\n";
