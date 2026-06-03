<?php

namespace App\Services\Payment\Mpesa;

use App\Enums\PaymentIntentStatus;
use App\Enums\WebhookEventType;
use App\Models\Mpesa\MpesaShortcode;
use App\Models\Mpesa\MpesaTransaction;
use App\Models\PaymentGateway;
use App\Models\Transaction as TransactionModel;
use App\Services\Payment\Concerns\HasStandardGatewayCapabilities;
use App\Services\Payment\PaymentGateway as PaymentGatewayInterface;
use App\Services\PaymentIntentService;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Transaction;

class MpesaPaymentGateway implements PaymentGatewayInterface
{
    use HasStandardGatewayCapabilities;

    protected array $credentials;

    public function __construct(
        protected DarajaClient $darajaClient,
        protected MpesaFeeCalculator $feeCalculator,
        protected PaymentIntentService $paymentIntentService,
        protected WebhookDispatcher $webhookDispatcher,
    ) {
        $this->credentials = PaymentGateway::getCredentials('mpesa');
    }

    public function deposit($amount, $currency, $trxId)
    {
        if (strtoupper($currency) !== 'KES') {
            throw new \RuntimeException('M-PESA only supports KES.');
        }

        $this->validateAmount((float) $amount);

        $transaction = TransactionModel::findTransaction($trxId);
        $intent      = $this->paymentIntentService->findByTrxId($trxId);

        if (! $intent) {
            throw new \RuntimeException('Payment intent not found for M-PESA checkout.');
        }

        $phone = request()->input('mpesa_phone') ?? request()->input('phone');

        if (! $phone) {
            return redirect()->route('payment.mpesa.checkout', ['token' => encrypt($trxId)]);
        }

        $shortcode = $this->resolveShortcode($intent->merchant_id, request()->input('shortcode_type', 'till'));

        if ($shortcode->isTill()) {
            return $this->initiateStkPush($intent, $transaction, $shortcode, $phone, (float) $amount);
        }

        $billRef = $this->paymentIntentService->generateBillRefNumber($intent);

        $this->paymentIntentService->markRequiresAction($intent, 'display_paybill_instructions', [
            'paybill'      => $shortcode->shortcode,
            'account_ref'  => $billRef,
            'amount'       => $amount,
            'currency'     => $currency,
            'instructions' => __('Pay via M-PESA Paybill, then enter account :ref', ['ref' => $billRef]),
        ]);

        MpesaTransaction::query()->create([
            'mpesa_shortcode_id'  => $shortcode->id,
            'payment_intent_id'   => $intent->id,
            'bill_ref_number'     => $billRef,
            'amount'              => $amount,
            'transaction_type'    => 'paybill_pending',
            'status'              => 'pending',
        ]);

        return redirect()->route('payment.mpesa.paybill', ['token' => encrypt($trxId)]);
    }

    public function handleIPN(Request $request)
    {
        return app(\App\Http\Controllers\Webhook\MpesaWebhookController::class)->handleGenericIpn($request);
    }

    protected function initiateStkPush($intent, $transaction, MpesaShortcode $shortcode, string $phone, float $amount)
    {
        $passkey   = $this->credential('passkey');
        $timestamp = $this->darajaClient->timestamp();
        $password  = $this->darajaClient->generatePassword($shortcode->shortcode, $passkey);

        $stk = $this->darajaClient->stkPush(
            $shortcode->shortcode,
            $password,
            $timestamp,
            $phone,
            $amount,
            $this->paymentIntentService->generateBillRefNumber($intent),
            'DigiKash Payment',
            route('webhooks.mpesa.stk-callback'),
        );

        MpesaTransaction::query()->create([
            'mpesa_shortcode_id' => $shortcode->id,
            'payment_intent_id'  => $intent->id,
            'bill_ref_number'    => $stk['CheckoutRequestID'] ?? null,
            'msisdn'             => $phone,
            'amount'             => $amount,
            'transaction_type'   => 'stk_push',
            'status'             => 'pending',
            'raw_payload'        => $stk,
        ]);

        $this->paymentIntentService->markRequiresAction($intent, 'mpesa_stk_push', [
            'checkout_request_id' => $stk['CheckoutRequestID'] ?? null,
            'message'             => __('Enter your M-PESA PIN on your phone to complete payment.'),
        ]);

        if (! empty($stk['_sandbox_decline'])) {
            return redirect()->route('status.cancel', ['trx_id' => $transaction->trx_id]);
        }

        if (str_contains((string) config('app.url'), 'localhost') || ($this->credential('environment') === 'sandbox')) {
            dispatch(function () use ($intent, $transaction, $amount, $phone) {
                sleep(2);
                app(\App\Http\Controllers\Webhook\MpesaWebhookController::class)
                    ->completeSandboxStk($intent, $transaction, $amount, $phone);
            })->afterResponse();
        }

        return redirect()->route('payment.mpesa.stk-wait', ['token' => encrypt($transaction->trx_id)]);
    }

    protected function resolveShortcode(int $merchantId, string $type): MpesaShortcode
    {
        $shortcode = MpesaShortcode::query()
            ->where('merchant_id', $merchantId)
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if (! $shortcode) {
            $shortcode = MpesaShortcode::query()
                ->whereNull('merchant_id')
                ->where('type', $type)
                ->where('is_active', true)
                ->first();
        }

        if (! $shortcode) {
            throw new \RuntimeException('No active M-PESA shortcode configured for type: '.$type);
        }

        return $shortcode;
    }

    protected function validateAmount(float $amount): void
    {
        if ($amount < 10 || $amount > 250000) {
            throw new \RuntimeException('M-PESA amount must be between KES 10 and KES 250,000.');
        }
    }

    protected function credential(string $key, mixed $default = ''): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
