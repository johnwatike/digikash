<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudRule extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'rule_type',
        'conditions',
        'action',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active'  => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
