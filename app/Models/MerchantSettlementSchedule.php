<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSettlementSchedule extends Model
{
    protected $fillable = [
        'merchant_id',
        'frequency',
        'settlement_delay_days',
        'minimum_payout',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'minimum_payout' => 'float',
        'is_active'      => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
