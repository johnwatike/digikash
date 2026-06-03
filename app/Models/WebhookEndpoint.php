<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'merchant_id',
        'url',
        'secret',
        'events',
        'api_version',
        'status',
        'is_legacy_ipn',
    ];

    protected $casts = [
        'events'        => 'array',
        'is_legacy_ipn' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WebhookEndpoint $endpoint): void {
            if (! $endpoint->secret) {
                $endpoint->secret = 'whsec_'.Str::lower(Str::random(32));
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

    public function acceptsEvent(string $eventType): bool
    {
        $events = $this->events ?? [];

        if ($events === [] || in_array('*', $events, true)) {
            return true;
        }

        return in_array($eventType, $events, true);
    }
}
