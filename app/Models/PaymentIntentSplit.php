<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntentSplit extends Model
{
    protected $fillable = [
        'payment_intent_id',
        'recipient_merchant_id',
        'recipient_label',
        'amount',
        'percent',
        'status',
    ];

    protected $casts = [
        'amount'  => 'float',
        'percent' => 'float',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function recipientMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'recipient_merchant_id');
    }
}
