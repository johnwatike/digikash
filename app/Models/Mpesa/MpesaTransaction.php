<?php

namespace App\Models\Mpesa;

use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'mpesa_shortcode_id',
        'payment_intent_id',
        'trans_id',
        'bill_ref_number',
        'msisdn',
        'amount',
        'transaction_type',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'amount'      => 'float',
        'raw_payload' => 'array',
    ];

    public function shortcode(): BelongsTo
    {
        return $this->belongsTo(MpesaShortcode::class, 'mpesa_shortcode_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
