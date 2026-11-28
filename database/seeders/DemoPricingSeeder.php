<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\EnsureBasePriceList;
use App\Modules\Pricing\Actions\SaveProductPriceOverrideAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceList;
use App\Support\QaDemoSafety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DemoPricingSeeder extends Seeder
{
    public function run(): void
    {
        QaDemoSafety::assertAllowed();
        DB::transaction(function (): void {
            $administrator = User::query()->where('username', 'qa-admin')->firstOrFail();
            $approver = User::query()->where('username', 'qa-approver')->firstOrFail();
            $company = Company::query()->where('code', 'QA-DEMO')->firstOrFail();
            $branch = Branch::query()->where('code', 'QA-SAHIL')->firstOrFail();
            $outlet = Store::query()->where('code', 'QA-SAHIL-OUTLET')->firstOrFail();
            $product = Product::query()->where('item_code', 'QA-PRICE-400')->firstOrFail();

            app(EnsureBasePriceList::class)->execute($company);
            Auth::login($administrator);
            try {
                $proposal = app(CreatePriceProposalAction::class)->execute(
                    $product,
                    $outlet,
                    'QA-SAHIL-PRICE',
                    'قائمة أسعار الساحل التجريبية',
                    'Sahil QA Context Price',
                    '460.00',
                    'branch_exception',
                    'QA-PRICE-400-TO-460',
                    null,
                    null,
                    fake()->sentence(),
                );
                app(SubmitPriceProposalAction::class)->execute($proposal);
                Auth::login($approver);
                app(ApprovePriceProposalAction::class)->execute($proposal);
                $listId = $proposal->price_list_id;
                $branch->update(['default_price_list_id' => $listId]);
                $outlet->update(['price_list_id' => $listId]);
                // 3allaf outlet lists round derived prices up to 5 (64 -> 65); keep the documented 64.00 POS
                // split-sale scenario with an explicit outlet-list price override, as an operator would.
                app(SaveProductPriceOverrideAction::class)->execute(
                    Product::query()->where('item_code', 'QA-POS-64')->firstOrFail(),
                    PriceList::query()->findOrFail($listId),
                    '64.00',
                    'QA demo POS split-sale price.',
                );
            } finally {
                Auth::logout();
            }
        });
    }
}
