<?php

namespace App\Services;

use App\Models\FraudRule;
use App\Models\Merchant;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Cache;

class FraudRuleEngine
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluatePaymentIntent(
        Merchant $merchant,
        float $amount,
        string $currency,
        array $context = [],
    ): void {
        $rules = FraudRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($merchant) {
                $q->whereNull('merchant_id')->orWhere('merchant_id', $merchant->id);
            })
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->matches($rule, $amount, $currency, $context)) {
                if ($rule->action === 'block') {
                    throw new \RuntimeException('Payment blocked by fraud rule: '.$rule->name);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matches(FraudRule $rule, float $amount, string $currency, array $context): bool
    {
        $conditions = $rule->conditions ?? [];

        return match ($rule->rule_type) {
            'max_amount' => $amount > (float) ($conditions['max'] ?? PHP_FLOAT_MAX),
            'min_amount' => $amount < (float) ($conditions['min'] ?? 0),
            'velocity_per_hour' => $this->velocityExceeded(
                (int) ($conditions['merchant_id'] ?? $context['merchant_id'] ?? 0),
                (int) ($conditions['max_count'] ?? 10),
                3600,
            ),
            'blocked_country' => isset($context['country']) && in_array(
                $context['country'],
                (array) ($conditions['countries'] ?? []),
                true
            ),
            default => false,
        };
    }

    protected function velocityExceeded(int $merchantId, int $maxCount, int $windowSeconds): bool
    {
        if ($merchantId <= 0) {
            return false;
        }

        $key   = "fraud:velocity:{$merchantId}";
        $count = (int) Cache::get($key, 0);

        if ($count >= $maxCount) {
            return true;
        }

        Cache::put($key, $count + 1, $windowSeconds);

        return false;
    }
}
