<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'merchant_id',
        'type',
        'resource_type',
        'resource_id',
        'sequence',
        'payload',
        'environment',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WebhookEvent $event): void {
            if (! $event->event_id) {
                $event->event_id = 'evt_'.Str::lower(Str::random(24));
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
