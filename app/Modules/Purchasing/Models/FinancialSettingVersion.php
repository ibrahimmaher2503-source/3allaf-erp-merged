<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialSettingVersion extends Model
{
    protected $fillable = [
        'key',
        'value',
        'value_type',
        'effective_from',
        'effective_to',
        'created_by',
        'approval_record_id',
        'version',
        'locked_at',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'immutable_datetime',
        'effective_to' => 'immutable_datetime',
        'locked_at' => 'immutable_datetime',
        'version' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
