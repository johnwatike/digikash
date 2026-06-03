<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_event_id',
        'webhook_endpoint_id',
        'attempt',
        'status',
        'http_status',
        'response_body',
        'error_message',
        'next_retry_at',
        'delivered_at',
    ];

    protected $casts = [
        'next_retry_at' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }

    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class);
    }
}
