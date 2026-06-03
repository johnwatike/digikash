<?php

namespace App\Services\Payment\Mpesa;

class MpesaFeeCalculator
{
    public function tillMerchantFee(float $amount): float
    {
        if ($amount <= 200) {
            return 0;
        }

        return min($amount * 0.0055, 200);
    }

    public function paybillCustomerFee(float $amount): float
    {
        if ($amount <= 100) {
            return 0;
        }

        if ($amount <= 1500) {
            return 5;
        }

        if ($amount <= 5000) {
            return 23;
        }

        if ($amount <= 20000) {
            return 34;
        }

        return 55;
    }
}
