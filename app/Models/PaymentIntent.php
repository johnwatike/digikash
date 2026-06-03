<?php

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentIntent extends Model
{
    protected $fillable = [
        'pi_id',
        'merchant_id',
        'trx_id',
        'status',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'client_secret',
        'idempotency_key',
        'ref_trx',
        'environment',
        'metadata',
        'payment_method_data',
        'next_action_type',
        'next_action_data',
        'expires_at',
    ];

    protected $casts = [
        'status'              => PaymentIntentStatus::class,
        'amount'              => 'float',
        'fee'                 => 'float',
        'net_amount'          => 'float',
        'metadata'            => 'array',
        'payment_method_data' => 'array',
        'next_action_data'    => 'array',
        'expires_at'          => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PaymentIntent $intent): void {
            if (! $intent->pi_id) {
                $intent->pi_id = 'pi_'.Str::lower(Str::random(24));
            }
            if (! $intent->client_secret) {
                $intent->client_secret = 'pi_secret_'.Str::lower(Str::random(32));
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentIntentEvent::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(PaymentIntentSplit::class);
    }

    public function mpesaTransactions(): HasMany
    {
        return $this->hasMany(Mpesa\MpesaTransaction::class);
    }

    public function transitionTo(PaymentIntentStatus $status, ?string $reason = null, ?array $payload = null): void
    {
        $from = $this->status;

        $this->status = $status;
        $this->save();

        $this->events()->create([
            'from_status' => $from?->value,
            'to_status'   => $status->value,
            'reason'      => $reason,
            'payload'     => $payload,
        ]);
    }
}
