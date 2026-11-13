<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\OpeningInventoryZeroDecision;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\PrintTemplate;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\SetupDecision;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Models\UserBranchScope;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache key for the setup readiness snapshots.
 *
 * Only models that the readiness rules actually read invalidate the cache, so
 * routine transactional writes (sales, stock movements, audit rows, shifts)
 * no longer force a full readiness rebuild on the next dashboard visit.
 */
final class SetupReadinessCache
{
    private const VERSION_KEY = 'setup-readiness-version';

    private const REQUEST_KEY = 'setup_readiness_version';

    /** Columns a document sequence advances on every allocation; readiness never reads them. */
    private const SEQUENCE_COUNTER_COLUMNS = ['next_value', 'lock_version', 'last_reset_period', 'updated_at'];

    /** @var list<class-string<Model>> */
    public const MODELS = [
        User::class,
        Role::class,
        Permission::class,
        UserBranchScope::class,
        UserStoreScope::class,
        Company::class,
        Branch::class,
        Store::class,
        CashDrawer::class,
        PaymentMethod::class,
        TaxSetting::class,
        DocumentSequence::class,
        PrinterConfiguration::class,
        PrintTemplate::class,
        Category::class,
        Brand::class,
        Product::class,
        ProductUnit::class,
        Barcode::class,
        ProductOptionGroup::class,
        ProductOptionValue::class,
        Supplier::class,
        SupplierGroup::class,
        CustomerGroup::class,
        CustomerPolicySettingVersion::class,
        PriceList::class,
        PriceLine::class,
        ProductPriceOverride::class,
        SetupDecision::class,
        OpeningInventoryDocument::class,
        OpeningInventoryZeroDecision::class,
    ];

    public static function version(): int
    {
        $request = app()->bound('request') ? request() : null;
        if ($request?->attributes->has(self::REQUEST_KEY)) {
            return (int) $request->attributes->get(self::REQUEST_KEY);
        }

        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $request?->attributes->set(self::REQUEST_KEY, $version);

        return $version;
    }

    public static function invalidate(): void
    {
        if (! Cache::add(self::VERSION_KEY, 1)) {
            Cache::increment(self::VERSION_KEY);
        }

        if (app()->bound('request')) {
            request()->attributes->remove(self::REQUEST_KEY);
        }
    }

    public static function handleModelEvent(Model $model): void
    {
        if (! in_array($model::class, self::MODELS, true)) {
            return;
        }

        if ($model instanceof DocumentSequence
            && $model->exists
            && ! $model->wasRecentlyCreated
            && array_diff(array_keys($model->getChanges()), self::SEQUENCE_COUNTER_COLUMNS) === []) {
            return;
        }

        self::invalidate();
    }
}
