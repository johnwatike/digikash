<?php

namespace App\Services\Payment\Mpesa;

use App\Models\Mpesa\MpesaTransaction;
use App\Models\Transaction;
use App\Services\PaymentIntentService;
use Illuminate\Support\Facades\DB;

class MpesaPayoutService
{
    public function __construct(
        protected DarajaClient $darajaClient,
        protected PaymentIntentService $paymentIntentService,
    ) {}

    public function reverseForTransaction(Transaction $transaction, string $msisdn): array
    {
        $mpesaTxn = MpesaTransaction::query()
            ->where('payment_intent_id', function ($q) use ($transaction) {
                $q->select('id')
                    ->from('payment_intents')
                    ->where('trx_id', $transaction->trx_id)
                    ->limit(1);
            })
            ->whereNotNull('trans_id')
            ->first();

        if (! $mpesaTxn?->trans_id) {
            throw new \RuntimeException('No M-PESA transaction ID found for reversal.');
        }

        return $this->darajaClient->reverseTransaction(
            $mpesaTxn->trans_id,
            (float) $mpesaTxn->amount,
            $msisdn,
        );
    }
}
