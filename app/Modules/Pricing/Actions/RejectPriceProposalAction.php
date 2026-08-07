<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectPriceProposalAction
{
    public function execute(PriceVersion $version, string $reason): PriceVersion
    {
        /** @var User $approver */
        $approver = Auth::user() ?? throw new \LogicException('An authenticated pricing approver is required.');
        Gate::forUser($approver)->authorize('pricing_labels.approve');
        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => __('A rejection reason is required.')]);
        }

        return DB::transaction(function () use ($version, $approver, $reason): PriceVersion {
            $version = PriceVersion::query()->lockForUpdate()->with(['lines', 'approvalRecord'])->findOrFail($version->id);
            if ($version->state !== PriceVersionState::Submitted || $version->approvalRecord === null) {
                throw ValidationException::withMessages(['state' => __('Only a submitted proposal can be rejected.')]);
            }
            /** @var ApprovalRecord $approvalRecord */
            $approvalRecord = $version->approvalRecord;
            app(ApprovalRecordTransition::class)->execute(
                record: $approvalRecord,
                state: ApprovalState::Rejected,
                event: 'price_approval_rejected',
                attributes: ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => $reason],
                expectedSourceVersion: (string) $version->lock_version,
                expectedSourceHash: $version->source_hash,
                authorize: fn ($record): mixed => Gate::forUser($approver)->authorize('pricing_labels.approve'),
            );
            $version->update(['state' => PriceVersionState::Rejected, 'lock_version' => $version->lock_version + 1]);
            $line = $version->lines->first();
            app(RecordAuditEvent::class)->execute(category: 'pricing', event: 'price_version_rejected', source: $version, after: ['state' => PriceVersionState::Rejected->value], branchId: $line?->branch_id, storeId: $line?->store_id, reasonText: $reason);

            return $version->fresh(['lines.product', 'priceList', 'approvalRecord']);
        });
    }
}
