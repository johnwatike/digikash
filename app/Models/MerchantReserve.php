<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantReserve extends Model
{
    protected $fillable = [
        'merchant_id',
        'percent',
        'hold_days',
        'cap_amount',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'percent'    => 'float',
        'cap_amount' => 'float',
        'is_active'  => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
