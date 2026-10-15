<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Quotation\Models\QuotationShareLink;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class ShareQuotationAction
{
    public function execute(User $user, Quotation $quotation): string
    {
        Gate::forUser($user)->authorize('quotations.share');
        abort_unless($user->is_super_admin || $user->canAccessBranch($quotation->branch_id) || $user->canAccessStore($quotation->store_id), 403);
        $token = Str::random(64);
        $expiresAt = now()->addDays(30);
        if ($quotation->valid_until !== null) {
            $expiresAt = $expiresAt->min($quotation->valid_until->copy()->endOfDay());
        }
        $link = QuotationShareLink::query()->create([
            'quotation_id' => $quotation->id,
            'created_by' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
        $url = URL::temporarySignedRoute('quotations.print', $expiresAt, ['quotation' => $quotation->id, 'share' => $link->id, 'token' => $token]);
        app(RecordAuditEvent::class)->execute('quotations', 'quotation_shared', $quotation, branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['share_link_id' => $link->id, 'expires_at' => $expiresAt->toIso8601String(), 'non_posting' => true]);

        return $url;
    }
}
