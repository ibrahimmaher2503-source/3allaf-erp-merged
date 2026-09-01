<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:diagnose-fractional-quantities', function () {
    $targets=['stock_balances'=>['on_hand','reserved','in_transit'],'stock_movements'=>['quantity'],'purchase_invoice_lines'=>['quantity'],'purchase_invoice_distributions'=>['quantity'],'stock_transfer_lines'=>['quantity_requested','quantity_dispatched','quantity_received'],'sale_lines'=>['quantity'],'purchase_return_lines'=>['quantity'],'opening_inventory_lines'=>['quantity'],'inventory_adjustment_lines'=>['quantity_delta'],'stock_count_lines'=>['expected_quantity','counted_quantity','variance_quantity']];
    $total=0;
    foreach($targets as $table=>$columns){if(!Schema::hasTable($table))continue;foreach($columns as $column){if(!Schema::hasColumn($table,$column))continue;$count=DB::table($table)->whereNotNull($column)->whereRaw("MOD(ABS($column), 1) <> 0")->count();if($count){$this->line("$table.$column: $count");$total+=$count;}}}
    $this->info($total===0?__('No fractional product quantities were found.'):__('Fractional quantity cells found: :count. No data was changed.',['count'=>$total]));
    return $total===0?0:2;
})->purpose('Read-only report of fractional product quantities; never changes data.');

Schedule::command('platform:backup:run')
    ->dailyAt('02:30')
    ->name('platform-backup-run')
    ->withoutOverlapping(120);

Schedule::command('backup:clean --disable-notifications --isolated')
    ->dailyAt('03:30')
    ->name('platform-backup-clean')
    ->withoutOverlapping(120);

Schedule::command('backup:monitor --isolated')
    ->hourly()
    ->name('platform-backup-monitor')
    ->withoutOverlapping(30);

if (app()->environment('staging') && (bool) env('STAGING_SCHEDULER_PROBE', false)) {
    Schedule::command('platform:staging-scheduler-probe')
        ->everyMinute()
        ->name('staging-scheduler-probe')
        ->withoutOverlapping(1);
}
