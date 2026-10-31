<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';

use App\Models\User;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Models\Sale;

$fixture = qaFixture();
PaymentMethod::query()->firstOrCreate(['code' => 'QA-GIFT-CARD'], [
    'name_ar' => 'بطاقة هدية اختبار', 'name_en' => 'QA Gift Card',
    'type' => 'gift_card', 'status' => 'active', 'requires_evidence' => false, 'offline_eligible' => false,
]);
BranchSellingStore::query()->firstOrCreate(['branch_id' => $fixture['branch']->id, 'store_id' => $fixture['store']->id], [
    'status' => 'active', 'effective_from' => now()->subDay(), 'created_by' => $fixture['admin']->id,
]);
$cashier = User::query()->firstOrCreate(['username' => 'qa-dokki-cashier'], [
    'name' => 'QA Dokki cashier', 'email' => 'qa-dokki-cashier@example.test',
    'password' => bin2hex(random_bytes(20)), 'status' => 'active', 'email_verified_at' => now(),
]);
$cashier->roles()->syncWithoutDetaching([Role::query()->where('code', 'cashier')->firstOrFail()->id]);
$cashier->branchScopes()->updateOrCreate(['branch_id' => $fixture['branch']->id], ['status' => 'active']);
$cashier->storeScopes()->updateOrCreate(['store_id' => $fixture['store']->id], ['status' => 'active']);
ProductOptionGroup::query()->firstOrCreate(['code' => 'QA-OPTION-GROUP'], [
    'name_ar' => 'مجموعة اختبار', 'name_en' => 'QA option group', 'status' => 'active', 'sort_order' => 1,
]);
$card = app(GiftCardAction::class)->issue($fixture['admin'], '20.00', (int) $fixture['branch']->id,
    (int) $fixture['store']->id, Sale::class, 'qa-gift-batch', 'qa-gift-batch-issue');
file_put_contents(getenv('QA_STORAGE_PATH').'/cashier-gift-batch.json', json_encode([
    'cashier' => $cashier->name,
    'branch_id' => $fixture['branch']->id,
    'store_id' => $fixture['store']->id,
    'card_code' => $card->identifier,
    'card_id' => $card->id,
    'sale_number' => 'QA-POS-LOOKUP-001',
], JSON_THROW_ON_ERROR));
echo 'PASS disposable cashier, Gift Card, filter group and sale ready'.PHP_EOL;
