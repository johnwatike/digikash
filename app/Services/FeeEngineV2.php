<?php

namespace App\Services;

use App\Models\Merchant;
use App\Services\Payment\Mpesa\MpesaFeeCalculator;

class FeeEngineV2
{
    public function __construct(protected MpesaFeeCalculator $mpesaFeeCalculator) {}

    /**
     * @return array{merchant_fee: float, platform_fee: float, mpesa_fee: float, net: float}
     */
    public function calculate(
        Merchant $merchant,
        float $amount,
        string $currency,
        string $paymentMethod = 'card',
    ): array {
        $merchantPercent = (float) $merchant->fee;
        $platformFee     = $amount * $merchantPercent / 100;
        $mpesaFee        = 0.0;

        if ($paymentMethod === 'mpesa_till') {
            $mpesaFee = $this->mpesaFeeCalculator->tillMerchantFee($amount);
        } elseif ($paymentMethod === 'mpesa_paybill') {
            $mpesaFee = $this->mpesaFeeCalculator->paybillCustomerFee($amount);
        }

        $fxMarkup = $currency !== 'USD' ? $amount * 0.01 : 0;

        $totalFees = $platformFee + $mpesaFee + $fxMarkup;
        $net       = max(0, $amount - $totalFees);

        return [
            'merchant_fee' => $platformFee,
            'platform_fee' => $platformFee,
            'mpesa_fee'    => $mpesaFee,
            'fx_markup'    => $fxMarkup,
            'net'          => $net,
        ];
    }
}
