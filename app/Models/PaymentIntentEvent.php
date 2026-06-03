<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentIntentEvent extends Model
{
    protected $fillable = [
        'payment_intent_id',
        'from_status',
        'to_status',
        'reason',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
