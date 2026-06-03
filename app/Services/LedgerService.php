<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function postPaymentSuccess(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent) {
            $merchantAccount = $this->ensureMerchantAccount($intent->merchant_id, $intent->currency);
            $platformAccount = $this->ensurePlatformAccount($intent->currency);

            $this->postEntry($merchantAccount->id, 'credit', $intent->net_amount, $intent->currency, 'payment_intent', $intent->pi_id, 'Merchant payment net');
            $this->postEntry($platformAccount->id, 'credit', $intent->fee, $intent->currency, 'payment_intent', $intent->pi_id, 'Platform fee');

            $reserve = $intent->merchant->reserves()
                ->where('is_active', true)
                ->where('currency', $intent->currency)
                ->first();

            if ($reserve && $reserve->percent > 0) {
                $holdAmount = min(
                    $intent->net_amount * ($reserve->percent / 100),
                    $reserve->cap_amount ?? PHP_FLOAT_MAX
                );
                if ($holdAmount > 0) {
                    $reserveAccount = $this->ensureReserveAccount($intent->merchant_id, $intent->currency);
                    $this->postEntry($reserveAccount->id, 'credit', $holdAmount, $intent->currency, 'reserve', $intent->pi_id, 'Rolling reserve hold');
                    $this->postEntry($merchantAccount->id, 'debit', $holdAmount, $intent->currency, 'reserve', $intent->pi_id, 'Reserve deduction');
                }
            }
        });
    }

    public function ensureMerchantAccount(int $merchantId, string $currency): LedgerAccount
    {
        $code = "merchant:{$merchantId}:{$currency}";

        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code],
            [
                'name'        => "Merchant {$merchantId} {$currency}",
                'type'        => 'merchant_wallet',
                'merchant_id' => $merchantId,
                'currency'    => $currency,
            ]
        );
    }

    public function ensurePlatformAccount(string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => "platform:fees:{$currency}"],
            [
                'name'     => "Platform fees {$currency}",
                'type'     => 'platform_revenue',
                'currency' => $currency,
            ]
        );
    }

    public function ensureReserveAccount(int $merchantId, string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => "merchant:{$merchantId}:reserve:{$currency}"],
            [
                'name'        => "Merchant reserve {$merchantId}",
                'type'        => 'reserve',
                'merchant_id' => $merchantId,
                'currency'    => $currency,
            ]
        );
    }

    protected function postEntry(
        int $accountId,
        string $entryType,
        float $amount,
        string $currency,
        string $referenceType,
        string $referenceId,
        string $description,
    ): LedgerEntry {
        return LedgerEntry::query()->create([
            'ledger_account_id' => $accountId,
            'entry_type'        => $entryType,
            'amount'            => $amount,
            'currency'          => $currency,
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'description'       => $description,
        ]);
    }
}
