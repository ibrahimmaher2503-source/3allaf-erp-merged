<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** CF-05 regression coverage for branch warehouse meaning and selectors. */
final class BranchWarehouseRelationshipTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('cf005-warehouse-admin'));
    }

    public function test_branch_directory_counts_only_active_warehouses(): void
    {
        $branch = $this->branch('CF05-COUNT');
        $otherBranch = $this->branch('CF05-COUNT-OTHER');
        $foreignCompany = Company::factory()->create(['status' => 'active']);

        $this->store($branch, 'CF05-SELL', 'selling');
        $this->store($branch, 'CF05-WH-ACTIVE', 'warehouse');
        $this->store($otherBranch, 'CF05-WH-OTHER-BRANCH', 'warehouse');
        Store::query()->create([
            'company_id' => $foreignCompany->id,
            'branch_id' => $branch->id,
            'code' => 'CF05-WH-FOREIGN-COMPANY',
            'type' => 'warehouse',
            'name_ar' => 'مخزن شركة أجنبية',
            'name_en' => 'Foreign Company Warehouse',
            'status' => 'active',
            'policy_notes' => 'Automated test fixture only.',
        ]);
        $this->store($branch, 'CF05-SERVICE', 'party');
        $inactiveWarehouse = $this->store($branch, 'CF05-WH-INACTIVE', 'warehouse');
        $inactiveWarehouse->update(['status' => 'inactive']);

        $rendered = Livewire::test('platform::admin.branches')->html();

        self::assertSame('1', $this->elementText($rendered, 'branch-warehouse-count-'.$branch->id));
        self::assertSame('Warehouse', $this->elementText($rendered, 'branch-warehouse-label-'.$branch->id));
    }

    public function test_store_create_and_edit_selectors_show_active_authorized_current_company_branches(): void
    {
        $activeBranch = $this->branch('CF05-ACTIVE-A');
        $secondActiveBranch = $this->branch('CF05-ACTIVE-B');
        $inactiveBranch = $this->branch('CF05-INACTIVE', 'inactive');
        $unauthorizedBranch = $this->branch('CF05-UNAUTHORIZED');
        $foreignCompany = Company::factory()->create(['status' => 'active']);
        $foreignBranch = Branch::query()->create([
            'company_id' => $foreignCompany->id,
            'code' => 'CF05-FOREIGN',
            'name_ar' => 'فرع CF05-FOREIGN',
            'name_en' => 'Branch CF05-FOREIGN',
            'timezone' => 'UTC',
            'status' => 'active',
            'policy_notes' => 'Automated test fixture only.',
        ]);

        $viewer = $this->userWith(
            'cf005-branch-viewer',
            ['system-administrator'],
            branchIds: [$activeBranch->id, $secondActiveBranch->id, $inactiveBranch->id, $foreignBranch->id],
        );
        $this->actingAs($viewer);

        $create = Livewire::test('platform::admin.stores')->call('openCreateStoreModal');
        $createOptions = $this->selectOptionTexts($create->html(), 'storeForm.branch_id');
        self::assertContains($this->branchOptionLabel($activeBranch), $createOptions);
        self::assertContains($this->branchOptionLabel($secondActiveBranch), $createOptions);
        self::assertNotContains($this->branchOptionLabel($inactiveBranch), $createOptions);
        self::assertNotContains($this->branchOptionLabel($unauthorizedBranch), $createOptions);
        self::assertNotContains($this->branchOptionLabel($foreignBranch), $createOptions);

        $editedStore = $this->store($secondActiveBranch, 'CF05-EDIT-STORE');
        $edit = Livewire::test('platform::admin.stores')->call('openEditStoreModal', $editedStore->id);
        $edit->assertSet('storeForm.branch_id', (string) $secondActiveBranch->id);
        self::assertContains($this->branchOptionLabel($secondActiveBranch), $this->selectOptionTexts($edit->html(), 'storeForm.branch_id'));
    }

    public function test_store_types_keep_warehouse_and_point_of_sale_terms_distinct(): void
    {
        $branch = $this->branch('CF05-TERMS');
        $warehouse = $this->store($branch, 'CF05-TERM-WH', 'warehouse');
        $sellingStore = $this->store($branch, 'CF05-TERM-POS', 'selling');

        $rendered = Livewire::test('platform::admin.stores')->html();

        self::assertSame('Warehouse', $this->elementText($rendered, 'store-type-'.$warehouse->id));
        self::assertSame('Point of Sale (POS)', $this->elementText($rendered, 'store-type-'.$sellingStore->id));
    }

    private function branchOptionLabel(Branch $branch): string
    {
        $name = app()->getLocale() === 'ar' ? $branch->name_ar : $branch->name_en;

        return $branch->code.' - '.$name;
    }

    /** @return array<int, string> */
    private function selectOptionTexts(string $html, string $wireModel): array
    {
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $options = $xpath->query(sprintf('//*[@*[name()="wire:model"]="%s"]//option', $wireModel));

        self::assertNotFalse($options);

        return array_map(
            static fn ($option): string => trim(preg_replace('/\s+/', ' ', $option->textContent) ?? ''),
            iterator_to_array($options),
        );
    }

    private function elementText(string $html, string $testId): string
    {
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[@data-testid="%s"]', $testId));

        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, 'Expected one stable CF-05 element for '.$testId.'.');

        return trim(preg_replace('/\s+/', ' ', $nodes->item(0)->textContent) ?? '');
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        return $document;
    }
}
