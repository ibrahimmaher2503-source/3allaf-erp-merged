<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Policies\ApprovalRecordPolicy;
use App\Modules\Platform\Policies\AuditLogPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureQueryObservability();
        Livewire::addNamespace('platform', viewPath: resource_path('views/platform'));
        Livewire::addNamespace('catalog', viewPath: resource_path('views/catalog'));
        Livewire::addNamespace('purchasing', viewPath: resource_path('views/purchasing'));
        Livewire::addNamespace('pricing', viewPath: resource_path('views/pricing'));
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ApprovalRecord::class, ApprovalRecordPolicy::class);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user?->is_super_admin) {
                return true;
            }

            return $user?->hasPermission($ability) ? true : null;
        });

        foreach ([
            'company_settings.view', 'company_settings.create', 'company_settings.edit', 'company_settings.logical_delete',
            'branches_stores.view', 'branches_stores.create', 'branches_stores.edit', 'branches_stores.logical_delete',
            'drawers_payments_tax_numbering_printers.view', 'drawers_payments_tax_numbering_printers.create',
            'drawers_payments_tax_numbering_printers.edit', 'drawers_payments_tax_numbering_printers.logical_delete',
            'users_roles_permissions.view', 'users_roles_permissions.create', 'users_roles_permissions.edit',
            'dashboard_reports.view', 'audit_logs.view', 'product_wallet.view', 'party_wallet.view', 'returns_exchanges_gift_instruments.view', 'pos_sales.view', 'pos_sales.create', 'pos_sales.print',
            'products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit',
            'products_categories_brands.logical_delete', 'products_categories_brands.print', 'products_categories_brands.approve',
            'products_categories_brands.export', 'products_categories_brands.reverse', 'products_categories_brands.cancel',
            'products_categories_brands.override',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.logical_delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.print', 'purchase_orders.cancel', 'purchase_orders.approve',
            'pricing_labels.view', 'pricing_labels.create', 'pricing_labels.edit', 'pricing_labels.submit', 'pricing_labels.approve', 'pricing_labels.reject', 'pricing_labels.export', 'pricing_labels.override',
        ] as $ability) {
            Gate::define($ability, fn (?User $user): bool => $user?->hasPermission($ability) ?? false);
        }

        Gate::define('manage-settings', fn (?User $user): bool => $user?->hasPermission('company_settings.edit') ?? false);
        Gate::define('manage-branches-stores', fn (?User $user): bool => $user?->hasPermission('branches_stores.edit') ?? false);
        Gate::define('view-authorization-baseline', fn (?User $user): bool => $user?->hasPermission('users_roles_permissions.view') ?? false);
        Gate::define('manage-authorization', fn (?User $user): bool => $user?->hasPermission('users_roles_permissions.edit') ?? false);
        Gate::define('view-platform-status', fn (?User $user): bool => $user?->hasPermission('audit_logs.view') ?? false);
        Gate::define('view-ui-showcase', fn (?User $user): bool => $user?->hasPermission('dashboard_reports.view') ?? false);
    }

    protected function configureQueryObservability(): void
    {
        if (! config('performance.query_budget_enabled') && ! config('performance.slow_query_logging_enabled')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $slowQueryMs = (float) config('performance.slow_query_ms', 100);

            if (config('performance.slow_query_logging_enabled') && $query->time >= $slowQueryMs) {
                Log::channel('slow_queries')->warning('Slow database query detected.', [
                    'connection' => $query->connectionName,
                    'duration_ms' => $query->time,
                    'sql' => $query->sql,
                    'bindings_count' => count($query->bindings),
                    'route' => app()->bound('request') ? request()->route()?->getName() : null,
                ]);
            }

            if (! config('performance.query_budget_enabled') || app()->runningInConsole() || ! app()->bound('request')) {
                return;
            }

            $request = request();
            $queryCount = ((int) $request->attributes->get('query_budget_count', 0)) + 1;
            $request->attributes->set('query_budget_count', $queryCount);

            $queryBudget = (int) config('performance.query_budget', 100);
            if ($queryCount > $queryBudget && ! $request->attributes->get('query_budget_exceeded')) {
                $request->attributes->set('query_budget_exceeded', true);
                Log::warning('Database query budget exceeded; request aborted.', [
                    'budget' => $queryBudget,
                    'count' => $queryCount,
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                ]);

                throw new \RuntimeException(sprintf(
                    'Query budget exceeded: %d queries (budget: %d).',
                    $queryCount,
                    $queryBudget,
                ));
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
