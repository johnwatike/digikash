<?php

namespace App\Services;

use App\Data\TransactionData;
use App\Enums\AmountFlow;
use App\Enums\EnvironmentMode;
use App\Enums\PaymentIntentStatus;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Enums\WebhookEventType;
use App\Events\PaymentIntentCreated;
use App\Events\PaymentIntentRequiresAction;
use App\Events\PaymentIntentStatusChanged;
use App\Events\PaymentIntentSucceeded;
use App\Models\Currency as CurrencyModel;
use App\Models\Merchant;
use App\Models\PaymentIntent;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Support\Facades\DB;
use Transaction;
use Wallet;

class PaymentIntentService
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected FraudRuleEngine $fraudRuleEngine,
        protected LedgerService $ledgerService,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function createFromMerchantPayment(
        Merchant $merchant,
        array $paymentData,
        float $paymentAmount,
        string $currencyCode,
        EnvironmentMode $environment,
        ?string $idempotencyKey = null,
    ): PaymentIntent {
        $calculation = $this->calculateAmounts($paymentAmount, (float) $merchant->fee);

        $merchant->loadMissing('user');
        $merchantWallet = Wallet::getWalletByUserId($merchant->user->id, $currencyCode);

        if (! $merchantWallet) {
            throw new \RuntimeException('Receiver wallet for this currency is not available.');
        }

        if ($merchant->enforce_unique_ref_trx && ! empty($paymentData['ref_trx'])) {
            $exists = PaymentIntent::query()
                ->where('merchant_id', $merchant->id)
                ->where('ref_trx', $paymentData['ref_trx'])
                ->whereNotIn('status', [PaymentIntentStatus::CANCELED, PaymentIntentStatus::FAILED])
                ->exists();

            if ($exists) {
                throw new \RuntimeException('Duplicate ref_trx is not allowed for this merchant.');
            }
        }

        $this->fraudRuleEngine->evaluatePaymentIntent($merchant, $paymentAmount, $currencyCode, $paymentData);

        return DB::transaction(function () use (
            $merchant,
            $paymentData,
            $paymentAmount,
            $currencyCode,
            $environment,
            $idempotencyKey,
            $calculation,
            $merchantWallet,
        ) {
            $transaction = Transaction::create(new TransactionData(
                user_id: $merchant->user->id,
                trx_type: TrxType::RECEIVE_PAYMENT,
                amount: $calculation['amount'],
                amount_flow: AmountFlow::PLUS,
                fee: $calculation['fee'],
                currency: $currencyCode,
                net_amount: $calculation['net_amount'],
                payable_amount: $paymentAmount,
                payable_currency: $currencyCode,
                wallet_reference: $merchantWallet->uuid,
                trx_data: array_merge($paymentData, ['payment_intent_pending' => true]),
                description: $paymentData['description'] ?? __('Payment from :customer', ['customer' => $paymentData['customer_name'] ?? 'Customer']),
                status: TrxStatus::PENDING
            ));

            if ($environment->isSandbox()) {
                $transaction->remarks = 'SANDBOX_TRANSACTION';
                $transaction->save();
            }

            $intent = PaymentIntent::query()->create([
                'merchant_id'      => $merchant->id,
                'trx_id'           => $transaction->trx_id,
                'status'           => PaymentIntentStatus::REQUIRES_PAYMENT_METHOD,
                'amount'           => $paymentAmount,
                'fee'              => $calculation['fee'],
                'net_amount'       => $calculation['net_amount'],
                'currency'         => $currencyCode,
                'idempotency_key'  => $idempotencyKey,
                'ref_trx'          => $paymentData['ref_trx'] ?? null,
                'environment'      => $environment->value,
                'metadata'         => [
                    'customer_name'  => $paymentData['customer_name'] ?? null,
                    'customer_email' => $paymentData['customer_email'] ?? null,
                    'description'    => $paymentData['description'] ?? null,
                ],
                'expires_at' => now()->addMinutes(900),
            ]);

            $intent->events()->create([
                'to_status' => PaymentIntentStatus::REQUIRES_PAYMENT_METHOD->value,
                'reason'    => 'created',
            ]);

            $trxData = $transaction->trx_data;
            $trxData['payment_intent_id'] = $intent->pi_id;
            $transaction->trx_data = $trxData;
            $transaction->save();

            event(new PaymentIntentCreated($intent));

            $this->webhookDispatcher->dispatch(
                $merchant,
                WebhookEventType::PAYMENT_INTENT_CREATED,
                $this->serializeIntent($intent),
                $intent->pi_id,
                $environment->value,
            );

            return $intent;
        });
    }

    public function markRequiresAction(PaymentIntent $intent, string $actionType, array $actionData): PaymentIntent
    {
        $intent->transitionTo(PaymentIntentStatus::REQUIRES_ACTION, $actionType, $actionData);
        $intent->next_action_type = $actionType;
        $intent->next_action_data = $actionData;
        $intent->save();

        event(new PaymentIntentRequiresAction($intent));

        $this->webhookDispatcher->dispatch(
            $intent->merchant,
            WebhookEventType::PAYMENT_INTENT_REQUIRES_ACTION,
            $this->serializeIntent($intent),
            $intent->pi_id,
            $intent->environment,
        );

        return $intent;
    }

    public function markProcessing(PaymentIntent $intent): PaymentIntent
    {
        $intent->transitionTo(PaymentIntentStatus::PROCESSING, 'processing');
        event(new PaymentIntentStatusChanged($intent));

        return $intent;
    }

    public function markSucceeded(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->status === PaymentIntentStatus::SUCCEEDED) {
            return $intent;
        }

        $intent->transitionTo(PaymentIntentStatus::SUCCEEDED, 'payment_completed');
        $intent->next_action_type = null;
        $intent->next_action_data = null;
        $intent->save();

        $this->ledgerService->postPaymentSuccess($intent);

        event(new PaymentIntentSucceeded($intent));

        return $intent;
    }

    public function markFailed(PaymentIntent $intent, ?string $reason = null): PaymentIntent
    {
        if ($intent->status->isTerminal()) {
            return $intent;
        }

        $intent->transitionTo(PaymentIntentStatus::FAILED, $reason);
        event(new PaymentIntentStatusChanged($intent));

        return $intent;
    }

    public function markCanceled(PaymentIntent $intent, ?string $reason = null): PaymentIntent
    {
        if ($intent->status->isTerminal()) {
            return $intent;
        }

        $intent->transitionTo(PaymentIntentStatus::CANCELED, $reason);
        event(new PaymentIntentStatusChanged($intent));

        return $intent;
    }

    public function findByPiId(string $piId, ?int $merchantId = null): ?PaymentIntent
    {
        $query = PaymentIntent::query()->where('pi_id', $piId);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        return $query->first();
    }

    public function findByTrxId(string $trxId): ?PaymentIntent
    {
        return PaymentIntent::query()->where('trx_id', $trxId)->first();
    }

    public function generateBillRefNumber(PaymentIntent $intent): string
    {
        return strtoupper(substr($intent->pi_id, 3, 12));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeIntent(PaymentIntent $intent): array
    {
        return [
            'id'               => $intent->pi_id,
            'object'           => 'payment_intent',
            'status'           => $intent->status->value,
            'amount'           => $intent->amount,
            'currency'         => $intent->currency,
            'fee'              => $intent->fee,
            'net_amount'       => $intent->net_amount,
            'trx_id'           => $intent->trx_id,
            'ref_trx'          => $intent->ref_trx,
            'client_secret'    => $intent->client_secret,
            'environment'      => $intent->environment,
            'metadata'         => $intent->metadata,
            'next_action'      => $intent->next_action_type ? [
                'type' => $intent->next_action_type,
                'data' => $intent->next_action_data,
            ] : null,
            'created_at'       => $intent->created_at?->toIso8601String(),
            'updated_at'       => $intent->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{fee: float, amount: float, net_amount: float}
     */
    protected function calculateAmounts(float $amount, float $merchantFee): array
    {
        $fee       = $amount * $merchantFee / 100;
        $netAmount = $amount - $fee;

        return [
            'fee'        => $fee,
            'amount'     => $netAmount,
            'net_amount' => $netAmount,
        ];
    }
}
