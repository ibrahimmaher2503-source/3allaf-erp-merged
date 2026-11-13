<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\ScaleConfiguration;
use App\Modules\Platform\Services\TmScaleConnection;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Retail\Actions\PosCartAction;
use App\Modules\Retail\Support\PosContextResolver;
use App\Support\UserSafeError;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * Brings weighed items confirmed on the network scale into the open POS cart.
 *
 * The scale only exposes a sale log, so ERP reads it on demand. Each log record is used at most once (unique
 * sequence per scale) and the price always comes from ERP's effective price, never from the scale. Nothing is
 * sold here: the cashier reviews the cart and completes the sale as usual.
 */
final class ScaleReceiver extends Component
{
    public bool $auto = false;

    public string $messageType = '';

    public string $message = '';

    /** @var list<array{type: string, text: string}> one line per weighed item handled by the last press */
    public array $notes = [];

    public function mount(): void
    {
        Gate::authorize('pos_sales.view');
    }

    public function receive(PosCartAction $cart, TmScaleConnection $scale): void
    {
        Gate::authorize('pos_sales.create');
        /** @var User $user */
        $user = auth()->user();
        $context = app(PosContextResolver::class)->resolve($user);
        $device = $context->isReady() && $context->store !== null ? $this->deviceFor((int) $context->store->id) : null;
        if ($device === null) {
            $this->say('error', __('No active scale is linked to this selling outlet.'));

            return;
        }

        try {
            $records = $scale->readSalesReport($device->ip_address, $device->port);
        } catch (RuntimeException) {
            $this->say('error', __('The scale could not be read. Check that it is on and connected.'));

            return;
        }

        $known = DB::table('scale_received_records')->where('scale_configuration_id', $device->id);
        $lastSequence = (clone $known)->max('sequence');
        if ($lastSequence === null) {
            $this->setAsideHistory($device, $records);
            $this->say('info', __('Scale connected. Earlier scale records were set aside; confirm a weight on the scale to receive it.'));

            return;
        }

        $maxOnDevice = $records === [] ? 0 : max(array_column($records, 'sequence'));
        if ($maxOnDevice < (int) $lastSequence) {
            $this->say('error', __('The scale log was restarted. Ask an administrator to review the scale before receiving weights.'));

            return;
        }

        $fresh = array_values(array_filter($records, static fn (array $record): bool => $record['sequence'] > (int) $lastSequence));
        usort($fresh, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        $fresh = array_slice($fresh, 0, 20);
        if ($fresh === []) {
            $this->say('info', __('No new weighed item on the scale.'));

            return;
        }

        $this->notes = [];
        $added = 0;
        $storeId = (int) $context->store->id;
        foreach ($fresh as $record) {
            $outcome = $this->handle($device, $record, $cart, $user, $storeId);
            if ($outcome === null) {
                continue;
            }
            $this->notes[] = $outcome;
            if ($outcome['type'] === 'success') {
                $added++;
            }
        }

        if ($added > 0) {
            $this->dispatch('pos-cart-updated');
            $this->dispatch('notify', message: __('Weight received from the scale.'));
        }
        $this->say($added > 0 ? 'success' : 'error', $added > 0
            ? __('Items added to the cart: :count.', ['count' => $added])
            : __('Nothing was added to the cart.'));
    }

    public function render(PosContextResolver $contexts): View
    {
        /** @var User $user */
        $user = auth()->user();
        $context = $contexts->resolve($user);
        $device = $context->store !== null ? $this->deviceFor((int) $context->store->id) : null;

        return view('livewire.pos.scale-receiver', ['available' => $device !== null && Gate::allows('pos_sales.create')]);
    }

    private function deviceFor(int $storeId): ?ScaleConfiguration
    {
        return ScaleConfiguration::query()->where('store_id', $storeId)->where('status', 'active')->first();
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function setAsideHistory(ScaleConfiguration $device, array $records): void
    {
        foreach ($records as $record) {
            $this->insertRecord($device, $record, 'baseline', null, null);
        }
        if ($records === []) {
            // Nothing on the device yet: remember that reception was started so the first record is not mistaken for history.
            $this->insertRecord($device, ['sequence' => 0, 'recorded_at' => now()->format('Y-m-d H:i:s'), 'plu' => 0, 'weight' => '0', 'unit_price' => '0', 'total' => '0'], 'baseline', null, null);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{type: string, text: string}|null null when another request already used this record
     */
    private function handle(ScaleConfiguration $device, array $record, PosCartAction $cart, User $user, int $storeId): ?array
    {
        $mapping = DB::table('scale_product_mappings')->where('scale_configuration_id', $device->id)->where('plu', $record['plu'])->first();
        $product = $mapping ? DB::table('products')->join('product_units', 'product_units.product_id', '=', 'products.id')
            ->where('product_units.id', $mapping->product_unit_id)->first(['products.id', 'products.name_ar', 'products.name_en']) : null;
        $name = $product ? (str_starts_with(app()->getLocale(), 'ar') ? $product->name_ar : ($product->name_en ?: $product->name_ar)) : (string) $record['name'];
        $weight = (string) $record['weight'];

        $reason = $this->refusalReason($record, $mapping, $product, $storeId);
        if ($reason !== null) {
            return $this->insertRecord($device, $record, 'rejected', $reason, $user->id) ? $this->refusal($name, $weight, $reason) : null;
        }

        $erpPrice = app(EffectivePriceResolver::class)->resolve((int) $product->id, $storeId, productUnitId: (int) $mapping->product_unit_id)?->amount;
        try {
            DB::transaction(function () use ($device, $record, $cart, $user, $product, $mapping, $weight): void {
                if (! $this->insertRecord($device, $record, 'received', null, $user->id)) {
                    throw new RuntimeException('duplicate');
                }
                $cart->add(request(), $user, (int) $product->id, $weight, (int) $mapping->product_unit_id);
                app(RecordAuditEvent::class)->execute('master_data', 'receive_scale_weight', $device, null, [
                    'sequence' => $record['sequence'], 'plu' => $record['plu'], 'weight' => $record['weight'], 'scale_unit_price' => $record['unit_price'], 'scale_total' => $record['total'],
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'duplicate') {
                return null;
            }
            $text = UserSafeError::message($exception);

            return $this->insertRecord($device, $record, 'rejected', $text, $user->id) ? $this->refusal($name, $weight, $text) : null;
        }

        $text = __('Added to the cart: :name, :weight kg at the ERP price :price per kg.', ['name' => $name, 'weight' => $weight, 'price' => $erpPrice !== null ? bcadd((string) $erpPrice, '0', 2) : '-']);
        if ($erpPrice !== null && bccomp(bcadd((string) $erpPrice, '0', 2), bcadd((string) $record['unit_price'], '0', 2), 2) !== 0) {
            $text .= ' '.__('The scale showed :scale per kg; the ERP price was used.', ['scale' => bcadd((string) $record['unit_price'], '0', 2)]);
        }

        return ['type' => 'success', 'text' => $text];
    }

    /**
     * @return array{type: string, text: string}
     */
    private function refusal(string $name, string $weight, string $reason): array
    {
        return ['type' => 'error', 'text' => __('Not added: :name, :weight kg.', ['name' => $name, 'weight' => $weight]).' '.$reason];
    }

    /**
     * Returns a plain-language reason (including what to do) when the weighed item must not enter the cart.
     *
     * @param  array<string, mixed>  $record
     */
    private function refusalReason(array $record, ?object $mapping, ?object $product, int $storeId): ?string
    {
        if (! $record['consistent']) {
            return __('The total printed by the scale does not equal price times weight, so the record was ignored. Check the scale.');
        }
        if (bccomp((string) $record['weight'], '0', 3) <= 0) {
            return __('The weight must be greater than zero.');
        }
        if ($mapping === null || $product === null) {
            return __('Scale product :name (PLU :plu) is not linked to an ERP product. Link it in Settings, Printers and connected devices, Scale products.', ['name' => $record['name'], 'plu' => $record['plu']]);
        }
        $balance = DB::table('stock_balances')->where('store_id', $storeId)->where('product_id', $product->id)->first();
        $available = $balance ? bcsub((string) $balance->on_hand, (string) $balance->reserved, 3) : '0.000';
        if (bccomp($available, (string) $record['weight'], 3) < 0) {
            return __('The stock of this product in this selling outlet is :available kg, less than the weighed :weight kg. Receive stock first, then weigh again.', ['available' => $available, 'weight' => $record['weight']]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function insertRecord(ScaleConfiguration $device, array $record, string $status, ?string $reason, ?int $userId): bool
    {
        try {
            DB::table('scale_received_records')->insert([
                'scale_configuration_id' => $device->id, 'sequence' => $record['sequence'], 'recorded_at' => $record['recorded_at'],
                'plu' => $record['plu'], 'weight' => $record['weight'], 'unit_price' => $record['unit_price'], 'total' => $record['total'],
                'status' => $status, 'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null, 'received_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'scale_received_sequence_unique')) {
                return false;
            }
            throw $exception;
        }

        return true;
    }

    private function say(string $type, string $text): void
    {
        $this->messageType = $type;
        $this->message = $text;
    }
}
