<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DocumentSequence extends Model
{
    private bool $advancingCounter = false;

    protected $fillable = [
        'document_type',
        'prefix',
        'suffix',
        'padding_length',
        'next_value',
        'reset_rule',
        'status',
        'lock_version',
        'policy_notes',
    ];

    protected $casts = [
        'padding_length' => 'integer',
        'next_value' => 'integer',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $sequence): void {
            if (($sequence->isDirty('next_value') || $sequence->isDirty('lock_version')) && ! $sequence->advancingCounter) {
                throw new LogicException('Document-sequence counters may only change through allocation or audited override actions.');
            }
        });
    }

    public function advanceCounter(int $nextValue): void
    {
        $this->advancingCounter = true;
        try {
            $this->forceFill([
                'next_value' => $nextValue,
                'lock_version' => $this->lock_version + 1,
            ])->save();
        } finally {
            $this->advancingCounter = false;
        }
    }
}
