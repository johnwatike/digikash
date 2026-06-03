<?php

namespace App\Models\Mpesa;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpesaShortcode extends Model
{
    protected $fillable = [
        'merchant_id',
        'type',
        'shortcode',
        'label',
        'nominated_phone',
        'credentials',
        'callbacks_registered',
        'environment',
        'is_active',
    ];

    protected $casts = [
        'callbacks_registered' => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MpesaTransaction::class);
    }

    public function isPaybill(): bool
    {
        return $this->type === 'paybill';
    }

    public function isTill(): bool
    {
        return $this->type === 'till';
    }
}
