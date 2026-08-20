<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Actions\SaveSupplierCommunicationDestinationAction;
use App\Modules\Catalog\Actions\SaveSupplierContactAction;
use App\Modules\Purchasing\Actions\ResolveSupplierOrderRecipientAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Support\PlatformFixtures;

/**
 * Master request §49 — a purchase order recipient must be an explicit supplier destination.
 *
 * Run through the isolated MariaDB profile on port 3307 only.
 */
final class SupplierOrderRecipientResolutionTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_it_returns_only_the_active_primary_purchase_order_destination(): void
    {
        $supplier = $this->supplier('RECIPIENT-EXACT');

        $supplier->communicationDestinations()->createMany([
            ['purpose' => 'general', 'channel' => 'email', 'destination' => 'general@example.test', 'is_primary' => true, 'status' => 'active'],
            ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => 'inactive@example.test', 'is_primary' => true, 'status' => 'inactive'],
            ['purpose' => 'purchase_order', 'channel' => 'whatsapp', 'destination' => '01000000000', 'is_primary' => false, 'status' => 'active'],
            ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => 'orders@example.test', 'is_primary' => true, 'status' => 'active'],
        ]);

        $resolved = app(ResolveSupplierOrderRecipientAction::class)->execute($supplier);

        $this->assertSame('purchase_order', $resolved->purpose);
        $this->assertSame('email', $resolved->channel);
        $this->assertSame('orders@example.test', $resolved->destination);
    }

    public function test_it_fails_when_no_active_primary_purchase_order_destination_exists(): void
    {
        $supplier = $this->supplier('RECIPIENT-MISSING', 'owner@example.test');

        $supplier->communicationDestinations()->createMany([
            ['purpose' => 'general', 'channel' => 'email', 'destination' => 'general@example.test', 'is_primary' => true, 'status' => 'active'],
            ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => 'inactive@example.test', 'is_primary' => true, 'status' => 'inactive'],
        ]);

        try {
            app(ResolveSupplierOrderRecipientAction::class)->execute($supplier);
            self::fail('A missing designated purchase-order recipient must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recipient', $exception->errors());
            $this->assertSame('A designated purchase-order recipient is required for this supplier.', $exception->errors()['recipient'][0]);
        }
    }

    public function test_contact_and_destination_updates_cannot_cross_supplier_boundary(): void
    {
        $this->actingAs($this->administrator('supplier-idor-admin'));
        $owner = $this->supplier('RECIPIENT-OWNER');
        $other = $this->supplier('RECIPIENT-OTHER');
        $contact = $owner->contacts()->create(['role' => 'general', 'name' => 'Owner contact', 'status' => 'active', 'lock_version' => 1]);
        $destination = $owner->communicationDestinations()->create(['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => 'owner@example.test', 'is_primary' => true, 'status' => 'active', 'lock_version' => 1]);

        try {
            app(SaveSupplierContactAction::class)->execute($other->id, ['role' => 'general', 'name' => 'Hijack'], $contact->id);
            self::fail('A contact from another supplier was updated.');
        } catch (ModelNotFoundException) {
            self::assertSame('Owner contact', $contact->fresh()->name);
        }
        try {
            app(SaveSupplierCommunicationDestinationAction::class)->execute($other->id, ['purpose' => 'purchase_order', 'channel' => 'email', 'destination' => 'hijack@example.test'], $destination->id);
            self::fail('A destination from another supplier was updated.');
        } catch (ModelNotFoundException) {
            self::assertSame('owner@example.test', $destination->fresh()->destination);
        }
    }

    private function supplier(string $code, ?string $email = null): Supplier
    {
        return Supplier::query()->create([
            'code' => $code,
            'name_ar' => 'مورد اختبار',
            'name_en' => 'Test supplier',
            'email' => $email,
            'status' => 'active',
            'lock_version' => 0,
        ]);
    }
}
