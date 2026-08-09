<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Modules\Platform\Actions\SaveUserAuthorizationAction;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-008 — Users, roles, permissions, and scopes (regression).
 *
 * @group tsk-008
 */
class RolePermissionScopeTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    /** Actions documented as `P`/`R` in docs/04-roles-permissions.md, unless the specific code+role pair is an approved exception below. */
    private const NON_GRANTABLE_ACTIONS = ['approve', 'override', 'reverse', 'cancel', 'export', 'logical_delete'];

    /** The only `A`-status print grant in docs/04-roles-permissions.md. */
    private const APPROVED_PRINT_GRANTS = ['pos_sales.print'];

    /**
     * The only `A`-status grant among NON_GRANTABLE_ACTIONS in docs/04-roles-permissions.md:
     * "Pricing & Labels | ... | Approve: Pricing A when configured | ..." (line 44) — Pricing
     * Officer only. Every other approve/override/reverse/cancel/export/logical_delete cell in
     * the matrix is `R`/`P` and stays ungrantable pending owner ratification (QA-002).
     */
    private const APPROVED_SENSITIVE_GRANTS = [
        'pricing_labels.approve' => ['pricing-officer'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_nine_canonical_roles_are_seeded(): void
    {
        $expected = [
            'system-administrator', 'branch-manager', 'cashier', 'purchasing-officer',
            'warehouse-manager', 'pricing-officer', 'party-manager', 'stock-counter',
            'accountant-reviewer',
        ];

        $this->assertSame(count($expected), Role::query()->count());

        foreach ($expected as $code) {
            $role = Role::query()->where('code', $code)->first();
            $this->assertNotNull($role, "Missing canonical role [{$code}].");
            $this->assertSame('active', $role->status);
            $this->assertNotEmpty($role->name_ar);
            $this->assertNotEmpty($role->name_en);
        }
    }

    public function test_the_canonical_permission_catalog_is_seeded(): void
    {
        // 27 modules x 10 actions, plus the six legacy gate aliases.
        //
        // This intentionally still fails at 348: the Permission catalog itself (unlike
        // role grants — see test_no_role_is_granted_an_unapproved_sensitive_permission())
        // is not Production/environment-gated, because a Permission row existing without
        // being granted to any role carries no access risk. The row count diverging from
        // docs/04-roles-permissions.md is a pure documentation-currency question — whether
        // the owner ratifies a doc amendment describing 28 modules x 12 actions to match
        // TSK-014..TSK-022 — not a security defect. See testing/results/DEFECTS.md QA-002.
        $this->assertSame(276, Permission::query()->count());

        foreach (['company_settings', 'branches_stores', 'audit_logs', 'pos_sales', 'party_wallet', 'product_wallet', 'stock_counts'] as $module) {
            foreach (['view', 'create', 'edit', 'logical_delete', 'print', 'approve', 'export', 'reverse', 'cancel', 'override'] as $action) {
                $this->assertDatabaseHas('permissions', ['code' => $module.'.'.$action, 'status' => 'active']);
            }
        }
    }

    /**
     * Checks the Production-only grant map (`CanonicalAuthorizationSeeder::productionSafeRolePermissions()`),
     * not the `testing`-environment DB state seeded by `setUp()`. The seeded DB intentionally carries a
     * broader, owner-authorized Local/Dev-only catalog (DEC-051/052/054/058/059) so the many implemented
     * approval-workflow Feature tests can exercise real behavior; that extended catalog's doc ratification
     * is a separate, still-open question tracked as QA-002 and is not asserted here.
     */
    public function test_no_role_is_granted_an_unapproved_sensitive_permission(): void
    {
        foreach (CanonicalAuthorizationSeeder::productionSafeRolePermissions() as $roleCode => $codes) {
            foreach ($codes as $code) {
                $action = str_contains($code, '.') ? explode('.', $code, 2)[1] : $code;

                if (in_array($action, self::NON_GRANTABLE_ACTIONS, true)) {
                    $this->assertContains(
                        $roleCode,
                        self::APPROVED_SENSITIVE_GRANTS[$code] ?? [],
                        "Permission [{$code}] is documented as P/R and must not be granted to role [{$roleCode}] in Production.",
                    );
                }

                if ($action === 'print') {
                    $this->assertContains($code, self::APPROVED_PRINT_GRANTS, "Print permission [{$code}] is not an approved Production grant.");
                }
            }
        }
    }

    /** @see self::test_no_role_is_granted_an_unapproved_sensitive_permission() for why this checks the Production-only map. */
    public function test_the_seeded_grants_match_the_documented_current_scope_exactly(): void
    {
        $expected = [
            'system-administrator' => [
                'audit_logs.view', 'branches_stores.create', 'branches_stores.edit', 'branches_stores.view',
                'company_settings.create', 'company_settings.edit', 'company_settings.view',
                'dashboard_reports.view',
                'drawers_payments_tax_numbering_printers.create', 'drawers_payments_tax_numbering_printers.edit', 'drawers_payments_tax_numbering_printers.view',
                'users_roles_permissions.create', 'users_roles_permissions.edit', 'users_roles_permissions.view',
            ],
            'branch-manager' => ['branches_stores.view', 'pos_sales.view'],
            'cashier' => ['pos_sales.create', 'pos_sales.print', 'pos_sales.view'],
            'accountant-reviewer' => ['audit_logs.view', 'dashboard_reports.view'],
            'purchasing-officer' => [],
            'warehouse-manager' => [],
            'pricing-officer' => [],
            'party-manager' => [],
            'stock-counter' => [],
        ];

        $productionSafe = CanonicalAuthorizationSeeder::productionSafeRolePermissions();

        foreach ($expected as $roleCode => $codes) {
            $actual = $productionSafe[$roleCode] ?? [];
            sort($codes);
            sort($actual);
            $this->assertSame($codes, $actual, "Role [{$roleCode}]'s Production grants diverge from the documented current scope.");
        }
    }

    public function test_no_role_is_granted_a_permission_for_a_module_that_does_not_exist_yet(): void
    {
        // Grown from the original TSK-008 Foundation set (company_settings..pos_sales) as
        // TSK-010/014/016/017/019-022/027 landed real, gated routes/actions for these
        // modules (see git history + testing/results/DEFECTS.md QA-002 for provenance).
        // `purchase_returns` is a split of the documented `purchase_invoices_supplier_returns`
        // module — real code exists, but the split itself is undocumented drift (QA-002).
        $implementedModules = [
            'company_settings', 'branches_stores', 'drawers_payments_tax_numbering_printers', 'users_roles_permissions', 'dashboard_reports', 'audit_logs', 'pos_sales',
            'products_categories_brands', 'suppliers', 'purchase_orders', 'purchase_invoices_supplier_returns', 'purchase_returns',
            'pricing_labels', 'inventory_stock_card', 'transfers', 'stock_counts',
            'product_wallet', 'party_wallet', 'returns_exchanges_gift_instruments',
        ];

        $granted = Permission::query()->whereHas('roles')->pluck('code')->all();

        foreach ($granted as $code) {
            if (! str_contains($code, '.')) {
                continue; // legacy gate alias
            }

            [$module] = explode('.', $code, 2);
            $this->assertContains($module, $implementedModules, "Permission [{$code}] grants access to an unimplemented module.");
        }
    }

    public function test_each_role_reaches_only_its_authorized_routes(): void
    {
        $matrix = [
            'system-administrator' => [
                // POS is not an Administrator capability in docs/04; only a
                // super-administrator bypass reaches it.
                'allowed' => ['/dashboard', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/audit', '/admin/system/health'],
                'denied' => ['/pos'],
            ],
            'branch-manager' => [
                'allowed' => ['/admin/branches', '/admin/stores', '/pos'],
                'denied' => ['/dashboard', '/admin/settings', '/admin/cash-drawers', '/admin/authorization-baseline', '/admin/audit'],
            ],
            'cashier' => [
                'allowed' => ['/pos'],
                'denied' => ['/dashboard', '/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/audit'],
            ],
            'accountant-reviewer' => [
                'allowed' => ['/dashboard', '/admin/audit', '/admin/system/health'],
                'denied' => ['/admin/settings', '/admin/branches', '/admin/stores', '/admin/cash-drawers', '/admin/authorization-baseline', '/pos'],
            ],
            'stock-counter' => [
                'allowed' => [],
                'denied' => ['/dashboard', '/pos', '/admin/branches', '/admin/audit'],
            ],
            'party-manager' => [
                'allowed' => [],
                'denied' => ['/dashboard', '/pos', '/admin/settings', '/admin/audit'],
            ],
        ];

        foreach ($matrix as $roleCode => $paths) {
            $user = $this->userWith('tsk008-'.$roleCode, [$roleCode]);
            $this->actingAs($user);

            foreach ($paths['allowed'] as $path) {
                $this->assertSame(200, $this->get($path)->getStatusCode(), "Role [{$roleCode}] must reach [{$path}].");
            }

            foreach ($paths['denied'] as $path) {
                $this->assertSame(403, $this->get($path)->getStatusCode(), "Role [{$roleCode}] must be denied [{$path}].");
            }
        }
    }

    public function test_a_cashier_cannot_reach_party_wallet_capabilities(): void
    {
        $cashier = $this->userWith('tsk008-wallet-cashier', ['cashier']);

        foreach (['party_wallet.view', 'party_wallet.export', 'party_wallet.override'] as $ability) {
            $this->assertFalse($cashier->hasPermission($ability));
        }

        $this->assertTrue($cashier->hasPermission('pos_sales.view'));
    }

    public function test_a_party_manager_cannot_reach_product_wallet_capabilities(): void
    {
        $partyManager = $this->userWith('tsk008-wallet-party', ['party-manager']);

        foreach (['product_wallet.view', 'product_wallet.export', 'product_wallet.override'] as $ability) {
            $this->assertFalse($partyManager->hasPermission($ability));
        }
    }

    public function test_a_stock_counter_cannot_approve_a_reconciliation(): void
    {
        $counter = $this->userWith('tsk008-counter', ['stock-counter']);

        $this->assertFalse($counter->hasPermission('stock_counts.approve'));
        $this->assertFalse($counter->hasPermission('inventory_stock_card.approve'));
    }

    public function test_branch_scope_isolates_visible_records(): void
    {
        $this->actingAs($this->administrator('tsk008-scope-setup'));
        $assigned = $this->branch('SC-BR-A');
        $foreign = $this->branch('SC-BR-B');

        $user = $this->userWith('tsk008-branch-scope', ['branch-manager'], false, [$assigned->id]);

        $this->assertTrue($user->canAccessBranch($assigned->id));
        $this->assertFalse($user->canAccessBranch($foreign->id));
    }

    public function test_store_scope_isolates_visible_records_and_inherits_from_branch_scope(): void
    {
        $this->actingAs($this->administrator('tsk008-store-setup'));
        $branch = $this->branch('SC-ST-BR');
        $assignedStore = $this->store($branch, 'SC-ST-1');
        $foreignBranch = $this->branch('SC-ST-BR-2');
        $foreignStore = $this->store($foreignBranch, 'SC-ST-2');

        $storeUser = $this->userWith('tsk008-store-scope', ['cashier'], false, [], [$assignedStore->id]);
        $this->assertTrue($storeUser->canAccessStore($assignedStore->id));
        $this->assertFalse($storeUser->canAccessStore($foreignStore->id));

        $branchUser = $this->userWith('tsk008-branch-inherit', ['branch-manager'], false, [$branch->id]);
        $this->assertTrue($branchUser->canAccessStore($assignedStore->id), 'A branch scope covers the stores inside that branch.');
        $this->assertFalse($branchUser->canAccessStore($foreignStore->id));

        $this->assertTrue(Store::visibleTo($branchUser)->whereKey($assignedStore)->exists());
        $this->assertFalse(Store::visibleTo($branchUser)->whereKey($foreignStore)->exists());
    }

    public function test_a_role_change_takes_effect_immediately_and_is_audited(): void
    {
        $administrator = $this->administrator('tsk008-role-change');
        $this->actingAs($administrator);

        $target = $this->userWith('tsk008-target');
        $cashier = Role::query()->where('code', 'cashier')->firstOrFail();

        $this->assertFalse($target->hasPermission('pos_sales.view'));

        app(SaveUserAuthorizationAction::class)->execute($target, [$cashier->id], [], []);

        $this->assertTrue($target->fresh()->hasPermission('pos_sales.view'));

        $event = AuditLog::query()->where('event', 'update_user_authorization')->sole();
        $this->assertSame('authorization', $event->category);
        $this->assertSame($administrator->id, $event->actor_id);
        $this->assertSame(User::class, $event->source_type);
        $this->assertSame((string) $target->id, $event->source_id);
        $this->assertSame([], $event->before_values['roles']);
        $this->assertSame([$cashier->id], $event->after_values['roles']);
        $this->assertNotEmpty($event->request_id);
    }

    public function test_a_scope_change_replaces_the_previous_assignment_and_is_audited(): void
    {
        $this->actingAs($this->administrator('tsk008-scope-change'));

        $first = $this->branch('SCH-BR-1');
        $second = $this->branch('SCH-BR-2');
        $store = $this->store($second, 'SCH-SELL');
        $manager = Role::query()->where('code', 'branch-manager')->firstOrFail();
        $target = $this->userWith('tsk008-scope-target');

        $action = app(SaveUserAuthorizationAction::class);
        $action->execute($target, [$manager->id], [$first->id], []);
        $this->assertTrue($target->fresh()->canAccessBranch($first->id));

        $action->execute($target, [$manager->id], [$second->id], [$store->id]);

        $refreshed = $target->fresh();
        $this->assertFalse($refreshed->canAccessBranch($first->id));
        $this->assertTrue($refreshed->canAccessBranch($second->id));
        $this->assertTrue($refreshed->canAccessStore($store->id));
        $this->assertSame(1, $refreshed->branchScopes()->count());

        $this->assertSame(2, AuditLog::query()->where('event', 'update_user_authorization')->count());
    }

    public function test_the_last_system_administrator_cannot_lose_the_role(): void
    {
        $administrator = $this->administrator('tsk008-last-admin');
        $administratorRole = Role::query()->where('code', 'system-administrator')->firstOrFail();
        $administrator->roles()->sync([$administratorRole->id]);
        $this->actingAs($administrator);

        $auditBefore = AuditLog::query()->count();

        try {
            app(SaveUserAuthorizationAction::class)->execute($administrator->fresh(), [], [], []);
            $this->fail('The final system administrator was demoted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roleIds', $exception->errors());
        }

        $this->assertTrue($administrator->fresh()->roles()->whereKey($administratorRole)->exists());
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_an_administrator_may_be_demoted_once_a_second_administrator_exists(): void
    {
        // Documents the current rule: only the *last* administrator is
        // protected. There is no separate self-lockout guard.
        $first = $this->administrator('tsk008-admin-one');
        $second = $this->administrator('tsk008-admin-two');
        $administratorRole = Role::query()->where('code', 'system-administrator')->firstOrFail();
        $first->roles()->sync([$administratorRole->id]);
        $second->roles()->sync([$administratorRole->id]);

        $this->actingAs($first);
        app(SaveUserAuthorizationAction::class)->execute($first->fresh(), [], [], []);

        $this->assertFalse($first->fresh()->roles()->whereKey($administratorRole)->exists());
        $this->assertTrue($second->fresh()->roles()->whereKey($administratorRole)->exists());
    }

    public function test_the_authorization_screen_assigns_scopes_to_the_selected_user_only(): void
    {
        // Regression for the recorded modal-state defect: the save must target
        // the user opened for editing, never the modal's open/close flag.
        $administrator = $this->administrator('tsk008-modal');
        $this->actingAs($administrator);

        $branch = $this->branch('MODAL-BR');
        $store = $this->store($branch, 'MODAL-SELL');
        $reviewer = $this->userWith('tsk008-reviewer', ['accountant-reviewer']);
        $reviewerRole = Role::query()->where('code', 'accountant-reviewer')->firstOrFail();

        Livewire::test('platform::admin.authorization-baseline')
            ->call('editAuthorization', $reviewer->id)
            ->assertSet('editingUserId', $reviewer->id)
            ->assertSet('authorizationModalOpen', true)
            ->set('roleIds', [$reviewerRole->id])
            ->set('branchIds', [$branch->id])
            ->set('storeIds', [$store->id])
            ->call('saveAuthorization')
            ->assertHasNoErrors()
            ->assertSet('authorizationModalOpen', false);

        $refreshed = $reviewer->fresh();
        $this->assertTrue($refreshed->canAccessBranch($branch->id));
        $this->assertTrue($refreshed->canAccessStore($store->id));

        $this->assertSame(0, $administrator->fresh()->branchScopes()->count(), 'The administrator record must not be modified.');
    }

    public function test_the_authorization_screen_and_actions_are_denied_without_the_permission(): void
    {
        $this->actingAs($this->administrator('tsk008-deny-setup'));
        $target = $this->userWith('tsk008-deny-target');
        $auditBefore = AuditLog::query()->count();

        $this->actingAs($this->userWith('tsk008-deny', ['accountant-reviewer']));

        $this->get('/admin/authorization-baseline')->assertForbidden();

        try {
            app(SaveUserAuthorizationAction::class)->execute($target, [], [], []);
            $this->fail('An unauthorized authorization change was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_a_super_administrator_flag_bypasses_permission_checks(): void
    {
        $superAdmin = $this->userWith('tsk008-super', [], true);

        $this->actingAs($superAdmin);

        foreach (['/dashboard', '/admin/settings', '/admin/audit', '/pos'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
